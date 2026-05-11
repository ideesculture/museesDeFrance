<?php
/**
 * JocondeExporter
 * ===============
 *
 * Produces a Joconde / POP-compatible export of a CollectiveAccess
 * `ca_objects` set, including the SMF-required `.txt` notice file,
 * the resized image media, the per-export `rapport.txt` and the ZIP
 * archive.
 *
 * Used by both:
 *   - `controllers/JocondeController::Export()` (web UI)
 *   - `command-line/run-joconde-export.php`     (CLI for fast tests)
 *
 * The class deliberately knows nothing about HTTP, views or rendering.
 *
 * Field-by-field post-processing (LOCA constants, STAT normalization,
 * DIMS formatting, REF whitespace, DACQ year extraction) lives in this
 * class so any change applies uniformly to both entry points.
 *
 * Format details (text file structure, `//` record separator, BOM,
 * placement of `.txt` inside `media/`) follow the SMF / Isabelle Peretti
 * feedback of 2026-05-11 — see `suivi/2026-05-11 retour SMF…`.
 */

require_once(__CA_MODELS_DIR__ . '/ca_objects.php');
require_once(__CA_MODELS_DIR__ . '/ca_sets.php');
require_once(__CA_MODELS_DIR__ . '/ca_object_representations.php');
require_once(__CA_LIB_DIR__ . '/Configuration.php');
require_once(__CA_LIB_DIR__ . '/Search/ObjectSearch.php');

class JocondeExporter {

    private Configuration $config;
    private string $pluginDir;
    private string $exportRoot;       // /pluginDir/export-joconde
    private string $referentielDir;   // /exportRoot/_referentiel
    private array $headers = [];
    private array $templates = [];

    /** @var callable|null */
    private $logger = null;

    /**
     * @param Configuration $config     The museesDeFrance plugin config
     * @param string $pluginDir         Absolute path to the plugin (no trailing slash)
     */
    public function __construct(Configuration $config, string $pluginDir) {
        $this->config = $config;
        $this->pluginDir = rtrim($pluginDir, '/');
        $this->exportRoot = $this->pluginDir . '/export-joconde';
        $this->referentielDir = $this->exportRoot . '/_referentiel';
    }

    /**
     * Set an optional progress logger. Receives one string per call.
     * Use \fwrite(STDOUT, $msg . PHP_EOL) for CLI, or skip for web.
     */
    public function setLogger(callable $logger): void {
        $this->logger = $logger;
    }

    /**
     * Run the export.
     *
     * @param array{
     *   set_code?: string,
     *   force?: bool,
     *   skip_zip?: bool,
     * } $options
     *   - set_code  set to export, default 'joconde'
     *   - force     bypass the "one export per minute" guard, default false
     *   - skip_zip  do not build the .zip archive (faster CLI tests), default false
     *
     * @return array{
     *   anti_duplicate?: bool,
     *   refexport?: string,
     *   export_dir?: string,
     *   txt_path?: string,
     *   zip_path?: ?string,
     *   object_count?: int,
     *   images_exported?: int,
     *   images_not_exported?: int,
     *   images_without_credits?: int,
     *   notices_without_image?: int,
     * }
     */
    public function run(array $options = []): array {
        $setCode = $options['set_code'] ?? 'joconde';
        $force   = (bool)($options['force']    ?? false);
        $skipZip = (bool)($options['skip_zip'] ?? false);

        $museo = $this->config->get('museo');
        $this->loadTemplates();

        // Anti-duplicate guard: at most one export per minute, identified
        // by `Ymd_Hi`. The CLI may opt out via --force.
        $date = date('Ymd_Hi');
        if (!$force) {
            $lastDate = @file_get_contents($this->referentielDir . '/.dateexport');
            if ($date === $lastDate) {
                $this->log("Anti-duplicate guard hit (same minute as previous export). Use --force to override.");
                return ['anti_duplicate' => true];
            }
        }

        // Increment the export counter
        $numExport = (int)file_get_contents($this->referentielDir . '/.numexport') + 1;
        file_put_contents($this->referentielDir . '/.numexport', $numExport);
        file_put_contents($this->referentielDir . '/.dateexport', $date);

        $refexport = sprintf('J_%s-%04d_%s', $museo, $numExport, $date);
        $exportDir = $this->exportRoot . '/' . $refexport;
        $mediaDir  = $exportDir . '/media';
        $txtPath   = $mediaDir . '/' . $refexport . '.txt';
        $zipPath   = $skipZip ? null : ($this->exportRoot . '/' . $refexport . '.zip');

        $this->log("Export reference : {$refexport}");
        $this->log("Output directory : {$exportDir}");

        @mkdir($exportDir, 0775); @chmod($exportDir, 0775);
        @mkdir($mediaDir, 0775);  @chmod($mediaDir, 0775);

        // Resolve set items
        $set = new ca_sets();
        $set->load(['deleted' => 0, 'set_code' => $setCode]);
        if (!$set->getPrimaryKey()) {
            throw new RuntimeException("Set '{$setCode}' not found.");
        }
        $setItems = array_filter(explode(
            ';',
            $set->getWithTemplate("<unit relativeTo='ca_set_items'>^ca_set_items.row_id</unit>")
        ));
        $this->log("Set '{$setCode}' resolved to " . count($setItems) . ' notice(s).');

        // Iterate over notices
        $stats = [
            'object_count' => 0,
            'images_exported' => 0,
            'images_not_exported' => 0,
            'images_without_credits' => 0,
            'notices_without_image' => 0,
        ];
        $imgTooSmall = [];
        $sansCredit  = [];
        $results     = [];

        foreach ($setItems as $itemId) {
            $stats['object_count']++;
            $vtObject = new ca_objects($itemId);
            [$row, $perObjectStats] = $this->buildNoticeRow($vtObject, $museo, $itemId,
                                                            $refexport, $imgTooSmall, $sansCredit);
            $results[] = $row;
            $stats['images_exported']        += $perObjectStats['image_ok']        ?? 0;
            $stats['images_not_exported']    += $perObjectStats['image_failed']    ?? 0;
            $stats['images_without_credits'] += $perObjectStats['no_credits']      ?? 0;
            $stats['notices_without_image']  += $perObjectStats['no_image']        ?? 0;
        }

        $this->log("Processed {$stats['object_count']} notices, {$stats['images_exported']} image(s) exported.");

        // Write the Joconde text file
        $this->writeJocondeFile($txtPath, $results);
        @chmod($txtPath, 0664);
        $this->log("Wrote Joconde file: {$txtPath}");

        // Write rapport.txt
        $this->writeRapport($exportDir . '/rapport.txt', $refexport, $stats, $imgTooSmall, $sansCredit);

        if (!$skipZip) {
            self::buildZip($exportDir, $zipPath);
            $this->log("Wrote ZIP: {$zipPath}");
        }

        return [
            'anti_duplicate' => false,
            'refexport'      => $refexport,
            'export_dir'     => $exportDir,
            'txt_path'       => $txtPath,
            'zip_path'       => $zipPath,
        ] + $stats;
    }

    // ---------------------------------------------------------------
    // Templates configuration
    // ---------------------------------------------------------------
    private function loadTemplates(): void {
        $local   = $this->pluginDir . '/conf/local/joconde_templates.php';
        $default = $this->pluginDir . '/conf/joconde_templates.php';
        $path = file_exists($local) ? $local : $default;
        $cfg = require $path;
        $this->headers   = $cfg['headers'];
        $this->templates = $cfg['templates'];
    }

    // ---------------------------------------------------------------
    // Per-notice row generation + media handling
    // ---------------------------------------------------------------
    private function buildNoticeRow(
        ca_objects $vtObject, string $museo, string $itemId,
        string $refexport, array &$imgTooSmall, array &$sansCredit
    ): array {
        $perObject = ['image_ok' => 0, 'image_failed' => 0, 'no_image' => 0, 'no_credits' => 0];

        $representationId = $vtObject->getPrimaryRepresentationID();
        $medianame = '';
        if ($representationId) {
            $medianame = $this->processMedia($vtObject, $representationId, $museo, $itemId,
                                             $refexport, $imgTooSmall, $perObject);
        } else {
            $perObject['no_image']++;
        }

        $credits = $vtObject->get('credits_photo');
        if (empty($credits) && $medianame !== '') {
            $perObject['no_credits']++;
            $sansCredit[] = [$medianame, $vtObject->getWithTemplate('^ca_objects.idno'), $museo . $itemId];
        }

        $row = [];
        foreach ($this->templates as $fieldCode => $template) {
            if (is_callable($template)) {
                $row[] = $template($vtObject, $museo, $itemId, $credits, $medianame);
            } else {
                $row[] = $vtObject->getWithTemplate($template);
            }
        }
        return [$row, $perObject];
    }

    /**
     * Copy / resize the primary representation into the export media dir.
     * Returns the media filename (used in REFIM) or '' if the image was
     * dropped (too small, or copy failed).
     */
    private function processMedia(
        ca_objects $vtObject, int $representationId, string $museo, string $itemId,
        string $refexport, array &$imgTooSmall, array &$perObject
    ): string {
        $rep = new ca_object_representations($representationId);
        $vsMediaName = $rep->get('original_filename');
        $vsMediaPath = $rep->getMediaPath('media', 'large');
        $medianame = $museo . '00_' . $representationId . '_1.jpg';
        $mediapath = $this->exportRoot . '/' . $refexport . '/media/' . $medianame;
        $mediaSize = @getimagesize($vsMediaPath);
        if (!$mediaSize) {
            $perObject['image_failed']++;
            $imgTooSmall[] = [$vsMediaName, $vtObject->getWithTemplate('^ca_objects.idno'),
                              $museo . $itemId, 'image illisible'];
            return '';
        }

        if ($mediaSize[0] >= $mediaSize[1]) {
            // Portrait or square (yes, the legacy comment in the controller said the
            // opposite — left as-is so behaviour is preserved across both entry points)
            if ($mediaSize[0] > 1200) {
                $percent = $mediaSize[0] / 1200;
                if (copy($vsMediaPath, $mediapath)) {
                    self::resizeImage($mediapath, $mediaSize[1] * $percent, $mediaSize[0] * $percent);
                    $perObject['image_ok']++;
                } else {
                    $perObject['image_failed']++;
                    return '';
                }
            } elseif ($mediaSize[0] <= 1200 && $mediaSize[0] >= 480) {
                if (!copy($vsMediaPath, $mediapath)) {
                    $perObject['image_failed']++;
                    return '';
                }
                $perObject['image_ok']++;
            } else {
                $imgTooSmall[] = [$vsMediaName, $vtObject->getWithTemplate('^ca_objects.idno'),
                                  $museo . $itemId, 'taille inférieure à 640 x 480 pixels'];
                $perObject['image_failed']++;
                return '';
            }
        } else {
            // Landscape
            if ($mediaSize[1] > 1600) {
                $percent = $mediaSize[1] / 1600;
                if (copy($vsMediaPath, $mediapath)) {
                    self::resizeImage($mediapath, $mediaSize[1] * $percent, $mediaSize[0] * $percent);
                    $resized = @getimagesize($mediapath);
                    if ($resized && $resized[0] > 1200) {
                        $percent = $resized[0] / 1200;
                        self::resizeImage($mediapath, $mediaSize[1] * $percent, $mediaSize[0] * $percent);
                    }
                    $perObject['image_ok']++;
                } else {
                    $perObject['image_failed']++;
                    return '';
                }
            } elseif ($mediaSize[1] <= 1600 && $mediaSize[1] >= 640) {
                if (!copy($vsMediaPath, $mediapath)) {
                    $perObject['image_failed']++;
                    return '';
                }
                $perObject['image_ok']++;
            } else {
                $imgTooSmall[] = [$vsMediaName, $vtObject->getWithTemplate('^ca_objects.idno'),
                                  $museo . $itemId, 'taille inférieure à 640 x 480 pixels'];
                $perObject['image_failed']++;
                return '';
            }
        }
        return $medianame;
    }

    // ---------------------------------------------------------------
    // Joconde text file writing + field post-processing
    // ---------------------------------------------------------------
    private function writeJocondeFile(string $txtPath, array $results): void {
        $fp = fopen($txtPath, 'w');
        // UTF-8 BOM for robust browser detection
        fwrite($fp, "\xEF\xBB\xBF");

        $constantFields = $this->config->get('JocondeConstantFields') ?: [];
        $body = '';
        foreach ($results as $result) {
            foreach ($result as $index => $value) {
                if ($value === '' || $value === null) continue;
                $fieldName = $this->headers[$index];
                $value = $this->postProcessField($fieldName, $value, $constantFields);
                if ($value === '') continue;
                $body .= $fieldName . "\n";
                $body .= $value . "\n";
            }
            // Record separator (no ¶ per SMF request 2026-05-11)
            $body .= "//\n";
        }
        fwrite($fp, $body);
        fclose($fp);
    }

    /**
     * Apply Joconde-specific cleanups for a single (fieldName, value) pair.
     * Returns the cleaned value (may be '' if cleanup nulled it).
     */
    private function postProcessField(string $fieldName, $value, array $constantFields): string {
        // Configuration overrides everything
        if (isset($constantFields[$fieldName])) {
            return (string)$constantFields[$fieldName];
        }

        // 1. Trim
        $value = trim((string)$value);
        // 2. Strip empty parentheses
        $value = preg_replace('/\(\s*\)/', '', $value);
        // 3. Tighten spaces around `;` for most fields
        $loose = ['PREP', 'PAUT', 'COMM', 'EXPO'];
        if (!in_array($fieldName, $loose, true)) {
            $value = preg_replace('/\s*;\s*/', ';', $value);
        }
        // 4. Final trim
        $value = trim($value);

        // 5. DACQ: keep only the year
        if ($fieldName === 'DACQ' && $value !== '') {
            if (preg_match('/\b(1[0-9]{3}|20[0-9]{2})\b/', $value, $m)) {
                $value = $m[1];
            }
        }

        // 6. REF: strip every whitespace
        if ($fieldName === 'REF' && $value !== '') {
            $value = preg_replace('/\s+/u', '', $value);
        }

        // 7. STAT: SMF-required form "Propriété de la commune, don manuel, Mayenne, musée du château"
        //    Comma separator; first part keeps its leading capital.
        if ($fieldName === 'STAT' && $value !== '') {
            $parts = array_map('trim', explode(';', $value));
            foreach ($parts as $i => $p) {
                switch ($i) {
                    case 1: // mode d'acquisition: "Don" → "don manuel", others lowercased
                        $parts[$i] = ($p === 'Don') ? 'don manuel' : mb_strtolower($p, 'UTF-8');
                        break;
                    case 2: // propriétaire: "Ville de XXX" / "Commune de XXX" → titlecase XXX
                        if (preg_match('/^(?:Ville|Commune) de\s+(.+)$/iu', $p, $m)) {
                            $parts[$i] = mb_convert_case(mb_strtolower($m[1], 'UTF-8'),
                                                         MB_CASE_TITLE, 'UTF-8');
                        }
                        break;
                    case 3: // établissement affectataire: known museum lowercased
                        if (strcasecmp($p, 'Musée du Château de Mayenne') === 0) {
                            $parts[$i] = 'musée du château';
                        }
                        break;
                }
            }
            $parts = array_filter($parts, fn($p) => $p !== '');
            $value = implode(', ', $parts);
        }

        // 8. DIMS: remove weight, drop "cm", comma→dot decimal, etc.
        if ($fieldName === 'DIMS' && $value !== '') {
            $value = preg_replace('/P\.\s*[0-9,\.]+\s*cm\s*;?\s*/i', '', $value);
            $value = str_replace(' cm', '', $value);
            $value = preg_replace('/(\d+),(\d+)/', '$1.$2', $value);
            $value = preg_replace('/\bl\.\s*/i', 'L. ', $value);
            $value = rtrim($value, '; ');
            $value = preg_replace('/\s+/', ' ', $value);
            $value = preg_replace('/\s*;\s*/', ', ', $value);
        }

        return (string)$value;
    }

    // ---------------------------------------------------------------
    // Rapport.txt
    // ---------------------------------------------------------------
    private function writeRapport(
        string $rapportPath, string $refexport, array $stats,
        array $imgTooSmall, array $sansCredit
    ): void {
        $today = date('j/m/Y');
        $rap  = $this->config->get('NomMusee') . ', '
              . $this->config->get('Commune') . ', '
              . $this->config->get('museo') . "\n";
        $rap .= "Date de l'export : {$today}\n";
        $rap .= "{$refexport}\n";
        $rap .= "{$stats['object_count']} notices exportées/{$stats['object_count']} notices sélectionnées\n\n";
        $rap .= "{$stats['images_not_exported']} image(s) non exportée(s)\n";
        $rap .= "NOMIMAGE|NUMINV|REF|RAISON \n";
        foreach ($imgTooSmall as $img) $rap .= implode('|', $img) . "\n";
        $rap .= "\n";
        $rap .= "{$stats['images_without_credits']} images sans crédits\n";
        $rap .= "NOMIMAGE|NUMINV|REF\n";
        foreach ($sansCredit as $img) $rap .= implode('|', $img) . "\n";
        $rap .= "\n";
        file_put_contents($rapportPath, $rap);
        @chmod($rapportPath, 0664);
    }

    // ---------------------------------------------------------------
    // Helpers (formerly free functions in JocondeController.php)
    // ---------------------------------------------------------------
    public static function buildZip(string $source, string $destination): bool {
        if (!extension_loaded('zip') || !file_exists($source)) return false;
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) return false;
        $source = str_replace('\\', '/', realpath($source));
        if (is_dir($source)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..'], true)) continue;
                $file = realpath($file);
                if (is_dir($file)) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } elseif (is_file($file)) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } elseif (is_file($source)) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
        return $zip->close();
    }

    public static function resizeImage(string $file, $w, $h, bool $crop = false) {
        [$width, $height] = getimagesize($file);
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil($width - ($width * abs($r - $w / $h)));
            } else {
                $height = ceil($height - ($height * abs($r - $w / $h)));
            }
            $newwidth = $w;
            $newheight = $h;
        } else {
            if ($w / $h > $r) {
                $newwidth = $h * $r;
                $newheight = $h;
            } else {
                $newheight = $w / $r;
                $newwidth = $w;
            }
        }
        $src = imagecreatefromjpeg($file);
        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        return $dst;
    }

    private function log(string $message): void {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}
