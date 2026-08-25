/**
 * smfThesaurusBrowser.js
 * ----------------------------------------------------------------------
 * Arborescent (drill-down) thesaurus term picker for SMF/Joconde hierarchical
 * vocabularies, replacing the flat InformationService autocomplete in
 * CollectiveAccess (museesDeFrance plugin, element "domaine" / id 174).
 *
 * - Vanilla JS, no framework, no build step. Servable as-is.
 * - Loads a flat JSON store ({meta, concepts:{uri:{...}}, roots:[uri]}),
 *   builds an index and renders a keyboard-navigable ARIA tree + type-ahead
 *   filter (accent- and case-insensitive over prefLabel + altLabels).
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
     * @param {number} [limit=200]
     * @returns {Array<{uri, concept, path:Array<object>, pathLabels:string[]}>}
     */
    function filterConcepts(index, query, limit) {
        limit = (typeof limit === 'number' && limit > 0) ? limit : 200;
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
                if (out.length >= limit) { break; }
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
     * @param {object} opts
     *   - container: HTMLElement (required)
     *   - url: string JSON url (mutually exclusive with data)
     *   - data: object pre-loaded store (mutually exclusive with url)
     *   - onSelect: function({prefLabel, uri, path, value})
     *   - i18n: optional label overrides
     */
    function SMFTreeWidget(opts) {
        this.opts = opts || {};
        this.container = this.opts.container;
        this.index = null;
        this.mode = 'tree';           // 'tree' | 'filter'
        this.expanded = {};           // uri -> bool
        this.visibleItems = [];       // flat list of currently focusable rows
        this.activeIndex = -1;        // index into visibleItems
        this.i18n = Object.assign({
            placeholder: 'Rechercher ou parcourir l’arbre…',
            noResult: 'Aucun résultat',
            locate: 'Localiser dans l’arbre',
            loading: 'Chargement du thésaurus…',
            loadError: 'Impossible de charger le thésaurus.'
        }, this.opts.i18n || {});
        if (!this.container) { throw new Error('SMFTreeWidget: container is required.'); }
    }

    SMFTreeWidget.prototype.load = function () {
        var self = this;
        if (this.opts.data) {
            this.index = buildIndex(this.opts.data);
            this.render();
            return Promise.resolve(this.index);
        }
        this.container.textContent = this.i18n.loading;
        return fetch(this.opts.url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (store) {
                self.index = buildIndex(store);
                self.render();
                return self.index;
            })
            .catch(function (e) {
                self.container.textContent = self.i18n.loadError + ' (' + e.message + ')';
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

    // ---- Tree mode rendering -------------------------------------------

    SMFTreeWidget.prototype.renderTree = function () {
        this.mode = 'tree';
        this.treeEl.textContent = '';
        this.visibleItems = [];
        var ul = el('ul', { role: 'group', class: 'smf-tb-group' });
        this.index.roots.forEach(function (uri) { this._renderNode(uri, ul, 1); }, this);
        this.treeEl.appendChild(ul);
        this.refreshActive(0);
    };

    SMFTreeWidget.prototype._renderNode = function (uri, parentUl, depth) {
        var self = this;
        var c = this.index.concepts[uri];
        var kids = this.index.childrenOf[uri] || [];
        var hasKids = kids.length > 0;
        var isExpanded = !!this.expanded[uri];

        var li = el('li', { role: 'treeitem', class: 'smf-tb-item' });
        li.setAttribute('aria-level', String(depth));
        if (hasKids) { li.setAttribute('aria-expanded', isExpanded ? 'true' : 'false'); }
        li.setAttribute('data-uri', uri);

        var row = el('div', { class: 'smf-tb-row' });
        row.style.paddingLeft = (depth * 16) + 'px';

        var twisty = el('span', { class: 'smf-tb-twisty', 'aria-hidden': 'true', text: hasKids ? (isExpanded ? '▾' : '▸') : ' ' });
        if (hasKids) {
            twisty.addEventListener('click', function (e) { e.stopPropagation(); self.toggle(uri); });
        }
        var label = el('span', { class: 'smf-tb-label', text: escText(c.prefLabel) });

        row.appendChild(twisty);
        row.appendChild(label);
        row.addEventListener('click', function () { self.select(uri); });
        li.appendChild(row);

        parentUl.appendChild(li);
        this.visibleItems.push({ uri: uri, li: li, row: row, hasKids: hasKids, expanded: isExpanded, depth: depth });

        if (hasKids && isExpanded) {
            var ul = el('ul', { role: 'group', class: 'smf-tb-group' });
            kids.forEach(function (k) { self._renderNode(k, ul, depth + 1); });
            li.appendChild(ul);
        }
    };

    SMFTreeWidget.prototype.toggle = function (uri) {
        this.expanded[uri] = !this.expanded[uri];
        var active = this.activeIndex >= 0 ? this.visibleItems[this.activeIndex] : null;
        var keepUri = active ? active.uri : uri;
        this.renderTree();
        this.focusUri(keepUri);
    };

    // ---- Filter mode rendering -----------------------------------------

    SMFTreeWidget.prototype.onQuery = function (q) {
        if (normalize(q) === '') { this.renderTree(); return; }
        this.mode = 'filter';
        this.treeEl.textContent = '';
        this.visibleItems = [];
        var results = filterConcepts(this.index, q, 200);
        var self = this;
        if (results.length === 0) {
            this.treeEl.appendChild(el('div', { class: 'smf-tb-noresult', text: this.i18n.noResult }));
            this.activeIndex = -1;
            return;
        }
        var ul = el('ul', { role: 'tree', class: 'smf-tb-group' });
        results.forEach(function (res) {
            var li = el('li', { role: 'treeitem', class: 'smf-tb-item smf-tb-result' });
            li.setAttribute('data-uri', res.uri);
            var row = el('div', { class: 'smf-tb-row' });

            var label = el('span', { class: 'smf-tb-label', text: escText(res.concept.prefLabel) });
            row.appendChild(label);

            // breadcrumb path (ancestry), excluding the node itself
            if (res.pathLabels.length > 1) {
                var crumb = res.pathLabels.slice(0, -1).join(' › ');
                row.appendChild(el('span', { class: 'smf-tb-crumb', text: '  ' + crumb }));
            }

            row.addEventListener('click', function () { self.select(res.uri); });
            li.appendChild(row);
            ul.appendChild(li);
            self.visibleItems.push({ uri: res.uri, li: li, row: row, hasKids: false });
        });
        this.treeEl.appendChild(ul);
        this.refreshActive(0);
    };

    /** Expand all ancestors of a uri, switch to tree mode, and focus it. */
    SMFTreeWidget.prototype.locate = function (uri) {
        var path = resolvePath(this.index, uri);
        for (var i = 0; i < path.length - 1; i++) { this.expanded[path[i].uri] = true; }
        this.input.value = '';
        this.renderTree();
        this.focusUri(uri);
        this.input.focus();
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
                    } else {
                        var p = this.index.parentOf[it.uri];
                        if (p) { e.preventDefault(); this.focusUri(p); }
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

    // ---- Selection ------------------------------------------------------

    SMFTreeWidget.prototype.select = function (uri) {
        var c = this.index.concepts[uri];
        if (!c) { return; }
        var path = resolvePath(this.index, uri);
        var value = buildOutputValue(c);
        if (typeof this.opts.onSelect === 'function') {
            this.opts.onSelect({
                prefLabel: c.prefLabel,
                uri: c.uri,
                path: path.map(function (p) { return { prefLabel: p.prefLabel, uri: p.uri }; }),
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
        // browser widget
        Widget: SMFTreeWidget,
        /**
         * Convenience mount: create a widget bound to a container + JSON url.
         * @returns {SMFTreeWidget}
         */
        mount: function (opts) {
            var w = new SMFTreeWidget(opts);
            w.load();
            return w;
        }
    };
});
