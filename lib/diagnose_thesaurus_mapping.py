#!/usr/bin/env python3
"""
diagnose_thesaurus_mapping.py
=============================

Read-only diagnostic comparing an existing CollectiveAccess list (typically
`dmf_lexdomn`, hosting the legacy SMF 2014 lexicon for the `domaine` field)
to a live OpenTheso thesaurus (typically `th294`, the canonical Joconde
domain thesaurus).

Produces a structured report:
- exact / normalized label matches
- altLabel matches (SKOS:altLabel from OpenTheso)
- dmf items WITHOUT match (with object-usage count to gauge migration impact)
- th294 items absent from the dmf list (new entries to be offered)

The script DOES NOT WRITE TO THE DATABASE. It is intended to be run before
any migration to size the work and identify the items requiring manual
arbitration with the museum cataloguers.

Companion script: lib/get_or_update_all_thesaurus.php
  Populates the th294 list (and other SMF thesauri) from OpenTheso.
  Must be run AFTER this diagnostic and BEFORE any data migration.

Usage
-----
    python3 diagnose_thesaurus_mapping.py \
        --setup-path /var/www/.../providence/setup.php \
        [--source-list-code dmf_lexdomn] \
        [--theso-id th294] \
        [--element-code domaine] \
        [--json-output report.json]

Defaults match the SMF 'domaine' field (dmf_lexdomn → th294).

The DB credentials are read from the CollectiveAccess setup.php so the
script can be run from any deployed instance without further config.
"""

import argparse
import json
import os
import re
import subprocess
import sys
import unicodedata
import urllib.request
from collections import defaultdict


# ---------------------------------------------------------------------------
# Utilities
# ---------------------------------------------------------------------------
def normalize(s):
    """Case-fold + strip diacritics + collapse dashes/quotes/spaces."""
    if s is None:
        return ""
    s = unicodedata.normalize("NFD", s)
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    s = s.lower().strip()
    # Replace any dash variant and any apostrophe / single-quote variant
    # (ASCII U+0027, typographic U+2018, U+2019) with a single space.
    s = re.sub(r"[\-–—'‘’]", " ", s)
    s = " ".join(s.split())
    return s


def read_setup_constants(setup_path):
    """Extract __CA_DB_* values from a CollectiveAccess setup.php file."""
    with open(setup_path, encoding="utf-8") as fh:
        content = fh.read()
    pattern = re.compile(
        r"define\(\s*\"(__CA_DB_(?:HOST|USER|PASSWORD|DATABASE)__)\"\s*,\s*'([^']*)'\s*\)"
    )
    constants = dict(pattern.findall(content))
    required = {
        "__CA_DB_HOST__",
        "__CA_DB_USER__",
        "__CA_DB_PASSWORD__",
        "__CA_DB_DATABASE__",
    }
    missing = required - set(constants)
    if missing:
        sys.exit(f"Missing required DB constants in {setup_path}: {sorted(missing)}")
    return {
        "host": constants["__CA_DB_HOST__"],
        "user": constants["__CA_DB_USER__"],
        "password": constants["__CA_DB_PASSWORD__"],
        "database": constants["__CA_DB_DATABASE__"],
    }


def mysql_query(creds, sql):
    """Run a SELECT and return a list of dicts."""
    proc = subprocess.run(
        [
            "mysql",
            "-h", creds["host"],
            "-u", creds["user"],
            f"-p{creds['password']}",
            creds["database"],
            "-B",
            "-e", sql,
        ],
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        sys.exit(f"MySQL error: {proc.stderr}")
    lines = [l for l in proc.stdout.splitlines() if l]
    if not lines:
        return []
    header = lines[0].split("\t")
    rows = []
    for line in lines[1:]:
        parts = line.split("\t")
        rows.append(dict(zip(header, parts)))
    return rows


# ---------------------------------------------------------------------------
# OpenTheso JSON parsing
# ---------------------------------------------------------------------------
PREFLABEL = "http://www.w3.org/2004/02/skos/core#prefLabel"
ALTLABEL = "http://www.w3.org/2004/02/skos/core#altLabel"
BROADER = "http://www.w3.org/2004/02/skos/core#broader"
TYPE_RDF = "http://www.w3.org/1999/02/22-rdf-syntax-ns#type"
CONCEPT = "http://www.w3.org/2004/02/skos/core#Concept"
IDENTIFIER = "http://purl.org/dc/terms/identifier"


def fetch_theso(theso_id):
    url = f"https://opentheso.huma-num.fr/opentheso/api/all/theso?id={theso_id}&format=json"
    with urllib.request.urlopen(url) as response:
        return json.loads(response.read().decode("utf-8"))


def parse_theso(raw):
    concepts = []
    for uri, node in raw.items():
        types = [t.get("value") for t in node.get(TYPE_RDF, [])]
        if CONCEPT not in types:
            continue
        label = None
        if PREFLABEL in node:
            for entry in node[PREFLABEL]:
                if entry.get("lang") == "fr":
                    label = entry["value"]
                    break
            if label is None and node[PREFLABEL]:
                label = node[PREFLABEL][0]["value"]
        alts = []
        if ALTLABEL in node:
            for entry in node[ALTLABEL]:
                if entry.get("lang") == "fr":
                    alts.append(entry["value"])
        identifier = None
        if IDENTIFIER in node:
            identifier = node[IDENTIFIER][0]["value"]
        is_root = not bool(node.get(BROADER))
        concepts.append({
            "id": identifier,
            "label": label,
            "alts": alts,
            "is_root": is_root,
        })
    return concepts


# ---------------------------------------------------------------------------
# Diagnostic
# ---------------------------------------------------------------------------
def load_source_list(creds, list_code, element_code):
    list_rows = mysql_query(creds, f"SELECT list_id FROM ca_lists WHERE list_code = '{list_code}'")
    if not list_rows:
        sys.exit(f"List '{list_code}' not found in ca_lists.")
    list_id = list_rows[0]["list_id"]

    elem_rows = mysql_query(creds, f"SELECT element_id FROM ca_metadata_elements WHERE element_code = '{element_code}'")
    if not elem_rows:
        sys.exit(f"Element '{element_code}' not found in ca_metadata_elements.")
    element_id = elem_rows[0]["element_id"]

    # Restrict to the preferred label per item — legacy lists may carry
    # alt-label rows (e.g. `mobilier` as alt of `ameublement`) which would
    # double-count items and inflate usage totals.
    rows = mysql_query(creds, f"""
        SELECT li.item_id, li.idno, lil.name_singular AS label,
               (SELECT COUNT(*) FROM ca_attribute_values av
                WHERE av.element_id = {element_id} AND av.item_id = li.item_id) AS n_objects
        FROM ca_list_items li
        LEFT JOIN ca_list_item_labels lil
               ON lil.item_id = li.item_id AND lil.is_preferred = 1
        WHERE li.list_id = {list_id} AND li.deleted = 0
        ORDER BY lil.name_singular
    """)
    # filter the synthetic root node
    return [r for r in rows if r.get("label") and not r["label"].startswith("Root node")]


def diagnose(source_items, theso_concepts):
    # Index theso by normalized prefLabel and by normalized altLabel.
    # A concept with several altLabels normalizing to the same key must
    # only appear once in by_alt[key] — otherwise it gets reported as
    # ambiguous against itself.
    by_pref = defaultdict(list)
    by_alt_sets = defaultdict(dict)
    for c in theso_concepts:
        if c["label"]:
            by_pref[normalize(c["label"])].append(c)
        for alt in c["alts"]:
            by_alt_sets[normalize(alt)][id(c)] = c
    by_alt = {k: list(v.values()) for k, v in by_alt_sets.items()}

    exact = []
    normalized = []
    alt_match = []
    no_match = []
    ambiguous = []

    for it in source_items:
        nl = normalize(it["label"])
        pref_hits = by_pref.get(nl, [])
        if pref_hits:
            if any(c["label"] == it["label"] for c in pref_hits):
                target = next(c for c in pref_hits if c["label"] == it["label"])
                exact.append({"source": it, "target": target})
            else:
                normalized.append({"source": it, "target": pref_hits[0],
                                   "all_targets": pref_hits})
            if len(pref_hits) > 1:
                ambiguous.append({"source": it, "candidates": pref_hits, "via": "prefLabel"})
            continue
        alt_hits = by_alt.get(nl, [])
        if alt_hits:
            alt_match.append({"source": it, "target": alt_hits[0],
                              "all_targets": alt_hits})
            if len(alt_hits) > 1:
                ambiguous.append({"source": it, "candidates": alt_hits, "via": "altLabel"})
            continue
        no_match.append(it)

    # th294 entries not present in source (informational only)
    source_norms = {normalize(it["label"]) for it in source_items}
    new_in_theso = [c for c in theso_concepts
                    if normalize(c["label"] or "") not in source_norms]

    return {
        "exact": exact,
        "normalized": normalized,
        "alt_match": alt_match,
        "no_match": no_match,
        "ambiguous": ambiguous,
        "new_in_theso": new_in_theso,
    }


# ---------------------------------------------------------------------------
# Reporting
# ---------------------------------------------------------------------------
def print_report(source_items, theso_concepts, result):
    n_total = len(source_items)
    print(f"=== Source list ===")
    print(f"  Items (hors racine) : {n_total}")
    total_usage = sum(int(it.get("n_objects", 0) or 0) for it in source_items)
    print(f"  Total occurrences attribuées : {total_usage}")

    print(f"\n=== OpenTheso ===")
    print(f"  Concepts : {len(theso_concepts)}")
    roots = [c for c in theso_concepts if c["is_root"]]
    print(f"  Racines  : {len(roots)}")

    print(f"\n=== Bilan correspondance ===")
    print(f"  Match exact (prefLabel strict)        : {len(result['exact'])}")
    print(f"  Match après normalisation             : {len(result['normalized'])}")
    print(f"  Match via altLabel                    : {len(result['alt_match'])}")
    print(f"  Sans correspondance                   : {len(result['no_match'])}")
    print(f"  Cibles multiples (à arbitrer)         : {len(result['ambiguous'])}")
    print(f"  Concepts nouveaux dans th294          : {len(result['new_in_theso'])}")

    if result["no_match"]:
        print(f"\n=== Items source SANS correspondance ===")
        for it in sorted(result["no_match"], key=lambda x: -int(x.get("n_objects", 0) or 0)):
            print(f"  [{it.get('n_objects', 0):>5} obj] [{it['idno']}] {it['label']}")

    if result["ambiguous"]:
        print(f"\n=== Cibles multiples (arbitrage requis) ===")
        for entry in sorted(result["ambiguous"],
                            key=lambda e: -int(e["source"].get("n_objects", 0) or 0)):
            it = entry["source"]
            print(f"  [{it.get('n_objects', 0):>5} obj] [{it['idno']}] {it['label']}  (via {entry['via']})")
            for c in entry["candidates"]:
                print(f"      → {c['id']}  {c['label']}")

    if result["normalized"] or result["alt_match"]:
        print(f"\n=== Match approximatif (à valider) ===")
        for label, entries in [("normalisation", result["normalized"]),
                                ("altLabel", result["alt_match"])]:
            if not entries:
                continue
            print(f"  -- via {label} --")
            for entry in sorted(entries,
                                key=lambda e: -int(e["source"].get("n_objects", 0) or 0)):
                it = entry["source"]
                t = entry["target"]
                print(f"    [{it.get('n_objects', 0):>5} obj] [{it['idno']}] {it['label']!r}  →  [{t['id']}] {t['label']!r}")


def to_json_safe(result):
    """Reduce result to JSON-serializable dicts (drop Pythonic objects)."""
    def trim_concept(c):
        return {"id": c["id"], "label": c["label"], "alts": c["alts"]}

    return {
        "exact": [{"source": e["source"], "target": trim_concept(e["target"])}
                  for e in result["exact"]],
        "normalized": [{"source": e["source"], "target": trim_concept(e["target"]),
                        "all_targets": [trim_concept(c) for c in e["all_targets"]]}
                       for e in result["normalized"]],
        "alt_match": [{"source": e["source"], "target": trim_concept(e["target"]),
                       "all_targets": [trim_concept(c) for c in e["all_targets"]]}
                      for e in result["alt_match"]],
        "no_match": result["no_match"],
        "ambiguous": [{"source": e["source"], "via": e["via"],
                       "candidates": [trim_concept(c) for c in e["candidates"]]}
                      for e in result["ambiguous"]],
        "new_in_theso": [trim_concept(c) for c in result["new_in_theso"]],
    }


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--setup-path", required=True,
                        help="Path to the CollectiveAccess setup.php (for DB credentials)")
    parser.add_argument("--source-list-code", default="dmf_lexdomn",
                        help="ca_lists.list_code of the source list (default: dmf_lexdomn)")
    parser.add_argument("--theso-id", default="th294",
                        help="OpenTheso thesaurus id (default: th294)")
    parser.add_argument("--element-code", default="domaine",
                        help="ca_metadata_elements.element_code of the field using the list "
                             "(default: domaine) — used to count object usage per item")
    parser.add_argument("--json-output",
                        help="If given, write a JSON report to this path")
    args = parser.parse_args()

    creds = read_setup_constants(args.setup_path)
    print(f"DB connection: {creds['user']}@{creds['host']}/{creds['database']}")
    print(f"Source list:   {args.source_list_code}  (counted via element {args.element_code})")
    print(f"OpenTheso id:  {args.theso_id}")
    print()

    source_items = load_source_list(creds, args.source_list_code, args.element_code)
    raw_theso = fetch_theso(args.theso_id)
    theso = parse_theso(raw_theso)

    result = diagnose(source_items, theso)
    print_report(source_items, theso, result)

    if args.json_output:
        with open(args.json_output, "w", encoding="utf-8") as fh:
            json.dump(to_json_safe(result), fh, ensure_ascii=False, indent=2)
        print(f"\nJSON report written to {args.json_output}")


if __name__ == "__main__":
    main()
