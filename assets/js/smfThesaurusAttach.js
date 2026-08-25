/**
 * smfThesaurusAttach.js
 * ----------------------------------------------------------------------
 * CollectiveAccess integration glue for the arborescent thesaurus picker.
 *
 * Augments (does NOT replace) the stock InformationService autocomplete field
 * for EVERY metadata element served by the SMFThesaurus InformationService
 * plugin (domaine/epoque/fonctions/useMethod and any future one). For each such
 * IS input it injects a "Parcourir l'arbre" button; clicking it opens a modal
 * hosting smfThesaurusBrowser. On selection the widget writes the EXACT
 * `prefLabel||uri` string into the attribute's HIDDEN input and mirrors the
 * label into the visible autocomplete input, then fires `change`.
 *
 * The element -> thesaurus wiring is data-driven: the plugin hook publishes
 *   window.SMF_THESAURUS_MAP  = { "<element_id>": "<thesaurus_id>", ... }
 *   window.SMF_THESAURUS_BASE_URL = base URL of the static JSON stores
 * We scan for inputs whose id looks like `infoservice_<element_id>_autocomplete*`,
 * extract <element_id>, and only decorate it if it is present in the map. Every
 * thesaurus (th285 included) opens with the SAME "tout client + IndexedDB"
 * ClientProvider: the static store JSON is downloaded once, persisted in
 * IndexedDB, and reloaded from there on subsequent opens (freshness checked by a
 * HEAD/Last-Modified probe inside the provider). No server browse endpoint.
 *
 * Loaded globally by museesDeFrancePlugin::hookRenderMenuBar. It is a no-op on
 * pages without a matching SMFThesaurus field, so it is safe to load everywhere.
 * Idempotent + MutationObserver for dynamically added attribute-value rows.
 *
 * @package museesDeFrance
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * ----------------------------------------------------------------------
 */
(function () {
    'use strict';

    var LABELS = {
        open: 'Parcourir l’arbre',
        title: 'Choisir un terme du thésaurus',
        close: 'Fermer',
        loading: 'Chargement du thésaurus…'
    };

    if (typeof window.SMFThesaurusBrowser === 'undefined') {
        // Core component not loaded; nothing we can do.
        if (window.console) { console.warn('[SMF] smfThesaurusBrowser.js not loaded; tree picker disabled.'); }
        return;
    }

    /** element_id -> thesaurus_id map published by the plugin hook. */
    function thesaurusMap() {
        return (window.SMF_THESAURUS_MAP && typeof window.SMF_THESAURUS_MAP === 'object')
            ? window.SMF_THESAURUS_MAP : {};
    }

    /** Thesaurus id for an element_id, or null if not an SMFThesaurus element. */
    function thesaurusForElement(elementId) {
        var map = thesaurusMap();
        var t = map[String(elementId)];
        return (typeof t === 'string' && /^th[0-9]+$/.test(t)) ? t : null;
    }

    /** Base URL for the flat JSON stores (client mode), e.g. ".../assets/thesauri". */
    function storeBaseUrl() {
        if (window.SMF_THESAURUS_BASE_URL) { return String(window.SMF_THESAURUS_BASE_URL).replace(/\/+$/, ''); }
        // Derive from this script's own src as a fallback.
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            var m = src.match(/^(.*\/app\/plugins\/museesDeFrance\/assets\/)js\/smfThesaurusAttach\.js/);
            if (m) { return m[1] + 'thesauri'; }
        }
        return '/gestion2/app/plugins/museesDeFrance/assets/thesauri';
    }

    /** Static JSON store URL for a thesaurus id. */
    function storeUrlFor(thesaurusId) {
        return storeBaseUrl() + '/' + thesaurusId + '.json';
    }

    function escapeRe(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    /**
     * Find the hidden input paired with a visible autocomplete input, for a
     * given element id. Visible id: infoservice_<eid>_autocomplete<SUFFIX>.
     */
    function findHiddenFor(visibleInput, elementId) {
        var vid = visibleInput.id || '';
        var re1 = new RegExp('^infoservice_' + escapeRe(elementId) + '_autocomplete(.*)$');
        var m = vid.match(re1);
        var suffix = m ? m[1] : '';
        var scope = visibleInput.closest('.caInformationServiceDetail, .attributeListItem, .roundedRel, form') || document;
        var hiddens = scope.querySelectorAll('input[type=hidden]');
        for (var i = 0; i < hiddens.length; i++) {
            var h = hiddens[i];
            var id = h.id || '';
            var name = h.name || '';
            var re = new RegExp(escapeRe(elementId) + '_' + escapeRe(suffix) + '$');
            if ((re.test(id) || re.test(name)) && id.indexOf('autocomplete') === -1) {
                return h;
            }
        }
        var wrap = document.getElementById('infoservice_' + elementId + '_input' + suffix);
        if (wrap) {
            var sib = wrap.querySelector('input[type=hidden]');
            if (sib) { return sib; }
        }
        return null;
    }

    /** Write value into CA hidden input + mirror label, then fire change. */
    function applySelection(visibleInput, hiddenInput, sel) {
        if (hiddenInput) {
            hiddenInput.value = sel.value;           // `prefLabel||uri`
            fireChange(hiddenInput);
        }
        if (visibleInput) {
            visibleInput.value = sel.prefLabel;      // human-readable mirror
            fireChange(visibleInput);
        }
    }

    function fireChange(node) {
        var ev;
        try { ev = new Event('change', { bubbles: true }); }
        catch (e) { ev = document.createEvent('HTMLEvents'); ev.initEvent('change', true, false); }
        node.dispatchEvent(ev);
        if (window.jQuery) { window.jQuery(node).trigger('change'); }
    }

    // ---- Modal ----------------------------------------------------------

    var openModal = null;

    function openBrowser(visibleInput, hiddenInput, thesaurusId) {
        closeBrowser();
        var backdrop = document.createElement('div');
        backdrop.className = 'smf-tb-modal-backdrop';
        var modal = document.createElement('div');
        modal.className = 'smf-tb-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', LABELS.title);

        var titleBar = document.createElement('div');
        titleBar.className = 'smf-tb-modal-title';
        var t = document.createElement('span'); t.textContent = LABELS.title;
        var x = document.createElement('button');
        x.type = 'button'; x.className = 'smf-tb-modal-close';
        x.textContent = '×'; x.setAttribute('aria-label', LABELS.close);
        x.addEventListener('click', closeBrowser);
        titleBar.appendChild(t); titleBar.appendChild(x);

        var host = document.createElement('div');

        modal.appendChild(titleBar);
        modal.appendChild(host);
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        backdrop.addEventListener('mousedown', function (e) { if (e.target === backdrop) { closeBrowser(); } });

        openModal = backdrop;

        var onSelect = function (sel) {
            applySelection(visibleInput, hiddenInput, sel);
            closeBrowser();
            if (visibleInput) { visibleInput.focus(); }
        };

        host.textContent = '';

        // Single "tout client + IndexedDB" path for EVERY thesaurus: the provider
        // downloads the static store once, persists it in IndexedDB, and reloads
        // from there on later opens (freshness via a HEAD/Last-Modified probe).
        var provider = new window.SMFThesaurusBrowser.ClientProvider({
            thesaurus: thesaurusId,
            url: storeUrlFor(thesaurusId)
        });
        var w = new window.SMFThesaurusBrowser.Widget({
            container: host,
            provider: provider,
            onSelect: onSelect,
            onClose: closeBrowser
        });
        w.load();
    }

    function closeBrowser() {
        if (openModal && openModal.parentNode) { openModal.parentNode.removeChild(openModal); }
        openModal = null;
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openModal) { closeBrowser(); }
    });

    // ---- Button injection ----------------------------------------------

    // Matches infoservice_<element_id>_autocomplete<suffix>; captures the id.
    var VISIBLE_ID_RE = /^infoservice_([0-9]+)_autocomplete/;

    function decorate(visibleInput) {
        if (!visibleInput || visibleInput.getAttribute('data-smf-tb') === '1') { return; }
        var vid = visibleInput.id || '';
        // Skip un-substituted CA templates (literal {n}).
        if (vid.indexOf('{n}') !== -1) { return; }
        var m = vid.match(VISIBLE_ID_RE);
        if (!m) { return; }
        var elementId = m[1];
        var thesaurusId = thesaurusForElement(elementId);
        if (!thesaurusId) { return; }   // not an SMFThesaurus element -> no-op

        visibleInput.setAttribute('data-smf-tb', '1');

        var hidden = findHiddenFor(visibleInput, elementId);

        var btn = document.createElement('a');
        btn.href = '#';
        btn.className = 'smf-tb-open-btn';
        btn.textContent = LABELS.open;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var h = hidden || findHiddenFor(visibleInput, elementId);
            openBrowser(visibleInput, h, thesaurusId);
        });

        if (visibleInput.nextSibling) {
            visibleInput.parentNode.insertBefore(btn, visibleInput.nextSibling);
        } else {
            visibleInput.parentNode.appendChild(btn);
        }
    }

    function scan(rootEl) {
        var root = rootEl || document;
        var inputs = root.querySelectorAll('input[id^="infoservice_"][id*="_autocomplete"]');
        for (var i = 0; i < inputs.length; i++) { decorate(inputs[i]); }
    }

    function init() {
        // Nothing to do if the map is empty (no SMFThesaurus elements configured).
        scan(document);
        if (window.MutationObserver) {
            var mo = new MutationObserver(function (muts) {
                for (var i = 0; i < muts.length; i++) {
                    if (muts[i].addedNodes && muts[i].addedNodes.length) { scan(document); break; }
                }
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
