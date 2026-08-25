<?php
/**
 * retreat_remap_item.php
 *
 * Remap a single list item to another on every ca_objects record that
 * currently carries the source item as a value for a given metadata
 * element. Goes through the CA BaseModelWithAttributes API so the change
 * log is fed, the search engine is reindexed and triggers fire.
 *
 * Usage:
 *   php retreat_remap_item.php \
 *     --setup-path /var/www/.../providence/setup.php \
 *     --element-code domaine \
 *     --from 37832 \
 *     --to   37786 \
 *     [--dry-run | --apply]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$opts = getopt('', [
    'setup-path:',
    'element-code:',
    'from:',
    'to:',
    'dry-run',
    'apply',
]);

$setupPath   = $opts['setup-path']   ?? '/var/www/mayenne/collectiveaccess/providence/setup.php';
$elementCode = $opts['element-code'] ?? 'domaine';
$fromItemId  = isset($opts['from']) ? (int)$opts['from'] : 0;
$toItemId    = isset($opts['to'])   ? (int)$opts['to']   : 0;
$apply       = isset($opts['apply']);

if (!$fromItemId || !$toItemId) {
    fwrite(STDERR, "Missing --from and/or --to (ca_list_items.item_id)\n");
    exit(1);
}
if (!is_readable($setupPath)) {
    fwrite(STDERR, "setup.php not found: $setupPath\n");
    exit(1);
}

require_once $setupPath;
require_once __CA_MODELS_DIR__ . '/ca_objects.php';
require_once __CA_MODELS_DIR__ . '/ca_metadata_elements.php';

$elementId = (int)ca_metadata_elements::getElementID($elementCode);
if (!$elementId) {
    fwrite(STDERR, "Unknown element code: $elementCode\n");
    exit(1);
}

$mode = $apply ? 'APPLY' : 'DRY-RUN';
fwrite(STDOUT, "[$mode] element_code=$elementCode (element_id=$elementId)  from=$fromItemId  to=$toItemId\n");

$db = new Db();

$qr = $db->query("
    SELECT DISTINCT a.row_id AS object_id
    FROM ca_attributes a
    JOIN ca_attribute_values av ON av.attribute_id = a.attribute_id
    JOIN ca_objects o ON o.object_id = a.row_id
    WHERE a.table_num = 57
      AND av.element_id = ?
      AND av.item_id    = ?
      AND o.deleted     = 0
    ORDER BY a.row_id
", [$elementId, $fromItemId]);

$objectIds = [];
while ($qr->nextRow()) {
    $objectIds[] = (int)$qr->get('object_id');
}

$total = count($objectIds);
fwrite(STDOUT, "Objects to update: $total\n");
if ($total === 0) { exit(0); }

$ok = 0; $fail = 0; $skip = 0;

foreach ($objectIds as $i => $objectId) {
    $progress = sprintf("[%4d/%d] obj %6d", $i + 1, $total, $objectId);

    $obj = new ca_objects($objectId);
    if (!$obj->getPrimaryKey()) {
        fwrite(STDOUT, "$progress  ERR  not loadable\n");
        $fail++;
        continue;
    }

    $attrs = $obj->getAttributesByElement($elementCode);
    $edited = 0;

    foreach ($attrs as $attr) {
        $hit = false;
        foreach ($attr->getValues() as $val) {
            if (method_exists($val, 'getItemID') && (int)$val->getItemID() === $fromItemId) {
                $hit = true;
                break;
            }
        }
        if (!$hit) { continue; }

        if ($apply) {
            $obj->editAttribute(
                $attr->getAttributeID(),
                $elementCode,
                [$elementCode => $toItemId]
            );
        }
        $edited++;
    }

    if ($edited === 0) {
        fwrite(STDOUT, "$progress  SKIP no matching attribute on object\n");
        $skip++;
        continue;
    }

    if ($apply) {
        $obj->update();
        if ($obj->numErrors() > 0) {
            $errs = [];
            foreach ($obj->getErrors() as $e) { $errs[] = is_string($e) ? $e : (string)$e; }
            fwrite(STDOUT, "$progress  ERR  " . implode(' | ', $errs) . "\n");
            $fail++;
            continue;
        }
    }

    fwrite(STDOUT, "$progress  OK   ($edited attr)\n");
    $ok++;
}

fwrite(STDOUT, "\n[$mode] done. OK=$ok  SKIP=$skip  FAIL=$fail  TOTAL=$total\n");
