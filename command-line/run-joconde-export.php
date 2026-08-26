<?php
/**
 * run-joconde-export.php
 * ----------------------
 *
 * Generate a Joconde export from the command line. Shares its export
 * logic with the JocondeController::Export() web action through
 * `lib/JocondeExporter.php` — both entry points produce identical
 * output (same templates, same post-processing, same file structure).
 *
 * Intended for quick test cycles while iterating on the export:
 * no browser, no anti-duplicate cooldown, verbose progress, optional
 * skip-zip for speed.
 *
 * Usage:
 *   php run-joconde-export.php \
 *     --setup-path /var/www/.../providence/setup.php \
 *     [--plugin-path /var/www/.../providence/app/plugins/museesDeFrance] \
 *     [--set-code joconde] \
 *     [--force]              # bypass the 1-export-per-minute guard
 *     [--skip-zip]           # do not build the .zip archive
 *     [--quiet]              # mute progress output
 *
 * If --plugin-path is omitted, the script defaults to the museesDeFrance
 * plugin directory directly under the CollectiveAccess setup.php location.
 *
 * Exit codes:
 *   0  success (export produced)
 *   1  anti-duplicate guard hit (re-run with --force or wait 1 minute)
 *   2  argument / setup error
 *   3  runtime error during export
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$opts = getopt('', [
    'setup-path:',
    'plugin-path:',
    'set-code:',
    'force',
    'skip-zip',
    'quiet',
    'help',
]);

if (isset($opts['help']) || empty($opts['setup-path'])) {
    fwrite(STDOUT, file_get_contents(__FILE__));
    exit(isset($opts['help']) ? 0 : 2);
}

$setupPath = $opts['setup-path'];
if (!file_exists($setupPath)) {
    fwrite(STDERR, "setup.php not found at: {$setupPath}\n");
    exit(2);
}
require_once $setupPath;

$pluginPath = $opts['plugin-path']
    ?? (__CA_APP_DIR__ . '/plugins/museesDeFrance');
if (!is_dir($pluginPath)) {
    fwrite(STDERR, "Plugin directory not found at: {$pluginPath}\n");
    exit(2);
}

require_once __CA_LIB_DIR__ . '/Configuration.php';
require_once $pluginPath . '/lib/JocondeExporter.php';

$localConf   = $pluginPath . '/conf/local/museesDeFrance.conf';
$defaultConf = $pluginPath . '/conf/museesDeFrance.conf';
$confPath = file_exists($localConf) ? $localConf : $defaultConf;
$config = Configuration::load($confPath);

$quiet = isset($opts['quiet']);
$logger = $quiet ? null : function(string $msg) {
    $stamp = date('H:i:s');
    fwrite(STDOUT, "[{$stamp}] {$msg}\n");
};

$exporter = new JocondeExporter($config, $pluginPath);
if ($logger) $exporter->setLogger($logger);

try {
    $result = $exporter->run([
        'set_code' => $opts['set-code'] ?? 'joconde',
        'force'    => isset($opts['force']),
        'skip_zip' => isset($opts['skip-zip']),
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Export failed: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(3);
}

if (!empty($result['anti_duplicate'])) {
    fwrite(STDERR, "Anti-duplicate guard: an export ran during the current minute. Use --force.\n");
    exit(1);
}

if (!$quiet) {
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "=== Export summary ===\n");
    fwrite(STDOUT, "  refexport            : {$result['refexport']}\n");
    fwrite(STDOUT, "  export_dir           : {$result['export_dir']}\n");
    fwrite(STDOUT, "  txt_path             : {$result['txt_path']}\n");
    fwrite(STDOUT, "  zip_path             : " . ($result['zip_path'] ?? '(skipped)') . "\n");
    fwrite(STDOUT, "  object_count         : {$result['object_count']}\n");
    fwrite(STDOUT, "  images_exported      : {$result['images_exported']}\n");
    fwrite(STDOUT, "  images_not_exported  : {$result['images_not_exported']}\n");
    fwrite(STDOUT, "  images_without_credits: {$result['images_without_credits']}\n");
    fwrite(STDOUT, "  notices_without_image: {$result['notices_without_image']}\n");
}
exit(0);
