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

console.log('\n== HEAD version probe (fetchStoreVersion) ==');

// Minimal Headers-like shim (case-insensitive get) for the stubbed fetch.
function fakeHeaders(map) {
    var lower = {};
    Object.keys(map || {}).forEach(function (k) { lower[k.toLowerCase()] = map[k]; });
    return { get: function (k) { var v = lower[String(k).toLowerCase()]; return (v === undefined) ? null : v; } };
}
function withFetch(stub, fn) {
    var prev = global.fetch;
    global.fetch = stub;
    return Promise.resolve().then(fn).then(
        function (v) { global.fetch = prev; return v; },
        function (e) { global.fetch = prev; throw e; }
    );
}
// Tiny async test runner: collects promises, reports at the end.
var asyncChain = Promise.resolve();
function atest(name, fn) {
    asyncChain = asyncChain.then(function () {
        return Promise.resolve().then(fn).then(
            function () { passed++; console.log('  ok   - ' + name); },
            function (e) { failed++; console.log('  FAIL - ' + name + '\n         ' + (e && e.message ? e.message : e)); }
        );
    });
}

atest('fetchStoreVersion returns lm:<Last-Modified> when present', function () {
    return withFetch(function (url, opts) {
        assert.strictEqual(opts.method, 'HEAD');
        assert.strictEqual(opts.credentials, 'same-origin');
        return Promise.resolve({ ok: true, headers: fakeHeaders({ 'Last-Modified': 'Mon, 01 Jan 2024 00:00:00 GMT' }) });
    }, function () {
        return SMF.fetchStoreVersion('/x/th1.json').then(function (v) {
            assert.strictEqual(v, 'lm:Mon, 01 Jan 2024 00:00:00 GMT');
        });
    });
});

atest('fetchStoreVersion falls back to et:<ETag> when no Last-Modified', function () {
    return withFetch(function () {
        return Promise.resolve({ ok: true, headers: fakeHeaders({ 'ETag': '"abc123"' }) });
    }, function () {
        return SMF.fetchStoreVersion('/x/th1.json').then(function (v) { assert.strictEqual(v, 'et:"abc123"'); });
    });
});

atest('fetchStoreVersion resolves null on network error (caller will GET)', function () {
    return withFetch(function () { return Promise.reject(new Error('offline')); }, function () {
        return SMF.fetchStoreVersion('/x/th1.json').then(function (v) { assert.strictEqual(v, null); });
    });
});

console.log('\n== ClientProvider: IndexedDB freshness + fallback ==');

var MINI_STORE = { meta: { concept_count: 1 }, roots: ['u1'], concepts: { u1: { uri: 'u1', prefLabel: 'Root', broader: [], narrower: [] } } };

atest('inline data builds without any network', function () {
    return withFetch(function () { throw new Error('must not fetch'); }, function () {
        var p = new SMF.ClientProvider({ data: MINI_STORE });
        return p.ready().then(function () { return p.getRoots(); }).then(function (rows) {
            assert.strictEqual(rows.length, 1);
            assert.strictEqual(rows[0].label, 'Root');
        });
    });
});

atest('no thesaurus id -> plain GET (no HEAD, no IndexedDB)', function () {
    var calls = [];
    return withFetch(function (url, opts) {
        calls.push((opts && opts.method) || 'GET');
        return Promise.resolve({ ok: true, json: function () { return Promise.resolve(MINI_STORE); } });
    }, function () {
        var p = new SMF.ClientProvider({ url: '/x/th1.json' });   // no thesaurus -> idb path skipped
        return p.ready().then(function () {
            assert.deepStrictEqual(calls, ['GET'], 'expected a single GET, got: ' + calls.join(','));
            return p.getRoots();
        }).then(function (rows) { assert.strictEqual(rows[0].uri, 'u1'); });
    });
});

// A fake `indexedDB` whose open() errors: it makes idbAvailable() true (so the
// provider takes the HEAD + IDB path) while idbGet()/idbPut() reject, exercising
// the transparent GET fallback. The true "served from IndexedDB, no GET" path is
// browser-only (structured clone) and is left for human validation.
function withFakeIndexedDB(fn) {
    var prev = global.indexedDB;
    global.indexedDB = {
        open: function () {
            var req = {};
            setTimeout(function () { if (typeof req.onerror === 'function') { req.error = new Error('idb open failed'); req.onerror(); } }, 0);
            return req;
        }
    };
    return Promise.resolve().then(fn).then(
        function (v) { global.indexedDB = prev; return v; },
        function (e) { global.indexedDB = prev; throw e; }
    );
}

atest('IndexedDB available: HEAD is probed, then IDB error -> GET fallback', function () {
    var methods = [];
    return withFakeIndexedDB(function () {
        return withFetch(function (url, opts) {
            var m = (opts && opts.method) || 'GET';
            methods.push(m);
            if (m === 'HEAD') { return Promise.resolve({ ok: true, headers: fakeHeaders({ 'Last-Modified': 'V1' }) }); }
            return Promise.resolve({ ok: true, json: function () { return Promise.resolve(MINI_STORE); } });
        }, function () {
            var p = new SMF.ClientProvider({ thesaurus: 'th1', url: '/x/th1.json' });
            return p.ready().then(function () {
                assert.ok(methods.indexOf('HEAD') !== -1, 'expected a HEAD probe, got: ' + methods.join(','));
                assert.ok(methods.indexOf('GET') !== -1, 'expected a GET fallback, got: ' + methods.join(','));
                return p.getRoots();
            }).then(function (rows) { assert.strictEqual(rows[0].uri, 'u1'); });
        });
    });
});

atest('HEAD rejects -> transparent fallback to a direct GET', function () {
    var methods = [];
    return withFakeIndexedDB(function () {
        return withFetch(function (url, opts) {
            var m = (opts && opts.method) || 'GET';
            methods.push(m);
            if (m === 'HEAD') { return Promise.reject(new Error('no HEAD')); }
            return Promise.resolve({ ok: true, json: function () { return Promise.resolve(MINI_STORE); } });
        }, function () {
            var p = new SMF.ClientProvider({ thesaurus: 'th1', url: '/x/th1.json' });
            return p.ready().then(function () {
                assert.ok(methods.indexOf('GET') !== -1, 'expected a GET fallback, got: ' + methods.join(','));
                return p.getRoots();
            }).then(function (rows) { assert.strictEqual(rows[0].uri, 'u1'); });
        });
    });
});

asyncChain.then(function () {
    console.log('\n== Summary ==');
    console.log('  passed: ' + passed + ', failed: ' + failed);
    process.exit(failed === 0 ? 0 : 1);
});
