<?php
/**
 * install_smf_thesaurus.php
 *
 * Étapes « glue » d'installation, par INSTANCE, pour que les thésaurus SMF
 * servis en InformationService (profil Joconde v4) fonctionnent — celles que
 * le chargement du profil XML ne réalise PAS tout seul :
 *
 *   1. Symlink du plugin InformationService dans app/lib/Plugins/InformationService/
 *      (CA découvre les services IS depuis ce dossier fixe ; la source vit dans le
 *      plugin museesDeFrance).
 *   2. Entrées de traduction dans app/conf/translations.conf (corrige "Plus @rsaquo;").
 *   3. Contrôles de prérequis (stores JSON présents, redis joignable, gzip/mod_deflate),
 *      en avertissement — non bloquants.
 *
 * Idempotent : peut être relancé sans effet de bord.
 *
 * Usage :
 *   php install_smf_thesaurus.php --base-dir /var/www/.../providence [--apply]
 *   (sans --apply : DRY-RUN, montre ce qui serait fait sans rien écrire)
 *
 * @package museesDeFrance
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$opts     = getopt('', ['base-dir:', 'apply']);
$apply    = isset($opts['apply']);
$base_dir = isset($opts['base-dir']) ? rtrim(trim((string)$opts['base-dir']), '/') : '';

function out(string $s): void { fwrite(STDOUT, $s . "\n"); }
function ok(string $s): void  { out("  [OK]   " . $s); }
function act(string $s): void { out("  [FAIT] " . $s); }
function warn(string $s): void{ out("  [WARN] " . $s); }
function abort(string $s): void { fwrite(STDERR, "ABORT: " . $s . "\n"); exit(1); }

if ($base_dir === '')          { abort("--base-dir <chemin> requis (racine de l'install Providence)."); }
if (!is_file($base_dir . '/setup.php')) { abort("setup.php introuvable sous --base-dir : $base_dir"); }

$plugin_dir = $base_dir . '/app/plugins/museesDeFrance';
if (!is_dir($plugin_dir)) { abort("plugin museesDeFrance absent : $plugin_dir (déployer le plugin d'abord)."); }

$mode = $apply ? 'APPLY' : 'DRY-RUN';
out("=====================================================================");
out(" install_smf_thesaurus.php   [$mode]   base-dir=$base_dir");
out("=====================================================================");

# ----------------------------------------------------------------------
# 1. Symlink du plugin InformationService
# ----------------------------------------------------------------------
out("\n[1] Plugin InformationService (symlink)");
$is_dir  = $base_dir . '/app/lib/Plugins/InformationService';
$link    = $is_dir . '/SMFThesaurus.php';
$target  = '../../../plugins/museesDeFrance/lib/InformationService/SMFThesaurus.php';
$src_abs = $plugin_dir . '/lib/InformationService/SMFThesaurus.php';

if (!is_file($src_abs)) {
	warn("source du plugin IS absente : $src_abs — le plugin museesDeFrance est-il à jour (v4) ?");
} elseif (is_link($link) || file_exists($link)) {
	$cur = @readlink($link);
	if ($cur === $target || (is_file($link) && realpath($link) === realpath($src_abs))) {
		ok("symlink déjà en place : $link");
	} else {
		warn("un fichier/lien existe déjà à $link (cible '$cur') — vérifier manuellement.");
	}
} else {
	if ($apply) {
		if (@symlink($target, $link)) { act("symlink créé : $link -> $target"); }
		else { warn("échec de création du symlink $link (permissions ?)"); }
	} else {
		act("(dry-run) créerait : ln -s $target $link");
	}
}

# ----------------------------------------------------------------------
# 2. Traductions (translations.conf : strings)
# ----------------------------------------------------------------------
out("\n[2] Traductions (translations.conf)");
$conf = $base_dir . '/app/conf/translations.conf';
$needles = [
	'More &rsaquo;' => "Plus d'informations",
	'Less &rsaquo;' => "Moins d'informations",
];
if (!is_file($conf)) {
	warn("translations.conf absent : $conf");
} else {
	$txt = file_get_contents($conf);
	$to_add = [];
	foreach ($needles as $k => $v) {
		// présent si une ligne "  <k> = <v>" existe déjà dans le bloc strings
		if (strpos($txt, $k . ' = ' . $v) === false) { $to_add[$k] = $v; }
	}
	if (!$to_add) {
		ok("entrées de traduction déjà présentes.");
	} elseif (!preg_match('/\bstrings\s*=\s*\{/', $txt)) {
		warn("bloc 'strings = {' introuvable dans translations.conf — ajout manuel requis : " . json_encode($to_add, JSON_UNESCAPED_UNICODE));
	} else {
		$ins = '';
		foreach ($to_add as $k => $v) { $ins .= "\t" . $k . ' = ' . $v . ",\n"; }
		if ($apply) {
			$new = preg_replace('/(\bstrings\s*=\s*\{\s*\n)/', '$1' . $ins, $txt, 1);
			if ($new !== null && $new !== $txt && file_put_contents($conf, $new) !== false) {
				act("ajouté au bloc strings : " . implode(', ', array_keys($to_add)));
			} else {
				warn("échec d'écriture de translations.conf.");
			}
		} else {
			act("(dry-run) ajouterait au bloc strings : " . implode(', ', array_keys($to_add)));
		}
	}
}

# ----------------------------------------------------------------------
# 3. Prérequis (contrôles non bloquants)
# ----------------------------------------------------------------------
out("\n[3] Prérequis");

// 3a. Stores JSON présents pour les thésaurus référencés par le profil v4
$profile = $plugin_dir . '/assets/profile/profil_joconde_v4.xml';
$store_dir = $plugin_dir . '/assets/thesauri';
if (is_file($profile)) {
	$xml = file_get_contents($profile);
	preg_match_all('/<setting name="thesaurus">\s*(th\d+)\s*<\/setting>/', $xml, $m);
	$needed = array_values(array_unique($m[1] ?? []));
	$missing = [];
	foreach ($needed as $th) { if (!is_file($store_dir . '/' . $th . '.json')) { $missing[] = $th; } }
	if (!$needed) { warn("aucun thésaurus IS trouvé dans le profil v4 (inattendu)."); }
	elseif (!$missing) { ok(count($needed) . " store(s) JSON présent(s) pour les thésaurus du profil."); }
	else { warn("stores manquants : " . implode(', ', $missing) . " (les builder : build_thesaurus_json.php --id thNNN)."); }
} else {
	warn("profil v4 introuvable : $profile");
}

// 3b. Cache serveur de l'index compact (autocomplétion NATIVE des gros thésaurus).
//     Le widget arborescent est 100% client et n'en dépend pas. N'importe quel backend
//     CA convient ; le défaut 'file' suffit. redis = optionnel (plus rapide en forte concurrence).
require_once($base_dir . '/setup.php');
$backend = defined('__CA_CACHE_BACKEND__') ? __CA_CACHE_BACKEND__ : 'file';
if (!class_exists('ExternalCache')) {
	warn("cache CA (ExternalCache) indisponible — l'autocomplétion native d'un gros thésaurus reparsera le JSON (fonctionnel, plus lent).");
} elseif ($backend === 'redis') {
	$h = defined('__CA_REDIS_HOST__') ? __CA_REDIS_HOST__ : 'localhost';
	$p = defined('__CA_REDIS_PORT__') ? (int)__CA_REDIS_PORT__ : 6379;
	if (class_exists('Redis')) {
		try { $r = new Redis(); $r->connect($h, $p, 1.0); $r->ping(); ok("cache serveur = redis ($h:$p) — optimal."); $r->close(); }
		catch (Throwable $e) { warn("backend 'redis' configuré mais injoignable ($h:$p) — vérifier le service. " . $e->getMessage()); }
	} else { warn("backend 'redis' configuré mais extension PHP redis absente."); }
} else {
	ok("cache serveur = '$backend' — suffisant (redis optionnel ; ce cache ne sert qu'à l'autocomplétion native des gros thésaurus).");
}

// 3c. gzip / mod_deflate sur application/json (perf du 1er téléchargement client)
$deflate = false;
foreach (['/etc/apache2/mods-enabled/deflate.load', '/etc/apache2/conf-enabled/deflate-json.conf'] as $f) {
	if (is_file($f)) { $deflate = true; }
}
if ($deflate) { ok("mod_deflate présent (vérifier qu'application/json est bien compressé)."); }
else { warn("mod_deflate/json non détecté : activer la compression gzip pour application/json (1er DL des stores JSON, ex. th285 17 Mo -> ~0,9 Mo)."); }

out("\n=====================================================================");
out($apply ? " Terminé. Relancer le cas échéant (idempotent)." : " DRY-RUN terminé. Relancer avec --apply pour appliquer [1] et [2].");
out("=====================================================================");
