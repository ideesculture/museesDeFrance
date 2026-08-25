/**
 * smfThesaurusAttach.js
 * ----------------------------------------------------------------------
 * CollectiveAccess integration glue for the arborescent thesaurus picker.
 *
 * Augments (does NOT replace) the stock InformationService autocomplete field
 * for the "domaine" element (element_id 174, service SMFThesaurus) in the
 * object editor: it injects a "Parcourir l'arbre" button next to each IS
 * input. Clicking it opens a modal hosting smfThesaurusBrowser. On selection
 * the widget writes the EXACT `prefLabel||uri` string into the attribute's
 * HIDDEN input and mirrors the label into the visible autocomplete input,
 * then fires the `change` event CollectiveAccess listens for.
 *
 * Loaded globally by museesDeFrancePlugin::hookRenderMenuBar (same mechanism
 * already used for delimiteur.js/css). It is a no-op on pages that contain no
 * element-174 InformationService field, so it is safe to load everywhere.
 *
 * IMPORTANT (DOM contract — see rapport, must be visually verified in a
 * browser): CollectiveAccess renders, per attribute value:
 *   - a visible text input   id="infoservice_174_autocomplete<N>"
 *     (class from htmlFormElement, e.g. "lookupBg")
 *   - a hidden input         id="<fieldNamePrefix>174_<N>"  name="...174_<N>"
 *     -> this is the field parseValue() reads; we write `prefLabel||uri` here
 *   - a "More" link          class="caInformationServiceMoreLink"
 * The visible input id is the stable anchor. The hidden input is found as the
 * input[type=hidden] sibling whose id ends with the same "_<N>" suffix.
 *
 * @package museesDeFrance
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * ----------------------------------------------------------------------
 */
(function () {
    'use strict';

    var ELEMENT_ID = '174';          // "domaine"
    var THESAURUS_URL = null;         // resolved lazily (see resolveUrl)
    var LABELS = {
        open: 'Parcourir l’arbre',
        title: 'Choisir un terme du thésaurus',
        close: 'Fermer'
    };

    if (typeof window.SMFThesaurusBrowser === 'undefined') {
        // Core component not loaded; nothing we can do.
        if (window.console) { console.warn('[SMF] smfThesaurusBrowser.js not loaded; tree picker disabled.'); }
        return;
    }

    /**
     * Resolve the JSON url. The plugin is served under
     *   <root>/app/plugins/museesDeFrance/assets/thesauri/th294.json
     * We build it from the current script location if possible, else fall back
     * to a root-relative path that works for the /gestion2 alias.
     */
    function resolveUrl() {
        if (THESAURUS_URL) { return THESAURUS_URL; }
        // If the plugin exposed a base via a global, prefer it.
        if (window.SMF_THESAURUS_URL) { THESAURUS_URL = window.SMF_THESAURUS_URL; return THESAURUS_URL; }
        // Try to derive from this script's own src.
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            var m = src.match(/^(.*\/app\/plugins\/museesDeFrance\/assets\/)js\/smfThesaurusAttach\.js/);
            if (m) { THESAURUS_URL = m[1] + 'thesauri/th294.json'; return THESAURUS_URL; }
        }
        // Fallback: absolute path via the gestion2 alias / providence root.
        // (Adjust if the app is mounted under a different base.)
        THESAURUS_URL = '/gestion2/app/plugins/museesDeFrance/assets/thesauri/th294.json';
        return THESAURUS_URL;
    }

    /** Find the hidden input paired with a visible autocomplete input. */
    function findHiddenFor(visibleInput) {
        // visible id: infoservice_174_autocomplete<SUFFIX>
        var vid = visibleInput.id || '';
        var m = vid.match(/^infoservice_174_autocomplete(.*)$/);
        var suffix = m ? m[1] : '';
        // Search the enclosing attribute block for a hidden input ending with
        // "174_<suffix>" (the fieldNamePrefix precedes "174").
        var scope = visibleInput.closest('.caInformationServiceDetail, .attributeListItem, .roundedRel, form') || document;
        var hiddens = scope.querySelectorAll('input[type=hidden]');
        for (var i = 0; i < hiddens.length; i++) {
            var h = hiddens[i];
            var id = h.id || '';
            var name = h.name || '';
            // Match ...174_<suffix> but NOT the _autocomplete input.
            var re = new RegExp('174_' + escapeRe(suffix) + '$');
            if ((re.test(id) || re.test(name)) && id.indexOf('autocomplete') === -1) {
                return h;
            }
        }
        // Last resort: the input immediately following the wrapper div.
        var wrap = document.getElementById('infoservice_174_input' + suffix);
        if (wrap) {
            var sib = wrap.querySelector('input[type=hidden]');
            if (sib) { return sib; }
        }
        return null;
    }

    function escapeRe(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

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
        // Also nudge jQuery listeners CA may have bound.
        if (window.jQuery) { window.jQuery(node).trigger('change'); }
    }

    // ---- Modal ----------------------------------------------------------

    var openModal = null;

    function openBrowser(visibleInput, hiddenInput) {
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

        window.SMFThesaurusBrowser.mount({
            container: host,
            url: resolveUrl(),
            onSelect: function (sel) {
                applySelection(visibleInput, hiddenInput, sel);
                closeBrowser();
                if (visibleInput) { visibleInput.focus(); }
            },
            onClose: closeBrowser
        });
    }

    function closeBrowser() {
        if (openModal && openModal.parentNode) { openModal.parentNode.removeChild(openModal); }
        openModal = null;
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openModal) { closeBrowser(); }
    });

    // ---- Button injection ----------------------------------------------

    function decorate(visibleInput) {
        if (!visibleInput || visibleInput.getAttribute('data-smf-tb') === '1') { return; }
        // Only the "template" input (with literal {n}) should be skipped; real
        // instances have {n} substituted. Skip un-substituted templates.
        if ((visibleInput.id || '').indexOf('{n}') !== -1) { return; }
        visibleInput.setAttribute('data-smf-tb', '1');

        var hidden = findHiddenFor(visibleInput);

        var btn = document.createElement('a');
        btn.href = '#';
        btn.className = 'smf-tb-open-btn';
        btn.textContent = LABELS.open;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            // Re-resolve hidden at click time (DOM may have changed).
            var h = hidden || findHiddenFor(visibleInput);
            openBrowser(visibleInput, h);
        });

        // Insert right after the visible input (before the "More" link if any).
        if (visibleInput.nextSibling) {
            visibleInput.parentNode.insertBefore(btn, visibleInput.nextSibling);
        } else {
            visibleInput.parentNode.appendChild(btn);
        }
    }

    function scan(rootEl) {
        var root = rootEl || document;
        var inputs = root.querySelectorAll('input[id^="infoservice_174_autocomplete"]');
        for (var i = 0; i < inputs.length; i++) { decorate(inputs[i]); }
    }

    function init() {
        scan(document);
        // CA adds attribute value rows dynamically ("Add value"); watch for them.
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
