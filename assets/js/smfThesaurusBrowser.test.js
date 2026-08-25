/**
 * smfThesaurusBrowser.test.js
 * Headless (Node, no browser) tests for the PURE LOGIC of the thesaurus
 * browser: index build, accent-insensitive filter, path resolution and
 * output-value construction.
 *
 * Run:  node smfThesaurusBrowser.test.js
 *
 * Uses the real th294.json store shipped under
 * museesDeFrance/assets/thesauri/th294.json.
 */
'use strict';

var fs = require('fs');
var path = require('path');
var assert = require('assert');

var SMF = require('./smfThesaurusBrowser.js');

var STORE_PATH = path.join(__dirname, '..', 'thesauri', 'th294.json');

var passed = 0, failed = 0;
function test(name, fn) {
    try { fn(); passed++; console.log('  ok   - ' + name); }
    catch (e) { failed++; console.log('  FAIL - ' + name + '\n         ' + e.message); }
}

console.log('Loading store: ' + STORE_PATH);
var store = JSON.parse(fs.readFileSync(STORE_PATH, 'utf8'));
var index = SMF.buildIndex(store);

console.log('\n== Index construction ==');

test('meta concept_count matches concepts size', function () {
    assert.strictEqual(Object.keys(index.concepts).length, store.meta.concept_count);
});

test('roots are all valid concepts and match declared roots', function () {
    assert.strictEqual(index.roots.length, store.roots.length,
        'root count ' + index.roots.length + ' != declared ' + store.roots.length);
    index.roots.forEach(function (u) { assert.ok(index.concepts[u], 'root not a concept: ' + u); });
});

test('no orphans (every non-root has a valid parent)', function () {
    assert.strictEqual(index.orphans.length, 0,
        'orphans: ' + index.orphans.map(function (u) { return index.concepts[u].prefLabel; }).join(', '));
});

test('parent/children coherence: child of P has P as parent', function () {
    Object.keys(index.childrenOf).forEach(function (p) {
        index.childrenOf[p].forEach(function (ch) {
            assert.strictEqual(index.parentOf[ch], p,
                'child ' + index.concepts[ch].prefLabel + ' parentOf != ' + index.concepts[p].prefLabel);
        });
    });
});

test('every concept is reachable from a root by descending childrenOf', function () {
    var seen = {};
    var stack = index.roots.slice();
    while (stack.length) {
        var u = stack.pop();
        if (seen[u]) { continue; }
        seen[u] = true;
        (index.childrenOf[u] || []).forEach(function (c) { stack.push(c); });
    }
    var unreached = Object.keys(index.concepts).filter(function (u) { return !seen[u]; });
    assert.strictEqual(unreached.length, 0,
        'unreachable: ' + unreached.map(function (u) { return index.concepts[u].prefLabel; }).slice(0, 5).join(', '));
});

console.log('\n== Accent-insensitive filter ==');

test('normalize strips accents and case', function () {
    assert.strictEqual(SMF.normalize('Céramique'), 'ceramique');
    assert.strictEqual(SMF.normalize('ÉGLISE'), 'eglise');
    assert.strictEqual(SMF.normalize('  Mérovingien '), 'merovingien');
});

test("'ceramique' (no accent) matches 'céramique'", function () {
    var res = SMF.filterConcepts(index, 'ceramique');
    var labels = res.map(function (r) { return r.concept.prefLabel; });
    assert.ok(labels.indexOf('céramique') !== -1, 'got: ' + labels.join(', '));
});

test("'merovingien' matches 'mérovingien'", function () {
    var res = SMF.filterConcepts(index, 'merovingien');
    var labels = res.map(function (r) { return r.concept.prefLabel; });
    assert.ok(labels.indexOf('mérovingien') !== -1, 'got: ' + labels.join(', '));
});

test("altLabel match: 'afrique' (lowercase altLabel) finds 'Afrique'", function () {
    var res = SMF.filterConcepts(index, 'afrique');
    var labels = res.map(function (r) { return r.concept.prefLabel; });
    assert.ok(labels.indexOf('Afrique') !== -1, 'got: ' + labels.join(', '));
});

test('empty query returns no results', function () {
    assert.strictEqual(SMF.filterConcepts(index, '').length, 0);
    assert.strictEqual(SMF.filterConcepts(index, '   ').length, 0);
});

test('filter results carry a breadcrumb path', function () {
    var res = SMF.filterConcepts(index, 'ceramique');
    assert.ok(res.length > 0);
    assert.ok(Array.isArray(res[0].path) && res[0].path.length >= 1);
    assert.strictEqual(res[0].path[res[0].path.length - 1].prefLabel, res[0].concept.prefLabel);
});

console.log('\n== Path resolution (breadcrumb) ==');

test('resolvePath of a deep node is root-first and includes the node', function () {
    // Afrique du nord: domaine par chronologie... > géographie > Afrique > Afrique du nord
    var uri = 'http://data.culture.fr/thesaurus/resource/ark:/67717/T51-112';
    var p = SMF.resolvePath(index, uri).map(function (c) { return c.prefLabel; });
    assert.deepStrictEqual(p, [
        'domaine par chronologie/civilisation/géographie',
        'géographie',
        'Afrique',
        'Afrique du nord'
    ], 'got: ' + p.join(' > '));
    // first element must be a declared root
    assert.ok(index.roots.indexOf(SMF.resolvePath(index, uri)[0].uri) !== -1);
});

test('resolvePath of a root is length 1', function () {
    var r = index.roots[0];
    var p = SMF.resolvePath(index, r);
    assert.strictEqual(p.length, 1);
    assert.strictEqual(p[0].uri, r);
});

test('resolvePath of unknown uri is empty', function () {
    assert.deepStrictEqual(SMF.resolvePath(index, 'urn:does-not-exist'), []);
});

console.log('\n== Output value construction (contract prefLabel||uri) ==');

test('buildOutputValue produces prefLabel||uri with empty idno segment', function () {
    var c = { prefLabel: 'peinture', uri: 'http://data.culture.fr/thesaurus/resource/ark:/67717/T51-42' };
    assert.strictEqual(SMF.buildOutputValue(c),
        'peinture||http://data.culture.fr/thesaurus/resource/ark:/67717/T51-42');
    // exactly 3 segments, middle empty
    var parts = SMF.buildOutputValue(c).split('|');
    assert.strictEqual(parts.length, 3);
    assert.strictEqual(parts[1], '');
});

test('real concept céramique yields correct value', function () {
    var res = SMF.filterConcepts(index, 'ceramique');
    var hit = res.filter(function (r) { return r.concept.prefLabel === 'céramique'; })[0];
    assert.ok(hit);
    var v = SMF.buildOutputValue(hit.concept);
    var parts = v.split('|');
    assert.strictEqual(parts.length, 3);
    assert.strictEqual(parts[0], 'céramique');
    assert.strictEqual(parts[1], '');
    assert.strictEqual(parts[2], hit.uri);
});

test('pipe in prefLabel is replaced by slash (keeps 3 segments)', function () {
    var c = { prefLabel: 'a|b|c', uri: 'urn:x' };
    var v = SMF.buildOutputValue(c);
    assert.strictEqual(v, 'a/b/c||urn:x');
    assert.strictEqual(v.split('|').length, 3);
});

test('every stored concept produces a parseable 3-segment value', function () {
    Object.keys(index.concepts).forEach(function (u) {
        var v = SMF.buildOutputValue(index.concepts[u]);
        var parts = v.split('|');
        assert.strictEqual(parts.length, 3, 'bad segments for ' + u + ': ' + v);
        assert.strictEqual(parts[1], '', 'idno not empty for ' + u);
        assert.strictEqual(parts[2], index.concepts[u].uri);
    });
});

console.log('\n== Summary ==');
console.log('  passed: ' + passed + ', failed: ' + failed);
process.exit(failed === 0 ? 0 : 1);
