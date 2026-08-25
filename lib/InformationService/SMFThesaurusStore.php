<?php
/** ---------------------------------------------------------------------
 * SMFThesaurusStore.php : shared loader + navigation logic for the flat
 * SMF/Joconde thesaurus JSON stores (assets/thesauri/<thNNN>.json).
 * ----------------------------------------------------------------------
 * museesDeFrance plugin for CollectiveAccess.
 *
 * This helper factors out, in ONE place, the store loading, the
 * accent/case-insensitive normalization and a small COMPACT derived bundle
 * (search index, id/label maps, broader chain) so the InformationService
 * plugin (SMFThesaurus.php, lookup/indexing/detail-panel) serves native
 * autocomplete and URI resolution WITHOUT re-parsing the full 17 MB JSON on
 * every request. The tree browse widget is now entirely client-side
 * (assets/js/smfThesaurusBrowser.js), so no server browse endpoint remains.
 *
 * SECURITY
 *  - Thesaurus ids are validated against ^th[0-9]+$ (anti path-traversal);
 *    only that validated id is ever concatenated into a file path, always
 *    rooted under assets/thesauri/.
 *  - `uri` values from the client are used ONLY as array keys into the
 *    already-decoded in-memory store — never to build a filesystem path.
 *  - Stores are decoded with json_decode() only; external data is never
 *    unserialize()'d.
 *
 * PHP 7.4+ / PHP 8 compatible. Relies on mbstring (present here); does not
 * require intl/iconv.
 *
 * @package museesDeFrance
 * @subpackage InformationService
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * ----------------------------------------------------------------------
 */

class SMFThesaurusStore {
	# ------------------------------------------------
	/**
	 * In-memory (per-process) cache of decoded stores, keyed by thesaurus id.
	 * @var array
	 */
	static $s_store_cache = array();
	/**
	 * In-memory (per-process) cache of COMPACT derived structures, keyed by
	 * thesaurus id. See buildCompact()/compact() for the shape.
	 * @var array
	 */
	static $s_compact_cache = array();
	/**
	 * Namespace used for the persistent (redis) cache of compact structures.
	 */
	const CACHE_NAMESPACE = 'smf_thesaurus';
	/**
	 * Schema version of the compact bundle. Bump when buildCompact()'s output
	 * shape changes so persisted (redis) bundles are transparently invalidated.
	 */
	const COMPACT_SCHEMA = 2;
	# ------------------------------------------------
	/**
	 * Validate a thesaurus id against a strict allow-list pattern to prevent
	 * path traversal / arbitrary file reads.
	 *
	 * @param mixed $pm_thesaurus
	 * @return string|null validated id or null if invalid
	 */
	public static function validateThesaurusId($pm_thesaurus) {
		if (!is_string($pm_thesaurus)) { return null; }
		if (!preg_match('/^th[0-9]+$/', $pm_thesaurus)) { return null; }
		return $pm_thesaurus;
	}
	# ------------------------------------------------
	/**
	 * Absolute path to the flat store directory (assets/thesauri).
	 * @return string
	 */
	public static function storeDir() {
		// __FILE__ = .../museesDeFrance/lib/InformationService/SMFThesaurusStore.php
		return dirname(__FILE__, 3) . '/assets/thesauri';
	}
	# ------------------------------------------------
	/**
	 * Absolute path to the flat store for a (validated) thesaurus id.
	 *
	 * @param string $ps_thesaurus already-validated id
	 * @return string
	 */
	public static function storePath($ps_thesaurus) {
		return self::storeDir() . '/' . $ps_thesaurus . '.json';
	}
	# ------------------------------------------------
	/**
	 * Load + decode + cache the flat store for a validated thesaurus id.
	 * Read-only. JSON only — never unserialize external data.
	 *
	 * @param string $ps_thesaurus already-validated id (^th[0-9]+$)
	 * @return array|null decoded store or null on failure
	 */
	public static function load($ps_thesaurus) {
		$vs_thesaurus = self::validateThesaurusId($ps_thesaurus);
		if ($vs_thesaurus === null) { return null; }

		if (isset(self::$s_store_cache[$vs_thesaurus])) {
			return self::$s_store_cache[$vs_thesaurus];
		}

		$vs_path = self::storePath($vs_thesaurus);
		if (!is_file($vs_path) || !is_readable($vs_path)) { return null; }

		$vs_raw = @file_get_contents($vs_path);
		if ($vs_raw === false) { return null; }

		$va_store = json_decode($vs_raw, true);
		if (!is_array($va_store) || !isset($va_store['concepts']) || !is_array($va_store['concepts'])) {
			return null;
		}

		self::$s_store_cache[$vs_thesaurus] = $va_store;
		return $va_store;
	}
	# ------------------------------------------------
	# Compact structures + persistent cache
	# ------------------------------------------------
	/**
	 * Get the COMPACT derived structures for a validated thesaurus id.
	 *
	 * These are small structures derived ONCE from the (17 MB) store, so that a
	 * normal children/path/search/roots/meta request NEVER re-parses the full
	 * JSON: the parse only happens on a cache miss. The compact bundle is:
	 *   - childrenOf  : uri_parent -> [uri_child...]   (only nodes with children)
	 *   - broaderOf   : uri -> first valid broader uri (for path())
	 *   - label       : uri -> prefLabel  (also the uri membership set)
	 *   - idIndex     : concept id -> uri  (for exact resolution by id)
	 *   - hasChildren : { uri => true }  (set of uris that have children)
	 *   - roots       : [uri...]  (validated, sorted by normalized label)
	 *   - searchIndex : [ [normalizedHaystack, uri, prefix?1:0], ... ]
	 *   - meta        : { concept_count => int }
	 *
	 * The InformationService plugin (SMFThesaurus::lookup) consumes this bundle
	 * (searchIndex + label + idIndex) so a native autocomplete keystroke on the
	 * 17 MB th285 store NEVER re-parses the full JSON.
	 *
	 * Lookup order:
	 *   1. per-process static cache (self::$s_compact_cache)
	 *   2. persistent cache (redis via ExternalCache), keyed by thesaurus + the
	 *      JSON file mtime/size (auto-invalidates when the store is rebuilt).
	 *   3. cache miss: load()+buildCompact() then populate both caches.
	 *
	 * @param string $ps_thesaurus already-validated id (^th[0-9]+$)
	 * @return array|null compact bundle or null on failure
	 */
	public static function compact($ps_thesaurus) {
		$vs_thesaurus = self::validateThesaurusId($ps_thesaurus);
		if ($vs_thesaurus === null) { return null; }

		if (isset(self::$s_compact_cache[$vs_thesaurus])) {
			return self::$s_compact_cache[$vs_thesaurus];
		}

		$vs_path = self::storePath($vs_thesaurus);
		if (!is_file($vs_path) || !is_readable($vs_path)) { return null; }

		// Version the persistent cache key by mtime+size of the JSON file, so a
		// rebuilt store transparently invalidates the cached compact bundle.
		$vn_mtime = @filemtime($vs_path);
		$vn_size  = @filesize($vs_path);
		$vs_key   = $vs_thesaurus . '_v' . self::COMPACT_SCHEMA . '_' . (int) $vn_mtime . '_' . (int) $vn_size;

		// 2) persistent cache (redis). Compact structures only — small enough
		//    that fetch()+unserialize stays cheap (unlike the 17 MB store).
		if (self::externalCacheAvailable()) {
			$va_hit = ExternalCache::fetch($vs_key, self::CACHE_NAMESPACE);
			if (is_array($va_hit) && isset($va_hit['childrenOf'])) {
				self::$s_compact_cache[$vs_thesaurus] = $va_hit;
				return $va_hit;
			}
		}

		// 3) miss: parse the store once and derive the compact structures.
		$va_store = self::load($vs_thesaurus);
		if ($va_store === null) { return null; }

		$va_compact = self::buildCompact($va_store);

		self::$s_compact_cache[$vs_thesaurus] = $va_compact;
		if (self::externalCacheAvailable()) {
			// Long TTL: invalidation is driven by the mtime/size in the key, not
			// by expiry. 30 days.
			ExternalCache::save($vs_key, $va_compact, self::CACHE_NAMESPACE, 2592000);
		}
		return $va_compact;
	}
	# ------------------------------------------------
	/**
	 * True if the CA persistent cache facility is usable in this context.
	 * Guards CLI/edge cases where the Cache classes are not bootstrapped.
	 * @return bool
	 */
	private static function externalCacheAvailable() {
		return class_exists('ExternalCache');
	}
	# ------------------------------------------------
	/**
	 * Derive the compact structures from a decoded store. Pure; no I/O.
	 *
	 * @param array $pa_store
	 * @return array compact bundle (see compact())
	 */
	public static function buildCompact($pa_store) {
		$va_concepts = (isset($pa_store['concepts']) && is_array($pa_store['concepts'])) ? $pa_store['concepts'] : array();

		$va_label       = array();
		$va_idIndex     = array();
		$va_childrenOf  = array();
		$va_hasChildren = array();
		$va_searchIndex = array();

		// Pass 1: labels + id index + search index.
		foreach ($va_concepts as $vs_uri => $va_concept) {
			$vs_pref = (string) (isset($va_concept['prefLabel']) ? $va_concept['prefLabel'] : '');
			$va_label[$vs_uri] = $vs_pref;

			// id -> uri map, for the plugin's exact resolution by concept id
			// (URI resolution is served directly by isset($va_label[$uri])).
			if (isset($va_concept['id']) && (string) $va_concept['id'] !== '') {
				$va_idIndex[(string) $va_concept['id']] = (string) $vs_uri;
			}

			// prefLabel (prefix-eligible) + altLabels (substring-only) into the index.
			$vs_pn = self::normalize($vs_pref);
			if ($vs_pn !== '') { $va_searchIndex[] = array($vs_pn, (string) $vs_uri, 1); }
			if (isset($va_concept['altLabels']) && is_array($va_concept['altLabels'])) {
				foreach ($va_concept['altLabels'] as $vs_alt) {
					$vs_an = self::normalize((string) $vs_alt);
					if ($vs_an !== '') { $va_searchIndex[] = array($vs_an, (string) $vs_uri, 0); }
				}
			}
		}

		// Pass 2: valid narrower edges -> childrenOf + hasChildren (deduped).
		foreach ($va_concepts as $vs_uri => $va_concept) {
			$va_narrower = (isset($va_concept['narrower']) && is_array($va_concept['narrower'])) ? $va_concept['narrower'] : array();
			$va_kids = array();
			$va_seen = array();
			foreach ($va_narrower as $vs_child) {
				if (!is_string($vs_child)) { continue; }
				if ($vs_child === $vs_uri) { continue; }
				if (!isset($va_concepts[$vs_child])) { continue; }
				if (isset($va_seen[$vs_child])) { continue; }
				$va_seen[$vs_child] = true;
				$va_kids[] = $vs_child;
			}
			if (!empty($va_kids)) {
				// Sort children by normalized label (same order as children()).
				usort($va_kids, function ($a, $b) use ($va_label) {
					$na = self::normalize(isset($va_label[$a]) ? $va_label[$a] : '');
					$nb = self::normalize(isset($va_label[$b]) ? $va_label[$b] : '');
					return strcmp($na, $nb);
				});
				$va_childrenOf[$vs_uri] = $va_kids;
				$va_hasChildren[$vs_uri] = true;
			}
		}

		// broaderOf: first VALID broader parent per node (for path()).
		$va_broaderOf = array();
		foreach ($va_concepts as $vs_uri => $va_concept) {
			if (isset($va_concept['broader']) && is_array($va_concept['broader'])) {
				foreach ($va_concept['broader'] as $vs_p) {
					if (is_string($vs_p) && isset($va_concepts[$vs_p])) { $va_broaderOf[$vs_uri] = $vs_p; break; }
				}
			}
		}

		// Roots: declared roots that exist as concepts, sorted by normalized label.
		$va_roots = (isset($pa_store['roots']) && is_array($pa_store['roots'])) ? $pa_store['roots'] : array();
		$va_valid_roots = array();
		foreach ($va_roots as $vs_uri) {
			if (is_string($vs_uri) && isset($va_concepts[$vs_uri])) { $va_valid_roots[] = $vs_uri; }
		}
		usort($va_valid_roots, function ($a, $b) use ($va_label) {
			$na = self::normalize(isset($va_label[$a]) ? $va_label[$a] : '');
			$nb = self::normalize(isset($va_label[$b]) ? $va_label[$b] : '');
			return strcmp($na, $nb);
		});

		return array(
			'childrenOf'  => $va_childrenOf,
			'broaderOf'   => $va_broaderOf,
			'label'       => $va_label,
			'idIndex'     => $va_idIndex,
			'hasChildren' => $va_hasChildren,
			'roots'       => $va_valid_roots,
			'searchIndex' => $va_searchIndex,
			'meta'        => array('concept_count' => self::conceptCount($pa_store)),
		);
	}
	# ------------------------------------------------
	# Text normalization
	# ------------------------------------------------
	/**
	 * Case- and accent-insensitive normalization. Does not rely on intl/iconv;
	 * uses an explicit transliteration table + mb_strtolower.
	 *
	 * @param string $ps_text
	 * @return string
	 */
	public static function normalize($ps_text) {
		if (!is_string($ps_text) || $ps_text === '') { return ''; }

		$vs = function_exists('mb_strtolower') ? mb_strtolower($ps_text, 'UTF-8') : strtolower($ps_text);

		static $va_map = null;
		if ($va_map === null) {
			$va_map = array(
				'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
				'ç'=>'c','ć'=>'c','č'=>'c',
				'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ė'=>'e','ę'=>'e','ě'=>'e',
				'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i','į'=>'i',
				'ñ'=>'n','ń'=>'n',
				'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','œ'=>'oe',
				'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ů'=>'u',
				'ý'=>'y','ÿ'=>'y',
				'ß'=>'ss','æ'=>'ae',
			);
		}
		$vs = strtr($vs, $va_map);
		$vs = preg_replace('/\s+/u', ' ', $vs);
		return trim($vs);
	}
	# ------------------------------------------------
	# Navigation primitives
	# ------------------------------------------------
	/**
	 * concept_count from meta, falling back to a live count of concepts.
	 *
	 * @param array $pa_store
	 * @return int
	 */
	public static function conceptCount($pa_store) {
		if (isset($pa_store['meta']['concept_count']) && is_numeric($pa_store['meta']['concept_count'])) {
			return (int) $pa_store['meta']['concept_count'];
		}
		return count($pa_store['concepts']);
	}
	# ------------------------------------------------
	# COMPACT-DRIVEN navigation primitives
	# ----------------------------------------------------------------------
	# These operate on the small compact bundle (see buildCompact()), so a
	# request never re-parses the 17 MB JSON. Used by the IS plugin's lookup()
	# + detail-panel resolution (SMFThesaurus.php).
	# ------------------------------------------------
	/** True if a uri is a known concept in the compact bundle. */
	public static function hasConceptC($pa_compact, $ps_uri) {
		return is_string($ps_uri) && isset($pa_compact['label'][$ps_uri]);
	}
	# ------------------------------------------------
	/** prefLabel of a concept from the compact bundle (or ''). */
	public static function labelC($pa_compact, $ps_uri) {
		return (is_string($ps_uri) && isset($pa_compact['label'][$ps_uri])) ? (string) $pa_compact['label'][$ps_uri] : '';
	}
	# ------------------------------------------------
	/**
	 * Ancestry breadcrumb (root-first, inclusive) from the compact bundle.
	 * Follows the first valid broader edge; guards against cycles.
	 *
	 * @param array $pa_compact
	 * @param string $ps_uri
	 * @return array list of {uri,label}
	 */
	public static function pathC($pa_compact, $ps_uri) {
		if (!self::hasConceptC($pa_compact, $ps_uri)) { return array(); }
		$va_path = array();
		$vs_cur = $ps_uri;
		$va_seen = array();
		$vn_guard = 0;
		while ($vs_cur !== '' && isset($pa_compact['label'][$vs_cur]) && !isset($va_seen[$vs_cur]) && $vn_guard < 1000) {
			$va_seen[$vs_cur] = true;
			array_unshift($va_path, array(
				'uri'   => (string) $vs_cur,
				'label' => (string) $pa_compact['label'][$vs_cur],
			));
			$vs_cur = (isset($pa_compact['broaderOf'][$vs_cur]) && isset($pa_compact['label'][$pa_compact['broaderOf'][$vs_cur]]))
				? (string) $pa_compact['broaderOf'][$vs_cur] : '';
			$vn_guard++;
		}
		return $va_path;
	}
	# ------------------------------------------------
	# COMPACT-DRIVEN resolution + lookup FOR THE IS PLUGIN
	# ----------------------------------------------------------------------
	# These let WLPlugInformationServiceSMFThesaurus::lookup() serve the native
	# autocomplete entirely from the compact bundle, so a keystroke on the 17 MB
	# th285 store never re-parses the full JSON.
	# ------------------------------------------------
	/**
	 * Exact resolution from the compact bundle by URI first, then by concept id.
	 * Returns array('label'=>prefLabel, 'uri'=>uri) or null.
	 *
	 * @param array $pa_compact
	 * @param string $ps_needle URI or concept id
	 * @return array|null
	 */
	public static function resolveExactC($pa_compact, $ps_needle) {
		if (!is_string($ps_needle) || $ps_needle === '') { return null; }
		if (isset($pa_compact['label'][$ps_needle])) {
			return array('label' => (string) $pa_compact['label'][$ps_needle], 'uri' => (string) $ps_needle);
		}
		if (isset($pa_compact['idIndex'][$ps_needle])) {
			$vs_uri = (string) $pa_compact['idIndex'][$ps_needle];
			return array('label' => self::labelC($pa_compact, $vs_uri), 'uri' => $vs_uri);
		}
		return null;
	}
	# ------------------------------------------------
	/**
	 * IS-plugin text lookup over the compact searchIndex. Prefix matches on
	 * prefLabel ranked first, then substring; alphabetical within each bucket;
	 * truncated to limit (TRUE top-N). Mirrors the plugin's previous store-based
	 * scan but uses the precomputed normalized index (no 17 MB re-parse).
	 *
	 * @param array $pa_compact
	 * @param string $ps_query
	 * @param int $pn_limit
	 * @return array list of {label, url, idno=''}  (CA lookup result shape)
	 */
	public static function lookupTextC($pa_compact, $ps_query, $pn_limit = 50) {
		$pn_limit = ((int) $pn_limit > 0) ? (int) $pn_limit : 50;
		$vs_needle = self::normalize($ps_query);
		if ($vs_needle === '') { return array(); }

		$va_index = (isset($pa_compact['searchIndex']) && is_array($pa_compact['searchIndex'])) ? $pa_compact['searchIndex'] : array();

		// Per-uri best rank: prefix on prefLabel (0) beats substring (1).
		$va_best = array();   // uri -> rank
		foreach ($va_index as $va_entry) {
			$vs_hn  = $va_entry[0];
			$vs_uri = $va_entry[1];
			$vn_pos = mb_strpos($vs_hn, $vs_needle, 0, 'UTF-8');
			if ($vn_pos === false) { continue; }
			$vn_rank = ($vn_pos === 0 && (int) $va_entry[2] === 1) ? 0 : 1;
			if (!isset($va_best[$vs_uri]) || $vn_rank < $va_best[$vs_uri]) {
				$va_best[$vs_uri] = $vn_rank;
			}
		}
		if (empty($va_best)) { return array(); }

		$va_prefix = array();
		$va_substr = array();
		foreach ($va_best as $vs_uri => $vn_rank) {
			$vs_pref = self::labelC($pa_compact, $vs_uri);
			$va_row = array(
				'label' => $vs_pref,
				'url'   => (string) $vs_uri,
				'idno'  => '',
				'_n'    => self::normalize($vs_pref),
			);
			if ($vn_rank === 0) { $va_prefix[] = $va_row; } else { $va_substr[] = $va_row; }
		}

		$fn_sort = function ($a, $b) { return strcmp($a['_n'], $b['_n']); };
		usort($va_prefix, $fn_sort);
		usort($va_substr, $fn_sort);

		$va_all = array_merge($va_prefix, $va_substr);
		if (count($va_all) > $pn_limit) { $va_all = array_slice($va_all, 0, $pn_limit); }

		$va_out = array();
		foreach ($va_all as $va_row) {
			$va_out[] = array('label' => $va_row['label'], 'url' => $va_row['url'], 'idno' => '');
		}
		return $va_out;
	}
	# ------------------------------------------------
}
