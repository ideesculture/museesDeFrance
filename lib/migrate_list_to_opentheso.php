<?php
/**
 * migrate_list_to_opentheso.php
 *
 * Migrate a legacy CollectiveAccess list (typically a `dmf_lex*` list
 * populated from the 2014 SMF lexicon) to a fresh OpenTheso thesaurus
 * (the SMF canonical source), preserving every attribute reference and
 * grouping unmappable source items under a per-instance "à retraiter"
 * branch in the new list.
 *
 * --dry-run is the default. Use --apply to actually write to the DB.
 *
 * Companion: lib/diagnose_thesaurus_mapping.py (read-only diagnostic).
 *
 * Steps (in --apply mode):
 *   1. Fetch the target OpenTheso JSON
 *   2. Create / update the target list in ca_lists (idno = list-code)
 *   3. Insert every OpenTheso concept as a ca_list_items row, idno =
 *      OpenTheso identifier, parent_id reflecting SKOS broader/narrower
 *   4. Build the auto-mapping (exact, normalized, altLabel) between the
 *      source items and the target items
 *   5. For unmapped source items with usage > 0, create them as children
 *      of the retreat branch (preserving label & original idno)
 *   6. Apply mapping overrides supplied via --overrides JSON
 *   7. Update `ca_attribute_values.item_id` for every row whose item is
 *      in the source list, remapping it to the corresponding target item
 *   8. Repoint the metadata element (`ca_metadata_elements.list_id`) to
 *      the target list
 *
 * Usage:
 *   php migrate_list_to_opentheso.php \
 *     --setup-path /var/www/.../providence/setup.php \
 *     [--source-list-code dmf_lexdomn] \
 *     [--target-theso-id th294] \
 *     [--target-list-code th294] \
 *     [--element-code domaine] \
 *     [--retreat-label "à retraiter - <musée>"] \
 *     [--retreat-idno-prefix retreat_] \
 *     [--overrides path/to/overrides.json] \
 *     [--dry-run | --apply]
 *
 * The overrides JSON has the form:
 *   { "source_idno": "target_idno_or_RETREAT_or_SKIP", ... }
 *
 * Special targets:
 *   RETREAT — put under the retreat branch (preserve source label + idno)
 *   SKIP    — leave as-is (no migration for this item; should have 0 usage)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Argument parsing
// ---------------------------------------------------------------------------
$shortOpts = '';
$longOpts = [
    'setup-path:',
    'source-list-code:',
    'target-theso-id:',
    'target-list-code:',
    'element-code:',
    'retreat-label:',
    'retreat-idno-prefix:',
    'overrides:',
    'dry-run',
    'apply',
    'help',
];
$opts = getopt($shortOpts, $longOpts);

if (isset($opts['help'])) {
    fwrite(STDOUT, file_get_contents(__FILE__) ?: 'see file header.');
    exit(0);
}

if (empty($opts['setup-path'])) {
    fwrite(STDERR, "Missing required --setup-path /path/to/providence/setup.php\n");
    exit(1);
}
$setupPath = $opts['setup-path'];
if (!file_exists($setupPath)) {
    fwrite(STDERR, "setup.php not found at: $setupPath\n");
    exit(1);
}

require_once $setupPath;
require_once __CA_LIB_DIR__ . '/Configuration.php';
require_once __CA_MODELS_DIR__ . '/ca_lists.php';
require_once __CA_MODELS_DIR__ . '/ca_list_items.php';
require_once __CA_MODELS_DIR__ . '/ca_metadata_elements.php';

$sourceListCode    = $opts['source-list-code']    ?? 'dmf_lexdomn';
$targetThesoId     = $opts['target-theso-id']     ?? 'th294';
$targetListCode    = $opts['target-list-code']    ?? $targetThesoId;
$elementCode       = $opts['element-code']        ?? 'domaine';
$retreatLabel      = $opts['retreat-label']       ?? 'à retraiter';
$retreatIdnoPrefix = $opts['retreat-idno-prefix'] ?? 'retreat_';
$overridesPath     = $opts['overrides']           ?? null;
$mode              = isset($opts['apply']) ? 'apply' : 'dry-run';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function out(string $msg): void {
    fwrite(STDOUT, $msg . "\n");
}

function fatal(string $msg): void {
    fwrite(STDERR, "FATAL: " . $msg . "\n");
    exit(2);
}

/**
 * Lowercase, strip diacritics, collapse all dash/quote variants and
 * multiple spaces. Mirrors the Python diagnose script.
 *
 * intl is the cleanest way to strip accents but is not always installed
 * on Debian PHP. iconv with TRANSLIT serves as a fallback.
 */
function normalize_label(?string $s): string {
    if ($s === null) return '';
    $s = trim($s);
    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}+/u', '', $s);
    } else {
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($folded !== false) {
            // iconv may keep stray "TRANSLIT" markers like 'a^' for 'â'; strip them
            $folded = preg_replace("/['^~`\"]/u", '', $folded);
            $s = $folded;
        }
    }
    $s = mb_strtolower($s, 'UTF-8');
    // Replace any dash variant and any apostrophe / single-quote variant
    $s = preg_replace("/[\\-\x{2013}\x{2014}'\x{2018}\x{2019}]/u", ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

function fetch_opentheso(string $thesoId): array {
    $url = "https://opentheso.huma-num.fr/opentheso/api/all/theso?id={$thesoId}&format=json";
    $ctx = stream_context_create(['http' => ['follow_location' => 1, 'timeout' => 30]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        fatal("Unable to fetch OpenTheso JSON: $url");
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fatal("Unable to decode OpenTheso JSON for $thesoId");
    }
    return $data;
}

/**
 * Parse the raw OpenTheso JSON into a flat list of SKOS Concepts.
 */
function parse_theso_concepts(array $raw): array {
    $PREFLABEL  = 'http://www.w3.org/2004/02/skos/core#prefLabel';
    $ALTLABEL   = 'http://www.w3.org/2004/02/skos/core#altLabel';
    $BROADER    = 'http://www.w3.org/2004/02/skos/core#broader';
    $TYPE       = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
    $CONCEPT    = 'http://www.w3.org/2004/02/skos/core#Concept';
    $IDENTIFIER = 'http://purl.org/dc/terms/identifier';

    $concepts = [];
    foreach ($raw as $uri => $node) {
        $types = array_map(fn($t) => $t['value'] ?? null, $node[$TYPE] ?? []);
        if (!in_array($CONCEPT, $types, true)) continue;

        $label = null;
        if (isset($node[$PREFLABEL])) {
            foreach ($node[$PREFLABEL] as $entry) {
                if (($entry['lang'] ?? null) === 'fr') {
                    $label = $entry['value'];
                    break;
                }
            }
            if ($label === null && !empty($node[$PREFLABEL])) {
                $label = $node[$PREFLABEL][0]['value'];
            }
        }

        $alts = [];
        if (isset($node[$ALTLABEL])) {
            foreach ($node[$ALTLABEL] as $entry) {
                if (($entry['lang'] ?? null) === 'fr') {
                    $alts[] = $entry['value'];
                }
            }
        }

        $broader = [];
        if (isset($node[$BROADER])) {
            foreach ($node[$BROADER] as $entry) {
                $broader[] = $entry['value'];
            }
        }

        $identifier = null;
        if (isset($node[$IDENTIFIER][0]['value'])) {
            $identifier = $node[$IDENTIFIER][0]['value'];
        }

        $concepts[$uri] = [
            'uri'     => $uri,
            'id'      => $identifier,
            'label'   => $label,
            'alts'    => $alts,
            'broader' => $broader,
            'is_root' => empty($broader),
        ];
    }
    return $concepts;
}

// ---------------------------------------------------------------------------
// Source list extraction
// ---------------------------------------------------------------------------
function load_source_items(string $sourceListCode, string $elementCode): array {
    $db = new Db();

    $listInfo = $db->query("SELECT list_id FROM ca_lists WHERE list_code = ?", $sourceListCode);
    if (!$listInfo->nextRow()) fatal("Source list '$sourceListCode' not found.");
    $listId = (int)$listInfo->get('list_id');

    $elemInfo = $db->query("SELECT element_id FROM ca_metadata_elements WHERE element_code = ?", $elementCode);
    if (!$elemInfo->nextRow()) fatal("Element '$elementCode' not found.");
    $elementId = (int)$elemInfo->get('element_id');

    // Only the preferred label per item — some legacy lists carry alt labels
    // in additional rows (e.g. `ameublement` preferred + `mobilier` alt).
    $qr = $db->query("
        SELECT li.item_id, li.idno, lil.name_singular AS label,
               (SELECT COUNT(*) FROM ca_attribute_values av
                WHERE av.element_id = ? AND av.item_id = li.item_id) AS n_objects
        FROM ca_list_items li
        LEFT JOIN ca_list_item_labels lil
               ON lil.item_id = li.item_id AND lil.is_preferred = 1
        WHERE li.list_id = ? AND li.deleted = 0
        ORDER BY lil.name_singular
    ", $elementId, $listId);

    $items = [];
    while ($qr->nextRow()) {
        $label = $qr->get('label');
        if ($label === null || str_starts_with($label, 'Root node')) continue;
        $items[] = [
            'item_id'  => (int)$qr->get('item_id'),
            'idno'     => $qr->get('idno'),
            'label'    => $label,
            'n_objects'=> (int)$qr->get('n_objects'),
        ];
    }
    return [
        'list_id'    => $listId,
        'element_id' => $elementId,
        'items'      => $items,
    ];
}

// ---------------------------------------------------------------------------
// Target list creation / update
// ---------------------------------------------------------------------------
/**
 * Get or create the target list. Idempotent.
 * Returns the list_id.
 */
function ensure_target_list(string $listCode, string $listLabel, string $mode): int {
    $list = new ca_lists();
    $list->load(['list_code' => $listCode, 'deleted' => 0]);
    if ($list->getPrimaryKey()) {
        return (int)$list->getPrimaryKey();
    }
    if ($mode === 'dry-run') {
        out("  [dry-run] CREATE list_code='{$listCode}' label='{$listLabel}'");
        return -1;
    }
    $list->setMode(ACCESS_WRITE);
    $list->set(['list_code' => $listCode, 'is_system_list' => 0, 'is_hierarchical' => 1]);
    $listId = (int)$list->insert();
    if ($list->numErrors()) fatal("Failed to create list '$listCode': " . join(' / ', $list->getErrors()));
    $list->addLabel(['name' => $listLabel], 2, null, true);
    $list->update();
    return $listId;
}

/**
 * Insert every OpenTheso concept as a ca_list_items row, idno = OpenTheso id.
 * Two passes: first pass creates all items (parent_id null), second pass sets
 * parent_id from SKOS broader (we need both items to exist to link them).
 *
 * Returns map: opentheso_id => ca_list_items.item_id
 */
function ensure_target_items(int $listId, array $concepts, string $mode): array {
    $idnoToItemId = [];

    // 1st pass: create / load each concept
    foreach ($concepts as $uri => $c) {
        if (!$c['id']) continue;
        if ($mode === 'dry-run') {
            $idnoToItemId[$c['id']] = -1;
            continue;
        }
        $item = new ca_list_items();
        $item->load(['idno' => $c['id'], 'deleted' => 0, 'list_id' => $listId]);
        if (!$item->getPrimaryKey()) {
            $item->setMode(ACCESS_WRITE);
            $item->set([
                'idno'       => $c['id'],
                'list_id'    => $listId,
                'access'     => 1,
                'status'     => 2,
                'is_enabled' => 1,
                'item_value' => $c['label'],
                'type_id'    => 2,
            ]);
            $item->insert();
            if ($item->numErrors()) fatal("Failed to create item '{$c['id']}': " . join(' / ', $item->getErrors()));
        }
        // (Re)set label
        $item->removeAllLabels();
        $item->addLabel(
            ['name_singular' => $c['label'], 'name_plural' => $c['label']],
            2, null, true
        );
        $item->set(['is_enabled' => 1]);
        $item->update();
        $idnoToItemId[$c['id']] = (int)$item->getPrimaryKey();
    }

    if ($mode === 'dry-run') {
        out("  [dry-run] CREATE " . count($concepts) . " items in target list");
        return $idnoToItemId;
    }

    // 2nd pass: set parent_id from broader
    foreach ($concepts as $uri => $c) {
        if (!$c['id'] || empty($c['broader'])) continue;
        $parentUri = $c['broader'][0]; // pick first broader
        if (!isset($concepts[$parentUri])) continue;
        $parentTheoId = $concepts[$parentUri]['id'];
        if (!$parentTheoId || !isset($idnoToItemId[$parentTheoId])) continue;

        $item = new ca_list_items();
        $item->load(['idno' => $c['id'], 'deleted' => 0, 'list_id' => $listId]);
        if ($item->getPrimaryKey()) {
            $item->setMode(ACCESS_WRITE);
            $item->set('parent_id', $idnoToItemId[$parentTheoId]);
            $item->update();
        }
    }
    return $idnoToItemId;
}

// ---------------------------------------------------------------------------
// Auto-mapping
// ---------------------------------------------------------------------------
/**
 * Build the auto-mapping. Keyed by source item_id (idno is not unique in
 * some legacy lists — same idno may appear twice).
 */
function build_auto_mapping(array $sourceItems, array $concepts): array {
    $byPref = [];
    $byAltSets = [];
    foreach ($concepts as $c) {
        if ($c['label']) {
            $byPref[normalize_label($c['label'])][] = $c;
        }
        foreach ($c['alts'] as $alt) {
            $key = normalize_label($alt);
            $byAltSets[$key][$c['id']] = $c;
        }
    }
    $byAlt = [];
    foreach ($byAltSets as $k => $set) {
        $byAlt[$k] = array_values($set);
    }

    $mapping = [];
    foreach ($sourceItems as $src) {
        $itemId = $src['item_id'];
        $nl = normalize_label($src['label']);
        $base = [
            'idno' => $src['idno'],
            'label' => $src['label'],
            'n_objects' => $src['n_objects'],
        ];

        if (!empty($byPref[$nl])) {
            $cands = $byPref[$nl];
            $exact = array_values(array_filter($cands, fn($c) => $c['label'] === $src['label']));
            if (!empty($exact)) {
                $mapping[$itemId] = $base + [
                    'target_idno' => $exact[0]['id'],
                    'target_label' => $exact[0]['label'],
                    'via' => 'exact',
                ];
                continue;
            }
            $mapping[$itemId] = $base + [
                'target_idno' => $cands[0]['id'],
                'target_label' => $cands[0]['label'],
                'via' => count($cands) > 1 ? 'normalized-ambiguous' : 'normalized',
                'all_candidates' => array_map(fn($c) => ['id' => $c['id'], 'label' => $c['label']], $cands),
            ];
            continue;
        }

        if (!empty($byAlt[$nl])) {
            $cands = $byAlt[$nl];
            $mapping[$itemId] = $base + [
                'target_idno' => $cands[0]['id'],
                'target_label' => $cands[0]['label'],
                'via' => count($cands) > 1 ? 'altLabel-ambiguous' : 'altLabel',
                'all_candidates' => array_map(fn($c) => ['id' => $c['id'], 'label' => $c['label']], $cands),
            ];
            continue;
        }

        $mapping[$itemId] = $base + [
            'target_idno' => null,
            'target_label' => null,
            'via' => 'no-match',
        ];
    }
    return $mapping;
}

/**
 * Apply user-provided overrides + decide automatic RETREAT/SKIP for unmapped.
 *
 * Overrides are keyed by idno (human-readable). If multiple source items
 * share the same idno (legacy data quality issue), all of them receive
 * the same override.
 *
 * Defaults for items without explicit override:
 *   - n_objects > 0 + no auto match → RETREAT
 *   - n_objects == 0 + no auto match → SKIP
 *   - any ambiguous auto match → RETREAT
 */
function finalize_mapping(array $autoMapping, array $overrides): array {
    foreach ($autoMapping as $itemId => &$entry) {
        if (isset($overrides[$entry['idno']])) {
            $entry['target_idno'] = $overrides[$entry['idno']];
            $entry['via'] .= '+override';
            continue;
        }
        if (str_ends_with($entry['via'], 'ambiguous')) {
            $entry['target_idno'] = 'RETREAT';
            continue;
        }
        if ($entry['via'] === 'no-match') {
            $entry['target_idno'] = $entry['n_objects'] > 0 ? 'RETREAT' : 'SKIP';
        }
    }
    return $autoMapping;
}

// ---------------------------------------------------------------------------
// Retreat branch creation
// ---------------------------------------------------------------------------
/**
 * Create the retreat parent and a child per RETREAT-flagged source item.
 * Returns map: source_item_id => target_item_id (of the retreat child).
 *
 * Several source items may share the same idno but get distinct retreat
 * children (suffixed with the source item_id) — preserves uniqueness.
 */
function ensure_retreat_branch(int $targetListId, string $retreatLabel,
                               string $idnoPrefix, array $mapping,
                               string $mode): array {
    $retreatEntries = array_filter($mapping, fn($e) => $e['target_idno'] === 'RETREAT');
    if (empty($retreatEntries)) return [];

    $retreatIdno = $idnoPrefix . 'root';
    if ($mode === 'dry-run') {
        out("  [dry-run] CREATE retreat parent: idno={$retreatIdno} label='{$retreatLabel}' under target list");
        foreach ($retreatEntries as $srcItemId => $entry) {
            $childIdno = $idnoPrefix . preg_replace('/[^a-zA-Z0-9_]+/u', '_', $entry['idno']) . '_' . $srcItemId;
            out("  [dry-run] CREATE retreat child: idno={$childIdno} label='{$entry['label']}' (n_obj={$entry['n_objects']})");
        }
        return [];
    }

    $parent = new ca_list_items();
    $parent->load(['idno' => $retreatIdno, 'list_id' => $targetListId, 'deleted' => 0]);
    if (!$parent->getPrimaryKey()) {
        $parent->setMode(ACCESS_WRITE);
        $parent->set([
            'idno' => $retreatIdno,
            'list_id' => $targetListId,
            'access' => 1,
            'status' => 2,
            'is_enabled' => 1,
            'item_value' => $retreatLabel,
            'type_id' => 2,
        ]);
        $parent->insert();
        if ($parent->numErrors()) fatal("Failed to create retreat parent: " . join(' / ', $parent->getErrors()));
    }
    $parent->removeAllLabels();
    $parent->addLabel(['name_singular' => $retreatLabel, 'name_plural' => $retreatLabel], 2, null, true);
    $parent->set(['is_enabled' => 1]);
    $parent->update();
    $parentItemId = (int)$parent->getPrimaryKey();

    $retreatItemIds = [];
    foreach ($retreatEntries as $srcItemId => $entry) {
        $childIdno = $idnoPrefix . preg_replace('/[^a-zA-Z0-9_]+/u', '_', $entry['idno']) . '_' . $srcItemId;

        $child = new ca_list_items();
        $child->load(['idno' => $childIdno, 'list_id' => $targetListId, 'deleted' => 0]);
        if (!$child->getPrimaryKey()) {
            $child->setMode(ACCESS_WRITE);
            $child->set([
                'idno' => $childIdno,
                'list_id' => $targetListId,
                'access' => 1,
                'status' => 2,
                'is_enabled' => 1,
                'item_value' => $entry['label'],
                'type_id' => 2,
                'parent_id' => $parentItemId,
            ]);
            $child->insert();
            if ($child->numErrors()) fatal("Failed to create retreat child '{$childIdno}': " . join(' / ', $child->getErrors()));
        }
        $child->setMode(ACCESS_WRITE);
        $child->set(['parent_id' => $parentItemId, 'is_enabled' => 1]);
        $child->removeAllLabels();
        $child->addLabel(['name_singular' => $entry['label'], 'name_plural' => $entry['label']], 2, null, true);
        $child->update();
        $retreatItemIds[$srcItemId] = (int)$child->getPrimaryKey();
    }
    return $retreatItemIds;
}

// ---------------------------------------------------------------------------
// Final source.item_id → target.item_id resolution
// ---------------------------------------------------------------------------
function resolve_mapping(array $mapping, array $idnoToItemId,
                        array $retreatItemIds, string $mode): array {
    $resolved = [];
    foreach ($mapping as $srcItemId => $entry) {
        $tgtIdno = $entry['target_idno'];
        if ($tgtIdno === 'SKIP' || $tgtIdno === null) {
            continue;
        }
        if ($tgtIdno === 'RETREAT') {
            if (isset($retreatItemIds[$srcItemId])) {
                $resolved[$srcItemId] = $retreatItemIds[$srcItemId];
            } elseif ($mode === 'dry-run') {
                $resolved[$srcItemId] = -1;
            } else {
                fatal("Retreat item not created for source item_id $srcItemId");
            }
            continue;
        }
        if (!isset($idnoToItemId[$tgtIdno])) {
            fatal("Target OpenTheso idno '$tgtIdno' (for source item_id $srcItemId, idno {$entry['idno']}) not found in imported list. Check overrides.");
        }
        $resolved[$srcItemId] = $idnoToItemId[$tgtIdno];
    }
    return $resolved;
}

// ---------------------------------------------------------------------------
// Apply: SQL update on ca_attribute_values
// ---------------------------------------------------------------------------
function apply_attribute_updates(int $elementId, array $resolved, string $mode): array {
    $db = new Db();
    $totals = ['updated' => 0, 'orphaned' => 0];

    if ($mode === 'dry-run') {
        foreach ($resolved as $srcItemId => $tgtItemId) {
            $r = $db->query("SELECT COUNT(*) AS n FROM ca_attribute_values WHERE element_id = ? AND item_id = ?",
                            $elementId, $srcItemId);
            $r->nextRow();
            $totals['updated'] += (int)$r->get('n');
        }
        return $totals;
    }
    foreach ($resolved as $srcItemId => $tgtItemId) {
        $db->query("UPDATE ca_attribute_values SET item_id = ? WHERE element_id = ? AND item_id = ?",
                   $tgtItemId, $elementId, $srcItemId);
        $totals['updated'] += $db->affectedRows();
    }
    // Detect orphans (rows with item_id not in resolved map)
    $srcItemIds = array_keys($resolved);
    if (!empty($srcItemIds)) {
        $placeholders = implode(',', array_fill(0, count($srcItemIds), '?'));
        // any remaining row in the source list?
        $r = $db->query("SELECT COUNT(*) AS n FROM ca_attribute_values av JOIN ca_list_items li ON li.item_id = av.item_id
                         WHERE av.element_id = ? AND li.list_id IN (SELECT list_id FROM ca_list_items WHERE item_id IN ($placeholders))",
                        array_merge([$elementId], $srcItemIds));
        $r->nextRow();
        $totals['remaining_on_source_list'] = (int)$r->get('n');
    }
    return $totals;
}

function repoint_element(int $elementId, int $targetListId, string $mode): void {
    if ($mode === 'dry-run') {
        out("  [dry-run] UPDATE ca_metadata_elements SET list_id={$targetListId} WHERE element_id={$elementId}");
        return;
    }
    $db = new Db();
    $db->query("UPDATE ca_metadata_elements SET list_id = ? WHERE element_id = ?",
               $targetListId, $elementId);
}

/**
 * Soft-delete the source list and every list item it contained.
 * Called after the attribute_values have been remapped and the
 * metadata element has been repointed — at that stage the list is
 * unreferenced and can be hidden from the UI.
 */
function disable_source_list(int $sourceListId, string $mode): array {
    $db = new Db();
    $itemsRes = $db->query("SELECT COUNT(*) AS n FROM ca_list_items WHERE list_id = ? AND deleted = 0", $sourceListId);
    $itemsRes->nextRow();
    $itemCount = (int)$itemsRes->get('n');

    if ($mode === 'dry-run') {
        out("  [dry-run] UPDATE ca_list_items SET deleted=1 WHERE list_id={$sourceListId} ({$itemCount} items)");
        out("  [dry-run] UPDATE ca_lists SET deleted=1 WHERE list_id={$sourceListId}");
        return ['items_deleted' => $itemCount, 'list_deleted' => 1];
    }
    $db->query("UPDATE ca_list_items SET deleted = 1 WHERE list_id = ?", $sourceListId);
    $itemsAffected = $db->affectedRows();
    $db->query("UPDATE ca_lists SET deleted = 1 WHERE list_id = ?", $sourceListId);
    return ['items_deleted' => $itemsAffected, 'list_deleted' => $db->affectedRows()];
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
out("=== migrate_list_to_opentheso ===");
out("Mode               : $mode");
out("Source list code   : $sourceListCode");
out("Target OpenTheso id: $targetThesoId");
out("Target list code   : $targetListCode");
out("Element code       : $elementCode");
out("Retreat label      : $retreatLabel");
out("Retreat idno prefix: $retreatIdnoPrefix");
out("Overrides file     : " . ($overridesPath ?? '(none)'));
out("");

// 1. Load overrides
$overrides = [];
if ($overridesPath) {
    if (!file_exists($overridesPath)) fatal("Overrides file not found: $overridesPath");
    $overrides = json_decode(file_get_contents($overridesPath), true);
    if (!is_array($overrides)) fatal("Invalid JSON in $overridesPath");
    out("Loaded " . count($overrides) . " overrides.");
}

// 2. Fetch OpenTheso
out("Fetching OpenTheso $targetThesoId ...");
$raw = fetch_opentheso($targetThesoId);
$concepts = parse_theso_concepts($raw);
out("  Parsed " . count($concepts) . " concepts.");

// 3. Load source list items
out("Loading source list $sourceListCode ...");
$sourceList = load_source_items($sourceListCode, $elementCode);
out("  Source list_id    : {$sourceList['list_id']}");
out("  Source element_id : {$sourceList['element_id']}");
out("  Source items      : " . count($sourceList['items']));

// 4. Build auto-mapping
$autoMapping = build_auto_mapping($sourceList['items'], $concepts);
$mapping = finalize_mapping($autoMapping, $overrides);

// Tally — categories are exclusive (sum = total source items)
$stats = ['theso'=>0,'retreat'=>0,'skip'=>0,'orphan'=>0];
$objStats = ['theso'=>0,'retreat'=>0,'skip'=>0,'orphan'=>0];
foreach ($mapping as $e) {
    $tgt = $e['target_idno'];
    $n = $e['n_objects'];
    if ($tgt === 'RETREAT') { $stats['retreat']++; $objStats['retreat'] += $n; }
    elseif ($tgt === 'SKIP') { $stats['skip']++; $objStats['skip'] += $n; }
    elseif ($tgt === null) { $stats['orphan']++; $objStats['orphan'] += $n; }
    else { $stats['theso']++; $objStats['theso'] += $n; }
}
$total = count($mapping);
$totalObj = array_sum($objStats);
out("");
out("=== Mapping summary ===");
out(sprintf("  Mappés vers th294 (auto + override) : %3d items / %5d objets",
            $stats['theso'], $objStats['theso']));
out(sprintf("  À retraiter (branche dédiée)        : %3d items / %5d objets",
            $stats['retreat'], $objStats['retreat']));
out(sprintf("  Ignorés (SKIP, 0 usage attendu)     : %3d items / %5d objets",
            $stats['skip'], $objStats['skip']));
if ($stats['orphan']) {
    out(sprintf("  Orphelins (target_idno=null, BUG)   : %3d items / %5d objets",
                $stats['orphan'], $objStats['orphan']));
}
out(sprintf("  Total source                        : %3d items / %5d objets",
            $total, $totalObj));

// Per-via breakdown for visibility
$viaStats = [];
foreach ($mapping as $e) {
    $viaStats[$e['via']] = ($viaStats[$e['via']] ?? 0) + 1;
}
out("  Détail des cheminements :");
ksort($viaStats);
foreach ($viaStats as $via => $n) {
    out(sprintf("    %-35s : %d", $via, $n));
}

out("");
out("=== Retreat items ===");
foreach ($mapping as $srcItemId => $e) {
    if ($e['target_idno'] === 'RETREAT') {
        out(sprintf("  [%5d obj] [item_id=%d, idno=%s] %s → RETREAT (via: %s)",
                    $e['n_objects'], $srcItemId, $e['idno'], $e['label'], $e['via']));
    }
}
out("");
out("=== Skip items ===");
foreach ($mapping as $srcItemId => $e) {
    if ($e['target_idno'] === 'SKIP') {
        out(sprintf("  [%5d obj] [item_id=%d, idno=%s] %s",
                    $e['n_objects'], $srcItemId, $e['idno'], $e['label']));
    }
}

// 5. Target list + items + retreat branch
out("");
out("=== Step: target list & items ===");
$targetListLabel = "Liste d'autorités " . $targetThesoId;
$targetListId = ensure_target_list($targetListCode, $targetListLabel, $mode);
$idnoToItemId = ensure_target_items($targetListId, $concepts, $mode);

out("");
out("=== Step: retreat branch ===");
$retreatItemIds = ensure_retreat_branch($targetListId, $retreatLabel,
                                        $retreatIdnoPrefix, $mapping, $mode);

// 6. Build resolved item_id map
$resolved = resolve_mapping($mapping, $idnoToItemId, $retreatItemIds, $mode);
out("");
out("=== Step: attribute values update ===");
out("  Source item_ids to rewrite : " . count($resolved));
$totals = apply_attribute_updates($sourceList['element_id'], $resolved, $mode);
out("  Rows " . ($mode === 'apply' ? 'updated' : 'that would be updated') . " : {$totals['updated']}");

out("");
out("=== Step: repoint element to new list ===");
repoint_element($sourceList['element_id'], $targetListId, $mode);

out("");
out("=== Step: soft-delete source list ===");
$disableStats = disable_source_list($sourceList['list_id'], $mode);
out("  Source list_id={$sourceList['list_id']} : {$disableStats['items_deleted']} items + 1 list flagged deleted=1");

out("");
if ($mode === 'dry-run') {
    out("Dry-run complete. Re-run with --apply to commit changes.");
} else {
    out("Apply complete.");
}
