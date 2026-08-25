/**
 * smfThesaurusBrowser.js
 * ----------------------------------------------------------------------
 * Arborescent (drill-down) thesaurus term picker for SMF/Joconde hierarchical
 * vocabularies, replacing the flat InformationService autocomplete in
 * CollectiveAccess (museesDeFrance plugin, element "domaine" / id 174).
 *
 * - Vanilla JS, no framework, no build step. Servable as-is.
 * - SINGLE data source ("tout client + IndexedDB"): the CLIENT provider loads
 *   the full flat JSON store ({meta, concepts:{uri:{...}}, roots:[uri]}) ONCE
 *   per browser, persists it in IndexedDB, then answers roots/children/path/
 *   search synchronously (wrapped in Promises) from the in-memory index. On
 *   every subsequent open it reloads from IndexedDB with NO body download, as
 *   long as the store is still fresh. Freshness is checked WITHOUT any server
 *   endpoint: a HEAD request on the static JSON URL reads Last-Modified (or
 *   ETag) as the version; if it matches the version stored in IndexedDB the
 *   body is never GET-ed. This applies IDENTICALLY to ALL thesauri (th285
 *   included). Instant navigation, no per-interaction network calls.
 * - On selection, writes the EXACT string `prefLabel||uri` (idno segment
 *   always empty) into a target hidden input, so CollectiveAccess
 *   InformationServiceAttributeValue::parseValue() stores
 *   prefLabel -> value_longtext1, uri -> value_longtext2.
 *
 * The pure logic (index build, filter, path resolution, output value) is
 * exported for headless Node testing (see smfThesaurusBrowser.test.js) AND
 * attached to window.SMFThesaurusBrowser for the browser.
 *
 * @package museesDeFrance
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * ----------------------------------------------------------------------
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;           // Node / CommonJS (tests)
    }
    if (typeof root !== 'undefined') {
        root.SMFThesaurusBrowser = api; // Browser global
    }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    /* ================================================================
     * PURE LOGIC (testable in Node, no DOM)
     * ================================================================ */

    /**
     * Normalize a string for accent- and case-insensitive matching.
     * Uses Unicode NFD decomposition to strip diacritics.
     * @param {string} s
     * @returns {string}
     */
    function normalize(s) {
        if (s === null || s === undefined) { return ''; }
        return String(s)
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '') // combining diacritics
            .toLowerCase()
            .trim();
    }

    /**
     * Build a working index from a raw thesaurus store.
     *
     * @param {object} store {meta, concepts:{uri:concept}, roots:[uri]}
     * @returns {object} index with:
     *   - concepts: {uri: concept}
     *   - roots: [uri]  (validated; only URIs that exist as concepts)
     *   - childrenOf: {uri: [childUri,...]}  derived from broader/narrower
     *   - parentOf: {uri: parentUri|null}
     *   - orphans: [uri]  concepts that are neither roots nor have a valid parent
     */
    function buildIndex(store) {
        if (!store || typeof store !== 'object' || !store.concepts) {
            throw new Error('Invalid thesaurus store: missing "concepts".');
        }
        var concepts = store.concepts;
        var uris = Object.keys(concepts);

        var childrenOf = {};
        var parentOf = {};
        uris.forEach(function (u) { childrenOf[u] = []; parentOf[u] = null; });

        // Derive parent/child edges from broader + narrower, keeping only
        // edges whose both endpoints exist as concepts. broader is authoritative
        // for parentOf; narrower fills childrenOf. We dedupe children.
        var seenChild = {}; // uri -> Set-like {childUri:true}
        uris.forEach(function (u) { seenChild[u] = {}; });

        function addEdge(parent, child) {
            if (!concepts[parent] || !concepts[child] || parent === child) { return; }
            if (!seenChild[parent][child]) {
                seenChild[parent][child] = true;
                childrenOf[parent].push(child);
            }
        }

        uris.forEach(function (u) {
            var c = concepts[u];
            var broader = Array.isArray(c.broader) ? c.broader : [];
            var narrower = Array.isArray(c.narrower) ? c.narrower : [];
            broader.forEach(function (p) {
                if (concepts[p]) {
                    if (parentOf[u] === null) { parentOf[u] = p; }
                    addEdge(p, u);
                }
            });
            narrower.forEach(function (ch) { addEdge(u, ch); });
        });

        // Validate roots: keep only declared roots that are real concepts.
        var declaredRoots = Array.isArray(store.roots) ? store.roots : [];
        var roots = declaredRoots.filter(function (u) { return !!concepts[u]; });
        var rootSet = {};
        roots.forEach(function (u) { rootSet[u] = true; });

        // A concept is an orphan if it is not a root and has no valid parent.
        var orphans = uris.filter(function (u) {
            return !rootSet[u] && parentOf[u] === null;
        });

        // Stable alphabetic sort of children by prefLabel (normalized).
        Object.keys(childrenOf).forEach(function (u) {
            childrenOf[u].sort(function (a, b) {
                var na = normalize(concepts[a].prefLabel);
                var nb = normalize(concepts[b].prefLabel);
                return na < nb ? -1 : (na > nb ? 1 : 0);
            });
        });
        roots.sort(function (a, b) {
            var na = normalize(concepts[a].prefLabel);
            var nb = normalize(concepts[b].prefLabel);
            return na < nb ? -1 : (na > nb ? 1 : 0);
        });

        return {
            meta: store.meta || {},
            concepts: concepts,
            roots: roots,
            childrenOf: childrenOf,
            parentOf: parentOf,
            orphans: orphans
        };
    }

    /**
     * Resolve the ancestry path (breadcrumb) of a concept, from top root down
     * to (and including) the concept itself.
     * @param {object} index
     * @param {string} uri
     * @returns {Array<object>} array of concepts, root-first. Empty if unknown.
     */
    function resolvePath(index, uri) {
        if (!index.concepts[uri]) { return []; }
        var path = [];
        var cur = uri;
        var guard = 0;
        var seen = {};
        while (cur && index.concepts[cur] && !seen[cur] && guard < 1000) {
            seen[cur] = true;
            path.unshift(index.concepts[cur]);
            cur = index.parentOf[cur];
            guard++;
        }
        return path;
    }

    /**
     * Filter concepts by a query, accent- and case-insensitive, matching
     * prefLabel and altLabels. Returns matches with their breadcrumb path.
     * @param {object} index
     * @param {string} query
     * @param {number} [limit=50]
     * @returns {Array<{uri, concept, path:Array<object>, pathLabels:string[]}>}
     */
    function filterConcepts(index, query, limit) {
        // Default aligned with the server SEARCH_LIMIT (50). We scan the WHOLE
        // set, then rank, then truncate — so the top-N is the TRUE top-N, not
        // merely the first N encountered (matches the server's searchC()).
        limit = (typeof limit === 'number' && limit > 0) ? limit : 50;
        var nq = normalize(query);
        var out = [];
        if (nq === '') { return out; }
        var uris = Object.keys(index.concepts);
        for (var i = 0; i < uris.length; i++) {
            var u = uris[i];
            var c = index.concepts[u];
            var hit = normalize(c.prefLabel).indexOf(nq) !== -1;
            if (!hit && Array.isArray(c.altLabels)) {
                for (var j = 0; j < c.altLabels.length; j++) {
                    if (normalize(c.altLabels[j]).indexOf(nq) !== -1) { hit = true; break; }
                }
            }
            if (hit) {
                var path = resolvePath(index, u);
                out.push({
                    uri: u,
                    concept: c,
                    path: path,
                    pathLabels: path.map(function (p) { return p.prefLabel; })
                });
            }
        }
        // Rank: prefix matches on prefLabel first, then alphabetical.
        out.sort(function (a, b) {
            var pa = normalize(a.concept.prefLabel).indexOf(nq) === 0 ? 0 : 1;
            var pb = normalize(b.concept.prefLabel).indexOf(nq) === 0 ? 0 : 1;
            if (pa !== pb) { return pa - pb; }
            var na = normalize(a.concept.prefLabel);
            var nb = normalize(b.concept.prefLabel);
            return na < nb ? -1 : (na > nb ? 1 : 0);
        });
        // Truncate to the limit AFTER ranking (true top-N).
        if (out.length > limit) { out = out.slice(0, limit); }
        return out;
    }

    /**
     * Build the CollectiveAccess output value for a concept.
     * Format is EXACTLY `prefLabel||uri` (three pipe-separated segments,
     * middle segment = idno = ALWAYS empty). Any `|` inside prefLabel is
     * replaced by `/` so the segment count stays at 3 (matches the migration).
     * @param {object} concept {prefLabel, uri}
     * @returns {string}
     */
    function buildOutputValue(concept) {
        if (!concept) { return '||'; }
        var label = String(concept.prefLabel === undefined || concept.prefLabel === null ? '' : concept.prefLabel);
        label = label.replace(/\|/g, '/');
        var uri = String(concept.uri === undefined || concept.uri === null ? '' : concept.uri);
        return label + '||' + uri;
    }

    /* ================================================================
     * INDEXEDDB PERSISTENCE (browser only; not exercised by Node tests)
     * ----------------------------------------------------------------
     * A tiny promise wrapper around one IndexedDB database `smf_thesauri`
     * with a single object store `stores` keyed by thesaurus id. Each value
     * is the PARSED object { version, concepts, roots } (IndexedDB does the
     * structured clone, so we never re-parse a 17 MB JSON string on reads).
     *
     * Goal: each thesaurus is downloaded ONCE per browser, then reloaded from
     * IndexedDB on every subsequent open — as long as the stored `version`
     * still matches the server's `version` (mtime). On any IndexedDB failure
     * (private mode, quota, exception, no support) every method rejects/returns
     * gracefully so the caller can fall back to a plain network fetch.
     * ================================================================ */
    var IDB_DB_NAME = 'smf_thesauri';
    var IDB_STORE = 'stores';
    var IDB_VERSION = 1;

    /** True if IndexedDB looks usable in this context. */
    function idbAvailable() {
        try { return typeof indexedDB !== 'undefined' && indexedDB !== null; }
        catch (e) { return false; }
    }

    /** Open (creating on first use) the smf_thesauri database. Promise<IDBDatabase>. */
    function idbOpen() {
        return new Promise(function (resolve, reject) {
            if (!idbAvailable()) { reject(new Error('IndexedDB unavailable')); return; }
            var req;
            try { req = indexedDB.open(IDB_DB_NAME, IDB_VERSION); }
            catch (e) { reject(e); return; }
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(IDB_STORE)) { db.createObjectStore(IDB_STORE); }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error || new Error('IndexedDB open failed')); };
            req.onblocked = function () { reject(new Error('IndexedDB open blocked')); };
        });
    }

    /** Read one stored entry by thesaurus id. Promise<{version,concepts,roots}|null>. */
    function idbGet(thesaurusId) {
        return idbOpen().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx, store, req;
                try {
                    tx = db.transaction(IDB_STORE, 'readonly');
                    store = tx.objectStore(IDB_STORE);
                    req = store.get(String(thesaurusId));
                } catch (e) { try { db.close(); } catch (e2) {} reject(e); return; }
                req.onsuccess = function () { try { db.close(); } catch (e) {} resolve(req.result || null); };
                req.onerror = function () { try { db.close(); } catch (e) {} reject(req.error || new Error('IndexedDB get failed')); };
            });
        });
    }

    /** Write one entry (value = {version,concepts,roots}). Promise<void>; rejects on quota/error. */
    function idbPut(thesaurusId, value) {
        return idbOpen().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx, store;
                try {
                    tx = db.transaction(IDB_STORE, 'readwrite');
                    store = tx.objectStore(IDB_STORE);
                    store.put(value, String(thesaurusId));
                } catch (e) { try { db.close(); } catch (e2) {} reject(e); return; }
                tx.oncomplete = function () { try { db.close(); } catch (e) {} resolve(); };
                tx.onabort = function () { try { db.close(); } catch (e) {} reject(tx.error || new Error('IndexedDB write aborted')); };
                tx.onerror = function () { try { db.close(); } catch (e) {} reject(tx.error || new Error('IndexedDB write failed')); };
            });
        });
    }

    /* ================================================================
     * DATA PROVIDER
     * ----------------------------------------------------------------
     * The provider abstracts the source of the tree data so the widget code
     * (render + keyboard + ARIA + output) stays decoupled from loading.
     *
     * Provider contract (all return Promises; nodes are plain
     * {uri, label, hasChildren} rows, search adds a `path`):
     *   ready()            -> Promise<void>
     *   getRoots()         -> Promise<[{uri,label,hasChildren}]>
     *   getChildren(uri)   -> Promise<[{uri,label,hasChildren}]>
     *   getPath(uri)       -> Promise<[{uri,label}]>            (root->node incl.)
     *   search(q)          -> Promise<[{uri,label,path:[{uri,label}]}]>
     *   getConcept(uri)    -> {prefLabel,uri}|null              (sync; for output)
     * ================================================================ */

    /**
     * Read the "version" of a static store WITHOUT downloading its body: a HEAD
     * request whose Last-Modified (or, failing that, ETag) header identifies the
     * current store revision. Same-origin. Resolves to a string tag, or null if
     * the HEAD is unusable (network/proxy/header stripped) so the caller can fall
     * back to a plain GET.
     * @param {string} url
     * @returns {Promise<string|null>}
     */
    function fetchStoreVersion(url) {
        return fetch(url, { method: 'HEAD', credentials: 'same-origin' })
            .then(function (r) {
                if (!r || !r.ok) { return null; }
                var lm = r.headers.get('Last-Modified');
                if (lm) { return 'lm:' + lm; }
                var et = r.headers.get('ETag');
                if (et) { return 'et:' + et; }
                return null;   // no usable freshness header -> force a GET
            })
            .catch(function () { return null; });
    }

    /**
     * CLIENT provider: full store loaded once, everything answered in-memory.
     * The ONLY data path (all thesauri, th285 included).
     *
     * DATA SOURCE:
     *   - opts.data            : inline store (Node tests / callers) — used as-is,
     *                            no network, no IndexedDB.
     *   - opts.thesaurus + opts.url : IndexedDB-backed mode.
     *       On ready() it HEADs opts.url to read the current version
     *       (Last-Modified/ETag), then checks IndexedDB (key = thesaurus id): if a
     *       stored entry exists AND its version matches, the tree is rebuilt from
     *       IndexedDB with NO body GET. Otherwise it GETs opts.url ONCE, builds the
     *       index, then writes {version,concepts,roots} back to IndexedDB (async).
     *   - opts.url only        : plain GET (IndexedDB-unavailable).
     *
     * Robustness: if the HEAD is unusable, or on ANY IndexedDB error (unavailable,
     * quota, exception), it falls back transparently to a direct GET — the widget
     * never breaks.
     *
     * @param {object} opts { data?, thesaurus?, url? }
     */
    function ClientProvider(opts) {
        this.opts = opts || {};
        this.index = null;
    }
    /** Build the in-memory index from a parsed {concepts, roots} object. */
    ClientProvider.prototype._buildFrom = function (store) {
        this.index = buildIndex(store);
    };
    /** GET the full store over the network and build the index. Promise<store>. */
    ClientProvider.prototype._fetchAndBuild = function () {
        var self = this;
        return fetch(this.opts.url, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function (store) { self._buildFrom(store); return store; });
    };
    ClientProvider.prototype.ready = function () {
        var self = this;
        if (this.index) { return Promise.resolve(); }
        // Inline data (tests / explicit callers): no network, no IndexedDB.
        if (this.opts.data) {
            this._buildFrom(this.opts.data);
            return Promise.resolve();
        }
        var thesaurus = this.opts.thesaurus;
        // No IndexedDB context (no thesaurus id / no support): plain GET.
        if (!thesaurus || !idbAvailable()) {
            return this._fetchAndBuild();
        }
        // 1) Read the current version via HEAD (no body download).
        return fetchStoreVersion(this.opts.url).then(function (version) {
            // 2) Try a fresh IndexedDB entry (no download if version matches).
            return idbGet(thesaurus).then(function (stored) {
                if (version !== null && stored && stored.concepts && String(stored.version) === String(version)) {
                    // Fresh: rebuild the index straight from IndexedDB, no network.
                    self._buildFrom({ concepts: stored.concepts, roots: stored.roots || [] });
                    return;
                }
                // 3) Stale, absent, or version unknown: GET once, build, persist.
                return self._fetchAndBuild().then(function (store) {
                    // Only persist if we have a usable version tag to compare later;
                    // otherwise a re-open would never trust the cached copy anyway.
                    if (version === null) { return; }
                    idbPut(thesaurus, {
                        version: version,
                        concepts: store.concepts,
                        roots: Array.isArray(store.roots) ? store.roots : []
                    }).catch(function () { /* quota / private mode: ignore, in-memory index already built */ });
                });
            });
        }).catch(function () {
            // Any IndexedDB / HEAD failure -> transparent fallback to a direct GET.
            if (self.index) { return; }
            return self._fetchAndBuild();
        });
    };
    ClientProvider.prototype._row = function (uri) {
        var c = this.index.concepts[uri];
        return {
            uri: uri,
            label: c ? c.prefLabel : uri,
            hasChildren: (this.index.childrenOf[uri] || []).length > 0
        };
    };
    ClientProvider.prototype.getRoots = function () {
        var self = this;
        return Promise.resolve(this.index.roots.map(function (u) { return self._row(u); }));
    };
    ClientProvider.prototype.getChildren = function (uri) {
        var self = this;
        var kids = this.index.childrenOf[uri] || [];
        return Promise.resolve(kids.map(function (u) { return self._row(u); }));
    };
    ClientProvider.prototype.getPath = function (uri) {
        var path = resolvePath(this.index, uri).map(function (c) {
            return { uri: c.uri, label: c.prefLabel };
        });
        return Promise.resolve(path);
    };
    ClientProvider.prototype.search = function (q) {
        // Same limit (50) as the server SEARCH_LIMIT / lazy mode.
        var results = filterConcepts(this.index, q, 50).map(function (res) {
            return {
                uri: res.uri,
                label: res.concept.prefLabel,
                path: res.path.map(function (p) { return { uri: p.uri, label: p.prefLabel }; })
            };
        });
        return Promise.resolve(results);
    };
    ClientProvider.prototype.getConcept = function (uri) {
        var c = this.index.concepts[uri];
        return c ? { prefLabel: c.prefLabel, uri: c.uri } : null;
    };

    /* ================================================================
     * DOM / BROWSER LAYER (not exercised by headless tests)
     * ================================================================ */

    /** Escape a string for safe text insertion (never innerHTML raw labels). */
    function escText(s) {
        return String(s === null || s === undefined ? '' : s);
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'text') { node.textContent = attrs[k]; }
                else if (k === 'class') { node.className = attrs[k]; }
                else if (k.indexOf('data-') === 0 || k.indexOf('aria-') === 0) { node.setAttribute(k, attrs[k]); }
                else if (k === 'role' || k === 'tabindex' || k === 'id' || k === 'type' || k === 'placeholder' || k === 'title') { node.setAttribute(k, attrs[k]); }
                else { node[k] = attrs[k]; }
            });
        }
        (children || []).forEach(function (c) { if (c) { node.appendChild(c); } });
        return node;
    }

    /**
     * Widget controller. Renders into a container element.
     *
     * Data-source selection:
     *   - opts.provider : an explicit provider instance (preferred), OR
     *   - opts.data / opts.url : builds a ClientProvider (legacy convenience).
     *
     * @param {object} opts
     *   - container: HTMLElement (required)
     *   - provider: data provider (see contract above)
     *   - url|data: legacy client-mode store source (if no provider given)
     *   - onSelect: function({prefLabel, uri, path, value})
     *   - onClose: function()
     *   - i18n: optional label overrides
     */
    function SMFTreeWidget(opts) {
        this.opts = opts || {};
        this.container = this.opts.container;
        this.provider = this.opts.provider || new ClientProvider({ url: this.opts.url, data: this.opts.data });
        this.mode = 'tree';           // 'tree' | 'filter'
        this.expanded = {};           // uri -> bool
        this.childCache = {};         // uri -> [rows]  (rows already fetched)
        this.rootRows = [];           // [rows] roots
        this.visibleItems = [];       // flat list of currently focusable rows
        this.activeIndex = -1;        // index into visibleItems
        this._searchTimer = null;     // debounce handle
        this._searchSeq = 0;          // race guard for async search
        this._childSeq = {};          // race guard per-uri for async expand
        this.i18n = Object.assign({
            placeholder: 'Rechercher ou parcourir l’arbre…',
            noResult: 'Aucun résultat',
            locate: 'Localiser dans l’arbre',
            loading: 'Chargement du thésaurus…',
            loadError: 'Impossible de charger le thésaurus.',
            loadingChildren: 'Chargement…',
            searching: 'Recherche…'
        }, this.opts.i18n || {});
        if (!this.container) { throw new Error('SMFTreeWidget: container is required.'); }
    }

    SMFTreeWidget.prototype.load = function () {
        var self = this;
        this.container.textContent = this.i18n.loading;
        return this.provider.ready()
            .then(function () { return self.provider.getRoots(); })
            .then(function (rows) {
                self.rootRows = rows || [];
                self.render();
                return self;
            })
            .catch(function (e) {
                self.container.textContent = self.i18n.loadError + ' (' + (e && e.message ? e.message : e) + ')';
                throw e;
            });
    };

    SMFTreeWidget.prototype.render = function () {
        var self = this;
        this.container.textContent = '';
        this.container.classList.add('smf-tb');

        this.input = el('input', {
            type: 'text',
            class: 'smf-tb-search',
            placeholder: this.i18n.placeholder,
            'aria-label': this.i18n.placeholder,
            role: 'combobox',
            'aria-expanded': 'true',
            'aria-autocomplete': 'list'
        });
        this.input.addEventListener('input', function () { self.onQuery(self.input.value); });
        this.input.addEventListener('keydown', function (e) { self.onKeyDown(e); });

        this.treeEl = el('div', { class: 'smf-tb-tree', role: 'tree', 'aria-label': 'Thésaurus', tabindex: '-1' });

        this.container.appendChild(this.input);
        this.container.appendChild(this.treeEl);

        this.renderTree();
        this.input.focus();
    };

    // ---- Row helpers ----------------------------------------------------

    /** Cached children rows for a uri, or [] if not yet fetched. */
    SMFTreeWidget.prototype._childrenRows = function (uri) {
        return this.childCache[uri] || [];
    };

    // ---- Tree mode rendering -------------------------------------------

    SMFTreeWidget.prototype.renderTree = function () {
        this.mode = 'tree';
        this.treeEl.textContent = '';
        this.visibleItems = [];
        var ul = el('ul', { role: 'group', class: 'smf-tb-group' });
        this.rootRows.forEach(function (row) { this._renderNode(row, ul, 1); }, this);
        this.treeEl.appendChild(ul);
        this.refreshActive(0);
    };

    SMFTreeWidget.prototype._renderNode = function (row, parentUl, depth) {
        var self = this;
        var uri = row.uri;
        var hasKids = !!row.hasChildren;
        var isExpanded = !!this.expanded[uri];

        var li = el('li', { role: 'treeitem', class: 'smf-tb-item' });
        li.setAttribute('aria-level', String(depth));
        if (hasKids) { li.setAttribute('aria-expanded', isExpanded ? 'true' : 'false'); }
        li.setAttribute('data-uri', uri);

        var rowEl = el('div', { class: 'smf-tb-row' });
        rowEl.style.paddingLeft = (depth * 16) + 'px';

        var twisty = el('span', { class: 'smf-tb-twisty', 'aria-hidden': 'true', text: hasKids ? (isExpanded ? '▾' : '▸') : ' ' });
        if (hasKids) {
            twisty.addEventListener('click', function (e) { e.stopPropagation(); self.toggle(uri); });
        }
        var label = el('span', { class: 'smf-tb-label', text: escText(row.label) });

        rowEl.appendChild(twisty);
        rowEl.appendChild(label);
        rowEl.addEventListener('click', function () { self.select(uri); });
        li.appendChild(rowEl);

        parentUl.appendChild(li);
        this.visibleItems.push({ uri: uri, li: li, row: rowEl, hasKids: hasKids, expanded: isExpanded, depth: depth });

        if (hasKids && isExpanded) {
            var childRows = this._childrenRows(uri);
            if (childRows.length) {
                var ul = el('ul', { role: 'group', class: 'smf-tb-group' });
                childRows.forEach(function (k) { self._renderNode(k, ul, depth + 1); });
                li.appendChild(ul);
            } else {
                // Children not yet available: show an inline loading placeholder.
                var loadingLi = el('li', { role: 'treeitem', class: 'smf-tb-item smf-tb-loading' });
                var loadingRow = el('div', { class: 'smf-tb-row' });
                loadingRow.style.paddingLeft = ((depth + 1) * 16) + 'px';
                loadingRow.appendChild(el('span', { class: 'smf-tb-crumb', text: this.i18n.loadingChildren }));
                loadingLi.appendChild(loadingRow);
                var ul2 = el('ul', { role: 'group', class: 'smf-tb-group' });
                ul2.appendChild(loadingLi);
                li.appendChild(ul2);
            }
        }
    };

    SMFTreeWidget.prototype.toggle = function (uri) {
        var self = this;
        var willExpand = !this.expanded[uri];
        this.expanded[uri] = willExpand;
        var active = this.activeIndex >= 0 ? this.visibleItems[this.activeIndex] : null;
        var keepUri = active ? active.uri : uri;

        if (willExpand && !this.childCache[uri]) {
            // Fetch children (resolved instantly from the in-memory index).
            var seq = (this._childSeq[uri] || 0) + 1;
            this._childSeq[uri] = seq;
            this.renderTree();            // shows the loading placeholder
            this.focusUri(keepUri);
            this.provider.getChildren(uri).then(function (rows) {
                if (self._childSeq[uri] !== seq) { return; }     // superseded
                self.childCache[uri] = rows || [];
                if (self.mode === 'tree' && self.expanded[uri]) {
                    self.renderTree();
                    self.focusUri(keepUri);
                }
            }).catch(function () {
                self.childCache[uri] = [];
                if (self.mode === 'tree') { self.renderTree(); self.focusUri(keepUri); }
            });
            return;
        }
        this.renderTree();
        this.focusUri(keepUri);
    };

    // ---- Filter mode rendering -----------------------------------------

    SMFTreeWidget.prototype.onQuery = function (q) {
        var self = this;
        if (normalize(q) === '') {
            if (this._searchTimer) { clearTimeout(this._searchTimer); this._searchTimer = null; }
            this._searchSeq++;   // cancel any in-flight search
            this.renderTree();
            return;
        }
        this.mode = 'filter';
        // Light debounce so type-ahead does not fire a request per keystroke.
        if (this._searchTimer) { clearTimeout(this._searchTimer); }
        this._searchTimer = setTimeout(function () { self._runSearch(q); }, 200);
    };

    SMFTreeWidget.prototype._runSearch = function (q) {
        var self = this;
        var seq = ++this._searchSeq;
        // Loading indicator while awaiting the provider.
        this.treeEl.textContent = '';
        this.visibleItems = [];
        this.treeEl.appendChild(el('div', { class: 'smf-tb-noresult', text: this.i18n.searching }));
        this.activeIndex = -1;

        this.provider.search(q).then(function (results) {
            if (seq !== self._searchSeq || self.mode !== 'filter') { return; }  // superseded
            self._renderResults(results || []);
        }).catch(function () {
            if (seq !== self._searchSeq) { return; }
            self.treeEl.textContent = '';
            self.treeEl.appendChild(el('div', { class: 'smf-tb-noresult', text: self.i18n.noResult }));
            self.activeIndex = -1;
        });
    };

    SMFTreeWidget.prototype._renderResults = function (results) {
        var self = this;
        this.treeEl.textContent = '';
        this.visibleItems = [];
        if (results.length === 0) {
            this.treeEl.appendChild(el('div', { class: 'smf-tb-noresult', text: this.i18n.noResult }));
            this.activeIndex = -1;
            return;
        }
        var ul = el('ul', { role: 'tree', class: 'smf-tb-group' });
        results.forEach(function (res) {
            var li = el('li', { role: 'treeitem', class: 'smf-tb-item smf-tb-result' });
            li.setAttribute('data-uri', res.uri);
            var rowEl = el('div', { class: 'smf-tb-row' });

            var label = el('span', { class: 'smf-tb-label', text: escText(res.label) });
            rowEl.appendChild(label);

            // breadcrumb path (ancestry), excluding the node itself
            var pathLabels = (res.path || []).map(function (p) { return p.label; });
            if (pathLabels.length > 1) {
                var crumb = pathLabels.slice(0, -1).join(' › ');
                rowEl.appendChild(el('span', { class: 'smf-tb-crumb', text: '  ' + crumb }));
            }

            rowEl.addEventListener('click', function () { self.select(res.uri); });
            li.appendChild(rowEl);
            ul.appendChild(li);
            self.visibleItems.push({ uri: res.uri, li: li, row: rowEl, hasKids: false });
        });
        this.treeEl.appendChild(ul);
        this.refreshActive(0);
    };

    /**
     * Expand all ancestors of a uri, switch to tree mode, and focus it.
     * Fetches the breadcrumb path and each ancestor's children (instant, from
     * the in-memory index) before rendering so the drilled-down node is visible.
     */
    SMFTreeWidget.prototype.locate = function (uri) {
        var self = this;
        this.input.value = '';
        this.provider.getPath(uri).then(function (path) {
            var ancestors = path.slice(0, -1); // exclude node itself
            ancestors.forEach(function (p) { self.expanded[p.uri] = true; });
            // Ensure children of every ancestor are loaded.
            var chain = Promise.resolve();
            ancestors.forEach(function (p) {
                chain = chain.then(function () {
                    if (self.childCache[p.uri]) { return; }
                    return self.provider.getChildren(p.uri).then(function (rows) { self.childCache[p.uri] = rows || []; });
                });
            });
            return chain;
        }).then(function () {
            self.renderTree();
            self.focusUri(uri);
            self.input.focus();
        });
    };

    // ---- Active row / keyboard -----------------------------------------

    SMFTreeWidget.prototype.refreshActive = function (idx) {
        this.visibleItems.forEach(function (it) { it.li.classList.remove('smf-tb-active'); it.li.setAttribute('aria-selected', 'false'); });
        if (this.visibleItems.length === 0) { this.activeIndex = -1; return; }
        this.activeIndex = Math.max(0, Math.min(idx, this.visibleItems.length - 1));
        var it = this.visibleItems[this.activeIndex];
        it.li.classList.add('smf-tb-active');
        it.li.setAttribute('aria-selected', 'true');
        if (it.li.scrollIntoView) { it.li.scrollIntoView({ block: 'nearest' }); }
    };

    SMFTreeWidget.prototype.focusUri = function (uri) {
        for (var i = 0; i < this.visibleItems.length; i++) {
            if (this.visibleItems[i].uri === uri) { this.refreshActive(i); return; }
        }
        this.refreshActive(0);
    };

    SMFTreeWidget.prototype.onKeyDown = function (e) {
        var it = this.activeIndex >= 0 ? this.visibleItems[this.activeIndex] : null;
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault(); this.refreshActive(this.activeIndex + 1); break;
            case 'ArrowUp':
                e.preventDefault(); this.refreshActive(this.activeIndex - 1); break;
            case 'ArrowRight':
                if (this.mode === 'tree' && it && it.hasKids && !this.expanded[it.uri]) {
                    e.preventDefault(); this.toggle(it.uri);
                }
                break;
            case 'ArrowLeft':
                if (this.mode === 'tree' && it) {
                    if (it.hasKids && this.expanded[it.uri]) {
                        e.preventDefault(); this.toggle(it.uri);
                    } else if (it.depth && it.depth > 1) {
                        // Focus the parent row already visible above (works in
                        // both modes without needing a parentOf lookup).
                        e.preventDefault(); this._focusParentOf(this.activeIndex);
                    }
                }
                break;
            case 'Enter':
                if (it) { e.preventDefault(); this.select(it.uri); }
                break;
            case 'Escape':
                e.preventDefault();
                if (typeof this.opts.onClose === 'function') { this.opts.onClose(); }
                break;
            default: break;
        }
    };

    /** Focus the nearest shallower row above the given index (its parent). */
    SMFTreeWidget.prototype._focusParentOf = function (idx) {
        var cur = this.visibleItems[idx];
        if (!cur || !cur.depth) { return; }
        for (var i = idx - 1; i >= 0; i--) {
            if (this.visibleItems[i].depth < cur.depth) { this.refreshActive(i); return; }
        }
    };

    // ---- Selection ------------------------------------------------------

    SMFTreeWidget.prototype.select = function (uri) {
        var self = this;
        var concept = this.provider.getConcept(uri);
        if (!concept) {
            // Should be cached from the row we rendered; fall back to the
            // visible label if a provider ever misses it.
            var label = '';
            for (var i = 0; i < this.visibleItems.length; i++) {
                if (this.visibleItems[i].uri === uri) {
                    var lblEl = this.visibleItems[i].row.querySelector('.smf-tb-label');
                    label = lblEl ? lblEl.textContent : '';
                    break;
                }
            }
            concept = { prefLabel: label, uri: uri };
        }
        var value = buildOutputValue(concept);
        // Best-effort breadcrumb for the caller (does not block selection).
        this.provider.getPath(uri).then(function (path) {
            self._emitSelect(concept, path, value);
        }).catch(function () {
            self._emitSelect(concept, [], value);
        });
    };

    SMFTreeWidget.prototype._emitSelect = function (concept, path, value) {
        if (typeof this.opts.onSelect === 'function') {
            this.opts.onSelect({
                prefLabel: concept.prefLabel,
                uri: concept.uri,
                path: (path || []).map(function (p) { return { prefLabel: p.label !== undefined ? p.label : p.prefLabel, uri: p.uri }; }),
                value: value
            });
        }
    };

    /* ================================================================
     * Public API
     * ================================================================ */
    return {
        // pure logic (Node-testable)
        normalize: normalize,
        buildIndex: buildIndex,
        resolvePath: resolvePath,
        filterConcepts: filterConcepts,
        buildOutputValue: buildOutputValue,
        // IndexedDB persistence helpers (browser only; no-op/reject in Node)
        idbAvailable: idbAvailable,
        idbGet: idbGet,
        idbPut: idbPut,
        // HEAD-based store version probe (Last-Modified/ETag)
        fetchStoreVersion: fetchStoreVersion,
        // provider (single "tout client + IndexedDB" path)
        ClientProvider: ClientProvider,
        // browser widget
        Widget: SMFTreeWidget,
        /**
         * Convenience mount: create a widget and load it.
         * Accepts either opts.provider, or opts.url/opts.data (client mode).
         * @returns {SMFTreeWidget}
         */
        mount: function (opts) {
            var w = new SMFTreeWidget(opts);
            w.load();
            return w;
        }
    };
});
