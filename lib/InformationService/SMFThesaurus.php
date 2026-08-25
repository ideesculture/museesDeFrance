<?php
/** ---------------------------------------------------------------------
 * SMFThesaurus.php : InformationService plugin serving SMF/Joconde thesauri
 *                    from a flat JSON store (no ca_list_items, no SQLite).
 * ----------------------------------------------------------------------
 * museesDeFrance plugin for CollectiveAccess.
 *
 * Canonical source lives in:
 *   museesDeFrance/lib/InformationService/SMFThesaurus.php
 * and is made loadable by CollectiveAccess through a symlink under the Providence install:
 *   <providence>/app/lib/Plugins/InformationService/SMFThesaurus.php
 *     -> ../../../../app/plugins/museesDeFrance/lib/InformationService/SMFThesaurus.php
 *
 * The store files are produced by:
 *   museesDeFrance/command-line/build_thesaurus_json.php --id th294
 * and stored under:
 *   museesDeFrance/assets/thesauri/<thesaurus>.json
 *
 * @package museesDeFrance
 * @subpackage InformationService
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * ----------------------------------------------------------------------
 */

require_once(__CA_LIB_DIR__."/Plugins/IWLPlugInformationService.php");
require_once(__CA_LIB_DIR__."/Plugins/InformationService/BaseInformationServicePlugin.php");

global $g_information_service_settings_SMFThesaurus;
$g_information_service_settings_SMFThesaurus = array(
	'thesaurus' => array(
		'formatType'  => FT_TEXT,
		'displayType' => DT_FIELD,
		'default'     => 'th294',
		'width'       => 30, 'height' => 1,
		'label'       => _t('Thesaurus id'),
		'description' => _t('Opentheso thesaurus identifier (e.g. "th294"). The matching flat JSON store must exist under museesDeFrance/assets/thesauri/<id>.json. Built with build_thesaurus_json.php.'),
	),
	'limit' => array(
		'formatType'  => FT_NUMBER,
		'displayType' => DT_FIELD,
		'default'     => 50,
		'width'       => 10, 'height' => 1,
		'label'       => _t('Result limit'),
		'description' => _t('Maximum number of results returned by a text lookup.'),
	),
);

class WLPlugInformationServiceSMFThesaurus extends BaseInformationServicePlugin implements IWLPlugInformationService {
	# ------------------------------------------------
	/**
	 * Settings, keyed for the settings manager.
	 * @var array
	 */
	static $s_settings;

	/**
	 * In-memory cache of decoded stores, keyed by thesaurus id.
	 * Avoids re-reading/re-decoding the JSON file on every lookup call.
	 * @var array
	 */
	static $s_store_cache = array();
	# ------------------------------------------------
	/**
	 *
	 */
	public function __construct() {
		global $g_information_service_settings_SMFThesaurus;

		WLPlugInformationServiceSMFThesaurus::$s_settings = $g_information_service_settings_SMFThesaurus;
		parent::__construct();
		$this->info['NAME'] = 'SMFThesaurus';

		$this->description = _t('Serves SMF/Joconde thesauri (Opentheso) from a flat JSON store, without loading them into ca_list_items.');
	}
	# ------------------------------------------------
	protected function getConfigName() {
		return 'smf_thesaurus';
	}
	# ------------------------------------------------
	/**
	 * Get all settings defined by this plugin as an array
	 *
	 * @return array
	 */
	public function getAvailableSettings() {
		return WLPlugInformationServiceSMFThesaurus::$s_settings;
	}
	# ------------------------------------------------
	# Store loading
	# ------------------------------------------------
	/**
	 * Validate a thesaurus id against a strict allow-list pattern to prevent
	 * path traversal / arbitrary file reads.
	 *
	 * @param mixed $pm_thesaurus
	 * @return string|null validated id or null if invalid
	 */
	private function validateThesaurusId($pm_thesaurus) {
		if (!is_string($pm_thesaurus)) { return null; }
		if (!preg_match('/^th[0-9]+$/', $pm_thesaurus)) { return null; }
		return $pm_thesaurus;
	}
	# ------------------------------------------------
	/**
	 * Resolve the thesaurus id from element settings.
	 *
	 * @param array $pa_settings
	 * @return string|null
	 */
	private function getThesaurusId($pa_settings) {
		$vs_thesaurus = is_array($pa_settings) && isset($pa_settings['thesaurus']) ? $pa_settings['thesaurus'] : null;
		if ($vs_thesaurus === null || $vs_thesaurus === '') {
			$vs_thesaurus = WLPlugInformationServiceSMFThesaurus::$s_settings['thesaurus']['default'];
		}
		return $this->validateThesaurusId($vs_thesaurus);
	}
	# ------------------------------------------------
	/**
	 * Absolute path to the flat store for a (validated) thesaurus id.
	 *
	 * @param string $ps_thesaurus already-validated id
	 * @return string
	 */
	private function storePath($ps_thesaurus) {
		// __FILE__ = .../museesDeFrance/lib/InformationService/SMFThesaurus.php
		// store   = .../museesDeFrance/assets/thesauri/<id>.json
		return dirname(__FILE__, 3) . '/assets/thesauri/' . $ps_thesaurus . '.json';
	}
	# ------------------------------------------------
	/**
	 * Load + decode + cache the flat store for a thesaurus. Read-only.
	 *
	 * @param array $pa_settings
	 * @return array|null decoded store or null on failure
	 */
	private function loadStore($pa_settings) {
		$vs_thesaurus = $this->getThesaurusId($pa_settings);
		if ($vs_thesaurus === null) { return null; }

		if (isset(WLPlugInformationServiceSMFThesaurus::$s_store_cache[$vs_thesaurus])) {
			return WLPlugInformationServiceSMFThesaurus::$s_store_cache[$vs_thesaurus];
		}

		$vs_path = $this->storePath($vs_thesaurus);
		if (!is_file($vs_path) || !is_readable($vs_path)) {
			return null;
		}

		$vs_raw = @file_get_contents($vs_path);
		if ($vs_raw === false) { return null; }

		// JSON only — never unserialize external data.
		$va_store = json_decode($vs_raw, true);
		if (!is_array($va_store) || !isset($va_store['concepts']) || !is_array($va_store['concepts'])) {
			return null;
		}

		WLPlugInformationServiceSMFThesaurus::$s_store_cache[$vs_thesaurus] = $va_store;
		return $va_store;
	}
	# ------------------------------------------------
	# Text normalization
	# ------------------------------------------------
	/**
	 * Case- and accent-insensitive normalization. Does not rely on intl/iconv
	 * (not guaranteed here); uses an explicit transliteration table + mb_strtolower.
	 *
	 * @param string $ps_text
	 * @return string
	 */
	private function normalize($ps_text) {
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
		// Collapse runs of whitespace for stable prefix/substring matching.
		$vs = preg_replace('/\s+/u', ' ', $vs);
		return trim($vs);
	}
	# ------------------------------------------------
	# Data
	# ------------------------------------------------
	/**
	 * Find a single concept by exact URI or exact id.
	 *
	 * @param array $pa_store
	 * @param string $ps_needle
	 * @return array|null concept node or null
	 */
	private function resolveExact($pa_store, $ps_needle) {
		if (isset($pa_store['concepts'][$ps_needle])) {
			return $pa_store['concepts'][$ps_needle];
		}
		foreach ($pa_store['concepts'] as $va_concept) {
			if (isset($va_concept['id']) && (string) $va_concept['id'] === (string) $ps_needle) {
				return $va_concept;
			}
		}
		return null;
	}
	# ------------------------------------------------
	/**
	 * Perform lookup against the flat SMF thesaurus store.
	 *
	 * Behaviour:
	 *  - If $ps_search is a URL, or matches a concept id exactly, resolve the
	 *    single matching concept (needed by InformationServiceAttributeValue,
	 *    which requires exactly 1 hit for URI/ID submissions).
	 *  - Otherwise perform a case/accent-insensitive text search over prefLabel
	 *    and altLabels (prefix matches ranked first, then substring matches).
	 *
	 * @param array $pa_settings Plugin settings values (expects 'thesaurus')
	 * @param string $ps_search Search expression, URI or id
	 * @param array $pa_options Options: 'limit' => int
	 * @return array array('results' => array(array('label'=>, 'url'=>, 'idno'=>), ...))
	 *         Note: 'idno' is intentionally always '' — CA maps idno to the
	 *         DECIMAL column value_decimal1 via the stored "label|idno|url" path,
	 *         and Opentheso ids are non-numeric (UUID, "T51-42"). The URI ('url')
	 *         is the stable resolution key.
	 */
	public function lookup($pa_settings, $ps_search, $pa_options=null) {
		if (!is_array($pa_options)) { $pa_options = array(); }
		$va_return = array('results' => array());

		$va_store = $this->loadStore($pa_settings);
		if ($va_store === null) { return $va_return; }

		$ps_search = is_string($ps_search) ? trim($ps_search) : '';
		if ($ps_search === '') { return $va_return; }

		// --- 1) exact resolution by URI or id -------------------------------
		$va_exact = $this->resolveExact($va_store, $ps_search);
		if (isURL($ps_search) || $va_exact !== null) {
			if ($va_exact !== null) {
				$va_return['results'][] = array(
					'label' => (string) $va_exact['prefLabel'],
					'url'   => (string) $va_exact['uri'],
					'idno'  => '',
				);
			}
			return $va_return;
		}

		// --- 2) case/accent-insensitive text search -------------------------
		$vn_limit = (int) caGetOption('limit', $pa_options, 0);
		if ($vn_limit <= 0) {
			$vn_limit = (int) (is_array($pa_settings) && isset($pa_settings['limit']) && (int)$pa_settings['limit'] > 0
				? $pa_settings['limit']
				: WLPlugInformationServiceSMFThesaurus::$s_settings['limit']['default']);
		}

		$vs_needle = $this->normalize($ps_search);
		if ($vs_needle === '') { return $va_return; }

		$va_prefix = array();
		$va_substr = array();

		foreach ($va_store['concepts'] as $va_concept) {
			$va_haystacks = array((string) $va_concept['prefLabel']);
			if (isset($va_concept['altLabels']) && is_array($va_concept['altLabels'])) {
				foreach ($va_concept['altLabels'] as $vs_alt) { $va_haystacks[] = (string) $vs_alt; }
			}

			$vb_prefix = false;
			$vb_substr = false;
			foreach ($va_haystacks as $vs_h) {
				$vs_hn = $this->normalize($vs_h);
				if ($vs_hn === '') { continue; }
				$vn_pos = mb_strpos($vs_hn, $vs_needle, 0, 'UTF-8');
				if ($vn_pos === 0)          { $vb_prefix = true; break; }
				if ($vn_pos !== false)      { $vb_substr = true; }
			}

			if ($vb_prefix || $vb_substr) {
				$va_row = array(
					'label' => (string) $va_concept['prefLabel'],
					'url'   => (string) $va_concept['uri'],
					'idno'  => '',
				);
				if ($vb_prefix) { $va_prefix[] = $va_row; } else { $va_substr[] = $va_row; }
			}
		}

		$va_results = array_merge($va_prefix, $va_substr);
		if (count($va_results) > $vn_limit) {
			$va_results = array_slice($va_results, 0, $vn_limit);
		}

		$va_return['results'] = $va_results;
		return $va_return;
	}
	# ------------------------------------------------
	/**
	 * Return clean display value (prefLabel). Labels here are already clean
	 * prefLabels, so return as-is.
	 *
	 * @param string $ps_text
	 * @return string
	 */
	public function getDisplayValueFromLookupText($ps_text) {
		return is_string($ps_text) ? $ps_text : '';
	}
	# ------------------------------------------------
	/**
	 * Text to index for a stored URI: just the prefLabel (index = label only,
	 * which is the whole point of this plugin).
	 *
	 * @param array $pa_settings
	 * @param string $ps_url stored URI
	 * @return array
	 */
	public function getDataForSearchIndexing($pa_settings, $ps_url) {
		$va_store = $this->loadStore($pa_settings);
		if ($va_store === null || !is_string($ps_url) || $ps_url === '') { return array(); }

		$va_concept = $this->resolveExact($va_store, $ps_url);
		if ($va_concept === null) { return array(); }

		$vs_label = (string) $va_concept['prefLabel'];
		return $vs_label !== '' ? array($vs_label) : array();
	}
	# ------------------------------------------------
	/**
	 * Extra info for a stored URI: the ancestry path (broader chain) as an
	 * HTML-escaped breadcrumb, plus the concept id.
	 *
	 * @param array $pa_settings
	 * @param string $ps_url stored URI
	 * @return array
	 */
	public function getExtraInfo($pa_settings, $ps_url) {
		$va_store = $this->loadStore($pa_settings);
		if ($va_store === null || !is_string($ps_url) || $ps_url === '') { return array(); }

		$va_concept = $this->resolveExact($va_store, $ps_url);
		if ($va_concept === null) { return array(); }

		// Walk the broader chain up to roots (guard against cycles).
		$va_path = array();
		$vs_cur = (string) $va_concept['uri'];
		$va_seen = array();
		while ($vs_cur !== '' && isset($va_store['concepts'][$vs_cur]) && !isset($va_seen[$vs_cur])) {
			$va_seen[$vs_cur] = true;
			$va_node = $va_store['concepts'][$vs_cur];
			array_unshift($va_path, (string) $va_node['prefLabel']);
			$vs_cur = (isset($va_node['broader'][0])) ? (string) $va_node['broader'][0] : '';
		}

		$va_path_escaped = array();
		foreach ($va_path as $vs_p) {
			$va_path_escaped[] = htmlspecialchars($vs_p, ENT_QUOTES, 'UTF-8');
		}

		// 'id' here is a free-form informational blob for the detail panel (NOT a
		// lookup result key mapped to value_decimal1); it carries the Opentheso id.
		return array(
			'id'   => (string) $va_concept['id'],
			'path' => join(' &raquo; ', $va_path_escaped),
		);
	}
	# ------------------------------------------------
	/**
	 * HTML fragment for the "more info" detail panel. All dynamic content is
	 * HTML-escaped before output.
	 *
	 * @param array $pa_settings
	 * @param string $ps_url stored URI
	 * @return array array('display' => html)
	 */
	public function getExtendedInformation($pa_settings, $ps_url) {
		$va_store = $this->loadStore($pa_settings);
		if ($va_store === null || !is_string($ps_url) || $ps_url === '') {
			return array('display' => '');
		}

		$va_concept = $this->resolveExact($va_store, $ps_url);
		if ($va_concept === null) { return array('display' => ''); }

		$vs_label = htmlspecialchars((string) $va_concept['prefLabel'], ENT_QUOTES, 'UTF-8');
		$vs_url   = htmlspecialchars((string) $va_concept['uri'], ENT_QUOTES, 'UTF-8');

		$va_extra = $this->getExtraInfo($pa_settings, $ps_url);
		$vs_path  = isset($va_extra['path']) ? $va_extra['path'] : '';   // already escaped

		$vs_display  = "<p><strong>{$vs_label}</strong></p>";
		if ($vs_path !== '') {
			$vs_display .= "<p>{$vs_path}</p>";
		}
		$vs_display .= "<p><a href='{$vs_url}' target='_blank' rel='noopener noreferrer'>{$vs_url}</a></p>";

		return array('display' => $vs_display);
	}
	# ------------------------------------------------
}
