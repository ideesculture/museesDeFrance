<?php
/**
 * migrate_element_to_is.php
 *
 * PARAMETRIC generalization of migrate_domaine_to_is.php.
 *
 * Convert an arbitrary List-datatype (3) metadata element into an
 * InformationService datatype (20) served by the `SMFThesaurus` plugin, and
 * migrate its existing List values to the IS storage format without loss.
 *
 * Two matching modes for ca_list_items -> Opentheso concept:
 *   --match idno   : legacy behaviour (as migrate_domaine_to_is.php) — match
 *                    ca_list_items.idno against concept `id`.
 *   --match label  : (default) match the item's preferred label (normalized)
 *                    against the thesaurus prefLabel AND altLabels (normalized).
 *                    On match, the stored label is the CANONICAL Opentheso
 *                    prefLabel + URI. On miss, the stored label is the local
 *                    decoded+trimmed label (free text, empty URI).
 *
 * SAFETY (identical hardening to migrate_domaine_to_is.php):
 *   - Refuses to run unless the DB configured under --base-dir matches --database (explicit target guard).
 *   - Defaults to --dry-run: writes NOTHING except backups. Only --apply mutates.
 *   - The write phase runs inside a single DB transaction (rolled back on error).
 *   - Multi-table discovery of (table_num, row_id).
 *   - B1 item_id reset scoped to the value_ids actually migrated.
 *   - Soft-deleted records rewritten via scoped direct SQL.
 *   - Idempotent; purges element-instance cache; post-apply integrity assertion
 *     (0 hybrid / 0 orphaned rows across ALL tables).
 *   - Backups named by element_id; NEVER overwrites an existing backup.
 *   - Never touches production (`mayenne`).
 *
 * The value written through the CA model API is "label|idno(empty)|uri" i.e.
 * "label||uri" for a matched concept, or "label||" for free text. Opentheso ids
 * are non-numeric so the idno segment is ALWAYS empty (CA maps it onto the
 * DECIMAL column value_decimal1).
 *
 * Usage:
 *   php migrate_element_to_is.php --element epoque --thesaurus th289            # dry-run
 *   php migrate_element_to_is.php --element fonctions --thesaurus th304 --match label
 *   php migrate_element_to_is.php --element domaine --thesaurus th294 --match idno --apply
 *
 * @package museesDeFrance
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

# ----------------------------------------------------------------------
# Constants / configuration
# ----------------------------------------------------------------------
const OBJECTS_TABLE_NUM = 57;   // ca_objects (reindex hint only)
const IS_DATATYPE       = 20;   // InformationService
const IS_SERVICE        = 'SMFThesaurus';
// Thesaurus stores ship alongside this script inside the plugin (portable, no hard-coded install path).
define('STORE_DIR', dirname(__DIR__) . '/assets/thesauri');
// CA_BASE_DIR, EXPECTED_DB and SCRATCHPAD are defined from CLI args below (--base-dir / --database / --backup-dir).

function out(string $s): void { fwrite(STDOUT, $s . "\n"); }
function err(string $s): void { fwrite(STDERR, $s . "\n"); }
function abort(string $s): void { err("ABORT: " . $s); exit(1); }

/**
 * Normalize a label for label-mode matching:
 * decode HTML entities, trim, lowercase, strip accents, collapse whitespace.
 */
function mig_normalize(string $s): string {
	$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$s = trim($s);
	$s = mb_strtolower($s, 'UTF-8');
	$from = ['à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', 'œ', 'æ', '’'];
	$to   = ['a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', 'oe', 'ae', "'"];
	$s = str_replace($from, $to, $s);
	$s = preg_replace('/\s+/u', ' ', $s);
	return trim((string)$s);
}

/**
 * Decode + trim only (human-readable free-text label, no &amp;).
 */
function mig_decode(string $s): string {
	return trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

# ----------------------------------------------------------------------
# CLI options
# ----------------------------------------------------------------------
$opts = getopt('', ['element:', 'thesaurus:', 'match:', 'base-dir:', 'database:', 'backup-dir:', 'dry-run', 'apply']);

$element_code = isset($opts['element'])   ? trim((string)$opts['element'])   : '';
$thesaurus    = isset($opts['thesaurus']) ? trim((string)$opts['thesaurus']) : '';
$match_mode   = isset($opts['match'])     ? strtolower(trim((string)$opts['match'])) : 'label';
$apply        = isset($opts['apply']);
$mode         = $apply ? 'APPLY' : 'DRY-RUN';

if ($element_code === '') { abort("--element <code> is required (e.g. --element epoque)."); }
if ($thesaurus === '')    { abort("--thesaurus <thNNN> is required (e.g. --thesaurus th289)."); }
if (!in_array($match_mode, ['idno', 'label'], true)) {
	abort("--match must be 'idno' or 'label' (got '$match_mode').");
}
if (!preg_match('/^th\d+$/', $thesaurus)) {
	abort("--thesaurus must look like thNNN (got '$thesaurus').");
}
$store_path = STORE_DIR . '/' . $thesaurus . '.json';

# Instance-specific paths / target DB — required, so the script can never guess the wrong install or DB.
$ca_base_dir = isset($opts['base-dir']) ? rtrim(trim((string)$opts['base-dir']), '/') : '';
$expected_db = isset($opts['database']) ? trim((string)$opts['database']) : '';
if ($ca_base_dir === '') { abort("--base-dir <path> is required (Providence install root, e.g. /var/www/.../providence)."); }
if ($expected_db === '') { abort("--database <name> is required (the exact DB you intend to migrate — explicit guard against wrong target)."); }
if (!is_readable($ca_base_dir . '/setup.php')) { abort("setup.php not readable under --base-dir: $ca_base_dir"); }
define('CA_BASE_DIR', $ca_base_dir);
define('EXPECTED_DB', $expected_db);
define('SCRATCHPAD', isset($opts['backup-dir']) ? rtrim(trim((string)$opts['backup-dir']), '/') : (sys_get_temp_dir() . '/museesdefrance_migration'));

out("=====================================================================");
out(sprintf(" migrate_element_to_is.php   [mode=%s match=%s]", $mode, $match_mode));
out(sprintf("   element=%s thesaurus=%s", $element_code, $thesaurus));
out("=====================================================================");

# ----------------------------------------------------------------------
# Bootstrap CollectiveAccess
# ----------------------------------------------------------------------
$setup = CA_BASE_DIR . '/setup.php';
if (!is_readable($setup)) { abort("setup.php not readable: $setup"); }
require_once($setup);

# ----------------------------------------------------------------------
# 0. GUARD RAIL — refuse to run unless the configured DB matches the one named in --database
# ----------------------------------------------------------------------
if (!defined('__CA_DB_DATABASE__')) { abort("__CA_DB_DATABASE__ is not defined."); }
if (__CA_DB_DATABASE__ !== EXPECTED_DB) {
	abort("configured database is '" . __CA_DB_DATABASE__ . "', expected '" . EXPECTED_DB . "'. Refusing to run (production protection).");
}
out("[guard] database = " . __CA_DB_DATABASE__ . " (OK)");

require_once(__CA_MODELS_DIR__ . '/ca_metadata_elements.php');

$db = new Db();

# ----------------------------------------------------------------------
# Resolve element_id + list_id from element_code
# ----------------------------------------------------------------------
$qr = $db->query("SELECT element_id, element_code, datatype, list_id, settings FROM ca_metadata_elements WHERE element_code = ?", [$element_code]);
if (!$qr->nextRow()) { abort("element_code '$element_code' not found in ca_metadata_elements."); }
$element_id       = (int)$qr->get('element_id');
$cur_code         = (string)$qr->get('element_code');
$cur_datatype     = (int)$qr->get('datatype');
$cur_list_id      = $qr->get('list_id');
$cur_settings     = $qr->get('settings');              // raw base64(serialize(...)) blob
$cur_settings_b64 = base64_encode((string)$cur_settings);

out(sprintf("[element] id=%d code=%s datatype=%d list_id=%s", $element_id, $cur_code, $cur_datatype, ($cur_list_id === null ? 'NULL' : $cur_list_id)));
if ($cur_datatype === IS_DATATYPE) {
	out("[element] NOTE: datatype is already " . IS_DATATYPE . " (InformationService) — element conversion step will be a no-op.");
}

# ----------------------------------------------------------------------
# 1. BACKUP (always, even in dry-run) — named by element_id, never overwrite
# ----------------------------------------------------------------------
if (!is_dir(SCRATCHPAD)) {
	if (!@mkdir(SCRATCHPAD, 0775, true) && !is_dir(SCRATCHPAD)) {
		abort("cannot create scratchpad: " . SCRATCHPAD);
	}
}
out("");
out("[backup] scratchpad = " . SCRATCHPAD);

$db_name = escapeshellarg(EXPECTED_DB);
$db_pass = defined('__CA_DB_PASSWORD__') ? __CA_DB_PASSWORD__ : '';
$db_host = defined('__CA_DB_HOST__') ? __CA_DB_HOST__ : 'localhost';
$cred = '-u' . escapeshellarg(defined('__CA_DB_USER__') ? __CA_DB_USER__ : 'root');
if ($db_pass !== '') { $cred .= ' -p' . escapeshellarg($db_pass); }
$cred .= ' -h' . escapeshellarg($db_host);

$dump_table = function (string $table, string $suffix) use ($cred, $db_name, $element_id): void {
	$f = SCRATCHPAD . '/backup_' . $suffix . '_' . $element_id . '.sql';
	if (is_file($f)) {
		out(sprintf("[backup] %-22s -> %s (backup existant conservé)", $suffix, $f));
		return;
	}
	$errf = SCRATCHPAD . '/backup_' . $suffix . '_' . $element_id . '.err';
	$cmd = sprintf(
		'mysqldump %s %s %s --where=%s --no-create-info --complete-insert --skip-extended-insert 2>%s > %s',
		$cred, $db_name, escapeshellarg($table),
		escapeshellarg('element_id=' . $element_id),
		escapeshellarg($errf), escapeshellarg($f)
	);
	$o = []; $rc = 0;
	exec($cmd, $o, $rc);
	// ca_metadata_elements must produce a non-empty dump (the element row exists);
	// value tables may legitimately be empty (e.g. element with 0 values).
	$must_be_nonempty = ($table === 'ca_metadata_elements');
	if ($rc !== 0 || !is_file($f) || ($must_be_nonempty && filesize($f) === 0)) {
		err("[backup] stderr: " . @file_get_contents($errf));
		abort("backup of $table failed (rc=$rc) — refusing to continue.");
	}
	out(sprintf("[backup] %-22s -> %s", $suffix, $f));
};

// (a) the element row  (b) ca_attributes  (c) ca_attribute_values — all by element_id
$dump_table('ca_metadata_elements', 'ca_metadata_elements');
$dump_table('ca_attributes',        'ca_attributes');
$dump_table('ca_attribute_values',  'ca_attribute_values');

// (d) rollback SQL: restore original datatype, list_id and settings blob (verbatim).
$settings_hex = bin2hex((string)$cur_settings);
$f_rollback = SCRATCHPAD . '/rollback_element_' . $element_id . '.sql';
if (is_file($f_rollback)) {
	out("[backup] rollback SQL           -> " . $f_rollback . " (backup existant conservé)");
} else {
	$rollback_sql =
		"-- Rollback of the '" . $cur_code . "' (element_id=" . $element_id . ") datatype conversion.\n" .
		"-- Restores datatype=" . $cur_datatype . ", list_id=" . ($cur_list_id === null ? 'NULL' : (int)$cur_list_id) . " and the original settings blob.\n" .
		"-- Run against DATABASE " . EXPECTED_DB . " ONLY. After running, flush caches / restart PHP and reindex.\n" .
		"UPDATE ca_metadata_elements SET\n" .
		"    datatype = " . $cur_datatype . ",\n" .
		"    list_id  = " . ($cur_list_id === null ? 'NULL' : (int)$cur_list_id) . ",\n" .
		"    settings = " . ($settings_hex === '' ? "''" : "UNHEX('" . $settings_hex . "')") . "\n" .
		"WHERE element_id = " . $element_id . ";\n" .
		"-- NOTE: the value payloads (ca_attribute_values) are NOT restored by this file.\n" .
		"-- To restore the ORIGINAL list values, re-import:\n" .
		"--   backup_ca_attribute_values_" . $element_id . ".sql  (after deleting the migrated rows for element_id=" . $element_id . ")\n";
	file_put_contents($f_rollback, $rollback_sql);
	out("[backup] rollback SQL           -> " . $f_rollback);
}
out("[backup] original settings (base64) = " . $cur_settings_b64);

# ----------------------------------------------------------------------
# 2. BUILD MAPPING (before any modification)
# ----------------------------------------------------------------------
out("");
out("[map] loading store: " . $store_path);
if (!is_readable($store_path)) { abort("$thesaurus.json store not readable: " . $store_path); }
$store = json_decode((string)file_get_contents($store_path), true);
if (!is_array($store) || !isset($store['concepts']) || !is_array($store['concepts'])) {
	abort("$thesaurus.json store malformed (no 'concepts').");
}

// index concepts by `id` (for idno mode) and by normalized label (for label mode).
$byId    = [];   // concept id           => concept
$byLabel = [];   // normalized label     => ['label'=>canonical prefLabel, 'uri'=>uri]
foreach ($store['concepts'] as $uri => $c) {
	if (!is_array($c)) { continue; }
	if (isset($c['id'])) { $byId[(string)$c['id']] = $c; }
	$pref = (string)($c['prefLabel'] ?? '');
	$curi = (string)($c['uri'] ?? $uri);
	$np = mig_normalize($pref);
	// First writer wins so a concept's own prefLabel is never shadowed by another's altLabel.
	if ($np !== '' && !isset($byLabel[$np])) {
		$byLabel[$np] = ['label' => $pref, 'uri' => $curi];
	}
	foreach (($c['altLabels'] ?? []) as $alt) {
		$na = mig_normalize((string)$alt);
		if ($na !== '' && !isset($byLabel[$na])) {
			$byLabel[$na] = ['label' => $pref, 'uri' => $curi];
		}
	}
}
out(sprintf("[map] concepts in store: %d (indexed by id: %d, by normalized label: %d)", count($store['concepts']), count($byId), count($byLabel)));

// distinct item_ids actually used by this element's values (non-null only), ALL TABLES.
$qr = $db->query(
	"SELECT av.item_id, li.idno
	 FROM (
	     SELECT DISTINCT av.item_id
	     FROM ca_attribute_values av
	     WHERE av.element_id = ? AND av.item_id IS NOT NULL
	 ) av
	 JOIN ca_list_items li ON li.item_id = av.item_id
	 ORDER BY li.idno",
	[$element_id]
);

$mapping     = [];   // item_id => ['label'=>, 'uri'=>, 'kind'=>'opentheso'|'local', 'idno'=>, 'local_label'=>]
$opentheso_n = 0;
$local       = [];   // free-text (non-matched) items

while ($qr->nextRow()) {
	$item_id = (int)$qr->get('item_id');
	$idno    = (string)$qr->get('idno');

	// fetch the preferred list label (needed for label mode AND for local fallback).
	$lqr = $db->query(
		"SELECT name_singular FROM ca_list_item_labels WHERE item_id = ? AND is_preferred = 1 LIMIT 1",
		[$item_id]
	);
	$raw_label = '';
	if ($lqr->nextRow()) { $raw_label = (string)$lqr->get('name_singular'); }
	$decoded_label = mig_decode($raw_label);   // human-readable, no &amp;

	$hit = null;   // ['label'=>canonical, 'uri'=>...]
	if ($match_mode === 'idno') {
		if (isset($byId[$idno])) {
			$c = $byId[$idno];
			$hit = ['label' => (string)($c['prefLabel'] ?? ''), 'uri' => (string)($c['uri'] ?? '')];
		}
	} else { // label
		$nlabel = mig_normalize($raw_label);
		if ($nlabel !== '' && isset($byLabel[$nlabel])) {
			$hit = $byLabel[$nlabel];
		}
	}

	if ($hit !== null) {
		$mapping[$item_id] = [
			'label'       => $hit['label'],   // canonical Opentheso prefLabel
			'uri'         => $hit['uri'],
			'kind'        => 'opentheso',
			'idno'        => $idno,
			'local_label' => $decoded_label,
		];
		$opentheso_n++;
	} else {
		$entry = [
			'label'       => $decoded_label,  // free text: decoded + trimmed local label
			'uri'         => '',
			'kind'        => 'local',
			'idno'        => $idno,
			'local_label' => $decoded_label,
		];
		$mapping[$item_id] = $entry;
		$local[] = ['item_id' => $item_id] + $entry;
	}
}

$n_items = count($mapping);
out(sprintf("[map] distinct item_ids used = %d", $n_items));
out(sprintf("[map]   -> Opentheso-matched = %d (%.1f%%)", $opentheso_n, $n_items > 0 ? (100.0 * $opentheso_n / $n_items) : 0.0));
out(sprintf("[map]   -> free-text (local) = %d", count($local)));

if (count($local) > 0) {
	out("[map] free-text (non-matched) items (stored as 'label||', no URI):");
	foreach ($local as $l) {
		out(sprintf("        item_id=%-6d idno=%-40s label=%s", $l['item_id'], $l['idno'], $l['label']));
	}
}

// helper: build the CA IS value string "label||uri" for an item_id.
$buildValue = function (int $item_id) use ($mapping): ?string {
	if (!isset($mapping[$item_id])) { return null; }
	$m = $mapping[$item_id];
	// pipe in a local label would break the label|idno|uri encoding -> replace by '/'.
	$label = str_replace('|', '/', (string)$m['label']);
	$uri   = str_replace('|', '', (string)$m['uri']);
	return $label . '||' . $uri;   // idno segment ALWAYS empty (Opentheso ids non-numeric)
};

# ----------------------------------------------------------------------
# Discover the FULL multi-table workload
# ----------------------------------------------------------------------
$qr = $db->query(
	"SELECT DISTINCT a.table_num, a.row_id
	 FROM ca_attributes a
	 JOIN ca_attribute_values av ON av.attribute_id = a.attribute_id
	 WHERE a.element_id = ? AND av.item_id IS NOT NULL
	 ORDER BY a.table_num, a.row_id",
	[$element_id]
);
$workload = [];
$workload_by_table = [];
while ($qr->nextRow()) {
	$tn  = (int)$qr->get('table_num');
	$rid = (int)$qr->get('row_id');
	$workload[] = [$tn, $rid];
	$workload_by_table[$tn] = ($workload_by_table[$tn] ?? 0) + 1;
}

$qr = $db->query(
	"SELECT COUNT(*) AS n_vals FROM ca_attribute_values av
	 WHERE av.element_id = ? AND av.item_id IS NOT NULL",
	[$element_id]
);
$qr->nextRow();
$n_values = (int)$qr->get('n_vals');

out("");
out(sprintf("[workload] records to migrate = %d ; value rows (non-null item_id) = %d", count($workload), $n_values));
foreach ($workload_by_table as $tn => $cnt) {
	$tname = Datamodel::getTableName($tn);
	out(sprintf("[workload]   table_num=%d (%s): %d record(s)", $tn, ($tname !== null ? $tname : '?'), $cnt));
}
if (count($workload) === 0) {
	out("[workload] nothing to migrate — item_id is NULL on every value (already migrated or element has 0 values).");
}
$n_objects = $workload_by_table[OBJECTS_TABLE_NUM] ?? 0;

$qr = $db->query(
	"SELECT COUNT(*) AS n FROM ca_attribute_values av
	 WHERE av.element_id = ? AND av.item_id IS NULL",
	[$element_id]
);
$qr->nextRow();
out(sprintf("[workload] value rows with item_id NULL (already migrated or empty) = %d", (int)$qr->get('n')));

# ----------------------------------------------------------------------
# 3. DRY-RUN sample: up to 15 transformations
# ----------------------------------------------------------------------
out("");
out("[sample] up to 15 example transformations (table/row, old item_id/label -> new IS value):");
$qr = $db->query(
	"SELECT a.table_num, a.row_id, av.value_id, av.item_id
	 FROM ca_attributes a
	 JOIN ca_attribute_values av ON av.attribute_id = a.attribute_id
	 WHERE av.element_id = ? AND av.item_id IS NOT NULL
	 ORDER BY a.table_num, a.row_id
	 LIMIT 15",
	[$element_id]
);
while ($qr->nextRow()) {
	$tn  = (int)$qr->get('table_num');
	$rid = (int)$qr->get('row_id');
	$iid = (int)$qr->get('item_id');
	$old_label = isset($mapping[$iid]) ? $mapping[$iid]['local_label'] : '(?)';
	$new_val   = $buildValue($iid) ?? '(no mapping)';
	$kind      = isset($mapping[$iid]) ? $mapping[$iid]['kind'] : '?';
	out(sprintf("        %s/%-7d item_id=%-6d [%-9s] '%s'  ->  '%s'", (Datamodel::getTableName($tn) ?: $tn), $rid, $iid, $kind, $old_label, $new_val));
}

if (!$apply) {
	out("");
	out("=====================================================================");
	out(" DRY-RUN complete. No datatype change, no value writes were performed.");
	out(" Backups + rollback SQL are in: " . SCRATCHPAD);
	out(" Re-run with --apply to perform the migration.");
	out("=====================================================================");
	exit(0);
}

# ----------------------------------------------------------------------
# 4. APPLY
# ----------------------------------------------------------------------
out("");
out("=====================================================================");
out(" APPLYING migration (transactional)");
out("=====================================================================");

require_once(__CA_LIB_DIR__ . '/Db/Transaction.php');
$trans = new Transaction($db);

try {
	# --- 4a. Convert the element definition to InformationService ---
	$t_element = new ca_metadata_elements();
	$t_element->setTransaction($trans);
	if (!$t_element->load($element_id)) { throw new Exception("cannot load ca_metadata_elements " . $element_id); }

	if ((int)$t_element->get('datatype') !== IS_DATATYPE) {
		$t_element->setMode(ACCESS_WRITE);
		$t_element->set('datatype', IS_DATATYPE);
		$t_element->set('list_id', null);
		$t_element->setSetting('service', IS_SERVICE);
		$t_element->setSetting('thesaurus', $thesaurus);
		$t_element->update();
		if ($t_element->numErrors() > 0) {
			throw new Exception("element conversion failed: " . join(' | ', $t_element->getErrors()));
		}
		out("[4a] element " . $element_id . " converted to datatype=" . IS_DATATYPE . " (service=" . IS_SERVICE . ", thesaurus=" . $thesaurus . ")");
	} else {
		$t_element->setMode(ACCESS_WRITE);
		$t_element->setSetting('service', IS_SERVICE);
		$t_element->setSetting('thesaurus', $thesaurus);
		$t_element->update();
		out("[4a] element already datatype=" . IS_DATATYPE . "; ensured service/thesaurus settings.");
	}

	// flush element caches so the new datatype is picked up when reloading rows.
	if (method_exists($t_element, 'flushCacheForElement')) { $t_element->flushCacheForElement(); }
	if (class_exists('MemoryCache')) { @MemoryCache::flush('ElementDataTypes'); @MemoryCache::flush('ElementCodes'); }
	if (class_exists('CompositeCache')) { @CompositeCache::flush('ElementSets'); @CompositeCache::flush('ElementList'); }
	if (class_exists('ca_attributes') && property_exists('ca_attributes', 's_ca_attributes_element_instance_cache')) {
		ca_attributes::$s_ca_attributes_element_instance_cache = [];
		out("[4a] purged ca_attributes::\$s_ca_attributes_element_instance_cache (stale element-instance cache).");
	}

	# --- 4b. Migrate values, MULTI-TABLE, record by record ---
	$total = count($workload);
	out("[4b] records to process (all tables): $total");

	$migrated_value_ids = [];
	$ok = 0; $skip = 0; $fail = 0; $attrs_edited = 0;
	$deleted_sql_migrated = 0;
	$trans_db = $trans->getDb();

	$table_has_deleted = [];

	foreach ($workload as $i => $tr) {
		list($table_num, $row_id) = $tr;

		$vqr = $db->query(
			"SELECT a.attribute_id, av.value_id, av.item_id
			 FROM ca_attributes a
			 JOIN ca_attribute_values av ON av.attribute_id = a.attribute_id
			 WHERE a.table_num = ? AND a.row_id = ? AND av.element_id = ? AND av.item_id IS NOT NULL",
			[$table_num, $row_id, $element_id]
		);
		$attr_rows = [];
		while ($vqr->nextRow()) {
			$attr_rows[(int)$vqr->get('attribute_id')] = [
				'value_id' => (int)$vqr->get('value_id'),
				'item_id'  => (int)$vqr->get('item_id'),
			];
		}
		if (!$attr_rows) { $skip++; continue; }

		$t_row = Datamodel::getInstanceByTableNum($table_num);
		if (!$t_row) {
			throw new Exception("no model class for table_num=$table_num (row_id=$row_id) — rolling back.");
		}
		$table_name = $t_row->tableName();
		$t_row->setTransaction($trans);

		$is_deleted = false;
		$loaded = $t_row->load($row_id);
		if ($loaded && !array_key_exists($table_num, $table_has_deleted)) {
			$table_has_deleted[$table_num] = (bool)$t_row->hasField('deleted');
		}
		if ($loaded && ($table_has_deleted[$table_num] ?? false)) {
			$is_deleted = ((int)$t_row->get('deleted') === 1);
		}

		if ($is_deleted) {
			foreach ($attr_rows as $attribute_id => $r) {
				$new_val = $buildValue($r['item_id']);
				if ($new_val === null) {
					out(sprintf("      %s/%-7d value %-8d SKIP no mapping for item_id=%d", $table_name, $row_id, $r['value_id'], $r['item_id']));
					continue;
				}
				$m = $mapping[$r['item_id']];
				$label = str_replace('|', '/', (string)$m['label']);
				$uri   = str_replace('|', '', (string)$m['uri']);
				$trans_db->query(
					"UPDATE ca_attribute_values SET value_longtext1 = ?, value_longtext2 = ?, item_id = NULL WHERE value_id = ?",
					[$label, $uri, $r['value_id']]
				);
				$migrated_value_ids[] = $r['value_id'];
				$attrs_edited++;
				$deleted_sql_migrated++;
			}
			$ok++;
			continue;
		}

		$edited = 0;
		$edited_value_ids = [];
		foreach ($attr_rows as $attribute_id => $r) {
			$new_val = $buildValue($r['item_id']);
			if ($new_val === null) {
				out(sprintf("      %s/%-7d attr %-8d SKIP no mapping for item_id=%d", $table_name, $row_id, $attribute_id, $r['item_id']));
				continue;
			}
			$t_row->editAttribute($attribute_id, $element_code, [$element_code => $new_val]);
			$edited++;
			$edited_value_ids[] = $r['value_id'];
		}

		if ($edited === 0) { $skip++; continue; }

		$t_row->update();
		if ($t_row->numErrors() > 0) {
			out(sprintf("      %s/%-7d FAIL %s", $table_name, $row_id, join(' | ', $t_row->getErrors())));
			$fail++;
			throw new Exception("$table_name row $row_id update failed — rolling back entire migration.");
		}
		$ok++;
		$attrs_edited += $edited;
		foreach ($edited_value_ids as $vid) { $migrated_value_ids[] = $vid; }

		if ((($i + 1) % 200) === 0) {
			out(sprintf("      ...progress %d/%d (OK=%d SKIP=%d FAIL=%d)", $i + 1, $total, $ok, $skip, $fail));
		}
	}

	out(sprintf("[4b] done. records OK=%d SKIP=%d FAIL=%d ; attribute values edited=%d (of which %d soft-deleted via direct SQL).",
		$ok, $skip, $fail, $attrs_edited, $deleted_sql_migrated));

	if ($fail > 0) {
		throw new Exception("$fail record(s) failed — rolling back.");
	}

	# --- 4c. [B1] Reset item_id to NULL — SCOPED to the migrated value_ids only ---
	$scoped_value_ids = array_values(array_unique($migrated_value_ids));
	$b1_affected = 0;
	$batch = 500;
	for ($off = 0; $off < count($scoped_value_ids); $off += $batch) {
		$chunk = array_slice($scoped_value_ids, $off, $batch);
		if (!$chunk) { break; }
		$ph = implode(',', array_fill(0, count($chunk), '?'));
		$trans_db->query(
			"UPDATE ca_attribute_values SET item_id = NULL
			 WHERE element_id = ? AND item_id IS NOT NULL AND value_id IN ($ph)",
			array_merge([$element_id], $chunk)
		);
		$b1_affected += (int)$trans_db->affectedRows();
	}
	$expected_model_reset = $attrs_edited - $deleted_sql_migrated;
	out(sprintf("[4c] item_id reset to NULL on %d migrated row(s) via scoped value_id IN(...) (expected model-path edits = %d).", $b1_affected, $expected_model_reset));
	if ($b1_affected !== $expected_model_reset) {
		out(sprintf("[4c] WARNING: scoped reset count (%d) != model-path edited count (%d).", $b1_affected, $expected_model_reset));
	}

	$trans->commit();
	out("[apply] transaction COMMITTED.");

} catch (Exception $e) {
	$trans->rollback();
	err("[apply] transaction ROLLED BACK: " . $e->getMessage());
	abort("migration aborted, database unchanged (rolled back). Cause: " . $e->getMessage());
}

# ----------------------------------------------------------------------
# 5. POST-APPLY VERIFICATION (read-only)
# ----------------------------------------------------------------------
out("");
out("[verify] element datatype now:");
$qr = $db->query("SELECT datatype, list_id FROM ca_metadata_elements WHERE element_id = ?", [$element_id]);
$qr->nextRow();
out(sprintf("         datatype=%d list_id=%s (expected 20 / NULL)", (int)$qr->get('datatype'), ($qr->get('list_id') === null ? 'NULL' : $qr->get('list_id'))));

out("[verify] up to 5 migrated Opentheso values (longtext1=label, longtext2=uri, item_id should be NULL):");
$qr = $db->query(
	"SELECT av.value_id, av.item_id, av.value_longtext1, av.value_longtext2
	 FROM ca_attribute_values av
	 WHERE av.element_id = ? AND av.value_longtext2 <> '' AND av.value_longtext2 IS NOT NULL
	 LIMIT 5",
	[$element_id]
);
while ($qr->nextRow()) {
	out(sprintf("         value_id=%-8d item_id=%s  label='%s'  uri='%s'",
		(int)$qr->get('value_id'),
		($qr->get('item_id') === null ? 'NULL' : $qr->get('item_id')),
		(string)$qr->get('value_longtext1'),
		(string)$qr->get('value_longtext2')));
}

out("[verify] up to 1 local free-text value (longtext2 empty):");
$qr = $db->query(
	"SELECT av.value_id, av.item_id, av.value_longtext1, av.value_longtext2
	 FROM ca_attribute_values av
	 WHERE av.element_id = ? AND (av.value_longtext2 = '' OR av.value_longtext2 IS NULL)
	   AND av.value_longtext1 <> '' AND av.value_longtext1 IS NOT NULL
	 LIMIT 1",
	[$element_id]
);
while ($qr->nextRow()) {
	out(sprintf("         value_id=%-8d item_id=%s  label='%s'  uri='%s'",
		(int)$qr->get('value_id'),
		($qr->get('item_id') === null ? 'NULL' : $qr->get('item_id')),
		(string)$qr->get('value_longtext1'),
		(string)$qr->get('value_longtext2')));
}

out("[verify] asserting integrity across ALL tables for element_id=" . $element_id . " ...");

$qr = $db->query(
	"SELECT COUNT(*) AS n FROM ca_attribute_values av
	 WHERE av.element_id = ? AND av.item_id IS NOT NULL
	   AND av.value_longtext1 IS NOT NULL AND av.value_longtext1 <> ''",
	[$element_id]
);
$qr->nextRow();
$hybrid_n = (int)$qr->get('n');

$qr = $db->query(
	"SELECT COUNT(*) AS n FROM ca_attribute_values av
	 WHERE av.element_id = ? AND av.item_id IS NULL
	   AND av.value_longtext1 REGEXP '^[0-9]+$'",
	[$element_id]
);
$qr->nextRow();
$orphan_n = (int)$qr->get('n');

if ($hybrid_n !== 0 || $orphan_n !== 0) {
	err(sprintf("[verify] ERROR: %d hybrid row(s) (item_id + longtext1) and %d orphaned row(s) (numeric residual, item_id NULL) for element_id=%d, ALL TABLES.", $hybrid_n, $orphan_n, $element_id));
	abort("post-apply verification FAILED: hybrid and/or orphaned rows remain.");
}
out(sprintf("[verify] OK: 0 hybrid rows AND 0 orphaned rows for element_id=%d across all tables.", $element_id));

out("");
out(sprintf("[reindex] %d live ca_objects were modified and SHOULD BE REINDEXED (not done by this script).", $n_objects));
out("          Soft-deleted records migrated via direct SQL do NOT need reindexing.");
out("          Run e.g.: php " . CA_BASE_DIR . "/support/utils/caUtils reindex-search-index -t ca_objects");
out("          If other tables were migrated, reindex them too.");
out("");
out("=====================================================================");
out(" APPLY complete.");
out("=====================================================================");
exit(0);
