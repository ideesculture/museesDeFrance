<?php
/**
 * build_thesaurus_json.php — SMF thesaurus flat-store builder
 * ------------------------------------------------------------
 * Downloads a SKOS/JSON thesaurus from Opentheso (huma-num) and produces a
 * normalized, deterministic flat JSON store used by the SMFThesaurus
 * InformationService plugin.
 *
 * This deliberately does NOT load anything into ca_list_items — the whole point
 * is to keep the SMF thesauri out of the CollectiveAccess search index.
 *
 * Usage:
 *   php build_thesaurus_json.php --id th294 [--out /path/to/th294.json]
 *
 * Standalone CLI: does NOT bootstrap CollectiveAccess.
 *
 * @package museesDeFrance
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

// ---------------------------------------------------------------------------
// SKOS / RDF property URIs
// ---------------------------------------------------------------------------
const RDF_TYPE       = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
const SKOS_CONCEPT   = 'http://www.w3.org/2004/02/skos/core#Concept';
const DC_IDENTIFIER  = 'http://purl.org/dc/terms/identifier';
const SKOS_PREFLABEL = 'http://www.w3.org/2004/02/skos/core#prefLabel';
const SKOS_ALTLABEL  = 'http://www.w3.org/2004/02/skos/core#altLabel';
const SKOS_BROADER   = 'http://www.w3.org/2004/02/skos/core#broader';
const SKOS_NARROWER  = 'http://www.w3.org/2004/02/skos/core#narrower';

const OPENTHESO_TPL  = 'https://opentheso.huma-num.fr/opentheso/api/all/theso?id=%s&format=json';

// ---------------------------------------------------------------------------
// Argument parsing
// ---------------------------------------------------------------------------
function smf_parse_args(array $argv): array {
	$out = array('id' => null, 'out' => null);
	for ($i = 1; $i < count($argv); $i++) {
		$a = $argv[$i];
		if ($a === '--id' && isset($argv[$i + 1]))       { $out['id']  = $argv[++$i]; }
		elseif ($a === '--out' && isset($argv[$i + 1]))  { $out['out'] = $argv[++$i]; }
		elseif (strpos($a, '--id=') === 0)               { $out['id']  = substr($a, 5); }
		elseif (strpos($a, '--out=') === 0)              { $out['out'] = substr($a, 6); }
	}
	return $out;
}

function smf_die(string $msg, int $code = 1): void {
	fwrite(STDERR, $msg . "\n");
	exit($code);
}

// ---------------------------------------------------------------------------
// HTTP fetch (follows 301 redirects; anti-SSRF handled upstream by id regex)
// ---------------------------------------------------------------------------
function smf_fetch_url(string $url, int $max_attempts = 4): string {
	if (!function_exists('curl_init')) {
		smf_die('cURL extension is required but not available.');
	}

	$last_err = '';
	for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,   // follow the 301 redirect
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT        => 60,
			// NB: do NOT send an "Accept: application/json" header — the Opentheso
			// endpoint returns HTTP 500 on content-negotiation for that header.
			// The format is already selected via the &format=json query parameter.
			CURLOPT_USERAGENT      => 'museesDeFrance-thesaurus-builder/1.0',
		));
		$body = curl_exec($ch);
		$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$cerr = curl_error($ch);
		curl_close($ch);

		if ($body !== false && $http >= 200 && $http < 300) {
			return (string) $body;
		}

		// The Opentheso endpoint intermittently returns transient 5xx errors;
		// retry a few times with a short backoff before giving up.
		$last_err = $body === false ? ('network error: ' . $cerr) : ("HTTP status {$http}");
		if ($attempt < $max_attempts) {
			fwrite(STDERR, "  attempt {$attempt} failed ({$last_err}); retrying...\n");
			sleep($attempt);   // linear backoff: 1s, 2s, 3s
		}
	}

	smf_die("Failed to fetch thesaurus after {$max_attempts} attempts ({$last_err}).");
}

// ---------------------------------------------------------------------------
// SKOS helpers
// ---------------------------------------------------------------------------

/**
 * Extract the first literal value for a property, preferring lang=fr.
 */
function smf_pref_value($entries, string $prefer_lang = 'fr'): ?string {
	if (!is_array($entries)) { return null; }
	$fallback = null;
	foreach ($entries as $e) {
		if (!isset($e['value'])) { continue; }
		$v = (string) $e['value'];
		if (isset($e['lang']) && $e['lang'] === $prefer_lang) { return $v; }
		if ($fallback === null) { $fallback = $v; }
	}
	return $fallback;
}

/**
 * Extract all values for a property as a de-duplicated, sorted list.
 * Used for both literal values (e.g. altLabels) and URI-typed values
 * (e.g. broader/narrower) — the extraction is identical in both cases.
 */
function smf_all_values($entries): array {
	$out = array();
	if (is_array($entries)) {
		foreach ($entries as $e) {
			if (isset($e['value']) && $e['value'] !== '') {
				$out[(string) $e['value']] = true;
			}
		}
	}
	$out = array_keys($out);
	sort($out, SORT_STRING);
	return $out;
}

function smf_is_concept(array $node): bool {
	if (!isset($node[RDF_TYPE]) || !is_array($node[RDF_TYPE])) { return false; }
	foreach ($node[RDF_TYPE] as $t) {
		if (isset($t['value']) && $t['value'] === SKOS_CONCEPT) { return true; }
	}
	return false;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
$args = smf_parse_args($argv);

$id = $args['id'];
if ($id === null || !preg_match('/^th[0-9]+$/', $id)) {
	smf_die("Invalid or missing --id. Expected pattern ^th[0-9]+\$ (e.g. --id th294).");
}

$source_url = sprintf(OPENTHESO_TPL, $id);

$default_out = dirname(__DIR__) . '/assets/thesauri/' . $id . '.json';
$out_path = $args['out'] !== null ? $args['out'] : $default_out;

fwrite(STDERR, "Fetching {$source_url} ...\n");
$raw = smf_fetch_url($source_url);

$data = json_decode($raw, true);
if (!is_array($data)) {
	smf_die('Failed to decode JSON from Opentheso (invalid response).');
}

// ---------------------------------------------------------------------------
// Parse concepts
// ---------------------------------------------------------------------------
$concepts = array();
foreach ($data as $uri => $node) {
	if (!is_array($node) || !smf_is_concept($node)) { continue; }   // skip ConceptScheme etc.

	$prefLabel = smf_pref_value($node[SKOS_PREFLABEL] ?? null, 'fr');
	$identifier = smf_pref_value($node[DC_IDENTIFIER] ?? null, 'fr');

	$concepts[(string) $uri] = array(
		'uri'        => (string) $uri,
		'id'         => $identifier !== null ? $identifier : '',
		'prefLabel'  => $prefLabel !== null ? $prefLabel : '',
		'altLabels'  => smf_all_values($node[SKOS_ALTLABEL] ?? null),
		'broader'    => smf_all_values($node[SKOS_BROADER] ?? null),
		'narrower'   => smf_all_values($node[SKOS_NARROWER] ?? null),
	);
}

if (!count($concepts)) {
	smf_die('No skos:Concept nodes found — nothing to write.');
}

// Deterministic ordering: sort concepts map by URI key.
ksort($concepts, SORT_STRING);

// roots = concepts with no broader
$roots = array();
foreach ($concepts as $uri => $c) {
	if (!count($c['broader'])) { $roots[] = $uri; }
}
sort($roots, SORT_STRING);

// ---------------------------------------------------------------------------
// Assemble store (NO timestamp -> reproducible, git-clean diffs)
// ---------------------------------------------------------------------------
$store = array(
	'meta' => array(
		'id'            => $id,
		'source_url'    => $source_url,
		'concept_count' => count($concepts),
	),
	'concepts' => $concepts,
	'roots'    => $roots,
);

$dir = dirname($out_path);
if (!is_dir($dir)) {
	if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
		smf_die("Could not create output directory: {$dir}");
	}
}

$json = json_encode(
	$store,
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if ($json === false) {
	smf_die('Failed to encode output JSON: ' . json_last_error_msg());
}

if (@file_put_contents($out_path, $json . "\n") === false) {
	smf_die("Could not write output file: {$out_path}");
}

fwrite(STDERR, sprintf(
	"Wrote %d concepts (%d roots) to %s\n",
	count($concepts), count($roots), $out_path
));
exit(0);
