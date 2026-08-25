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
require_once(__DIR__."/SMFThesaurusStore.php");

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
		return SMFThesaurusStore::validateThesaurusId($pm_thesaurus);
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
	 * Load the COMPACT derived bundle (persistent-cached; the full 17 MB JSON is
	 * only re-parsed on a cache miss). This is what lookup()/resolution use so a
	 * native autocomplete keystroke on th285 never re-parses the whole store.
	 *
	 * @param array $pa_settings
	 * @return array|null compact bundle or null on failure
	 */
	private function loadCompact($pa_settings) {
		$vs_thesaurus = $this->getThesaurusId($pa_settings);
		if ($vs_thesaurus === null) { return null; }
		return SMFThesaurusStore::compact($vs_thesaurus);
	}
	# ------------------------------------------------
	# Data
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

		// Served entirely from the COMPACT bundle (persistent-cached): a keystroke
		// on the 17 MB th285 store does NOT re-parse the full JSON.
		$va_compact = $this->loadCompact($pa_settings);
		if ($va_compact === null) { return $va_return; }

		$ps_search = is_string($ps_search) ? trim($ps_search) : '';
		if ($ps_search === '') { return $va_return; }

		// --- 1) exact resolution by URI or id -------------------------------
		$va_exact = SMFThesaurusStore::resolveExactC($va_compact, $ps_search);
		if (isURL($ps_search) || $va_exact !== null) {
			if ($va_exact !== null) {
				$va_return['results'][] = array(
					'label' => (string) $va_exact['label'],
					'url'   => (string) $va_exact['uri'],
					'idno'  => '',
				);
			}
			return $va_return;
		}

		// --- 2) case/accent-insensitive text search (compact searchIndex) ---
		$vn_limit = (int) caGetOption('limit', $pa_options, 0);
		if ($vn_limit <= 0) {
			$vn_limit = (int) (is_array($pa_settings) && isset($pa_settings['limit']) && (int)$pa_settings['limit'] > 0
				? $pa_settings['limit']
				: WLPlugInformationServiceSMFThesaurus::$s_settings['limit']['default']);
		}

		$va_return['results'] = SMFThesaurusStore::lookupTextC($va_compact, $ps_search, $vn_limit);
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
		$va_compact = $this->loadCompact($pa_settings);
		if ($va_compact === null || !is_string($ps_url) || $ps_url === '') { return array(); }

		$va_concept = SMFThesaurusStore::resolveExactC($va_compact, $ps_url);
		if ($va_concept === null) { return array(); }

		$vs_label = (string) $va_concept['label'];
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
		$va_compact = $this->loadCompact($pa_settings);
		if ($va_compact === null || !is_string($ps_url) || $ps_url === '') { return array(); }

		$va_concept = SMFThesaurusStore::resolveExactC($va_compact, $ps_url);
		if ($va_concept === null) { return array(); }
		$vs_uri = (string) $va_concept['uri'];

		// Delegate the breadcrumb to the shared helper's compact path (follows the
		// FIRST VALID broader edge with a cycle guard, from the persistent-cached
		// bundle), so the fil d'ariane matches the browse widget exactly without a
		// 17 MB re-parse.
		$va_path_rows = SMFThesaurusStore::pathC($va_compact, $vs_uri);

		$va_path_escaped = array();
		foreach ($va_path_rows as $va_row) {
			$va_path_escaped[] = htmlspecialchars((string) $va_row['label'], ENT_QUOTES, 'UTF-8');
		}

		// 'id' here is a free-form informational blob for the detail panel (NOT a
		// lookup result key mapped to value_decimal1); it carries the Opentheso id,
		// which is the trailing segment of the concept URI.
		$va_seg = explode('/', $vs_uri);
		$vs_id  = (string) end($va_seg);

		return array(
			'id'   => $vs_id,
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
		$va_compact = $this->loadCompact($pa_settings);
		if ($va_compact === null || !is_string($ps_url) || $ps_url === '') {
			return array('display' => '');
		}

		$va_concept = SMFThesaurusStore::resolveExactC($va_compact, $ps_url);
		if ($va_concept === null) { return array('display' => ''); }

		$vs_label = htmlspecialchars((string) $va_concept['label'], ENT_QUOTES, 'UTF-8');
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
