/**
 * CSP-safe dynamic styling helper.
 *
 * The app's Content-Security-Policy locks style-src-attr down to a per-request
 * nonce, and that directive has no effect on the `style=""` attribute itself —
 * nonces only cover <style> *elements*. So any `el.style.x = ...` from JS gets
 * blocked in the console exactly like an inline style="" in markup does.
 *
 * This helper keeps a single nonce'd <style> element in <head> and writes
 * rules into it via the CSSOM (insertRule/deleteRule) instead of touching
 * element.style directly. Each managed element gets a small, page-unique
 * [data-ars-id] attribute selector so rules can be set/updated per element,
 * including for values that are only known at runtime (colors, widths, etc.)
 *
 * Usage:
 *   ArsStyle.setVars(el, { '--icon-bg': el.dataset.bg, '--icon-color': el.dataset.color });
 *   ArsStyle.setVars(el, { width: pct + '%', background: color });
 */
(function (window, document) {
    'use strict';

    function getPageNonce() {
        if (window.__ARS_CSP_NONCE) return window.__ARS_CSP_NONCE;
        var tagged = document.querySelector('script[nonce]') || document.querySelector('style[nonce]');
        return tagged ? (tagged.nonce || tagged.getAttribute('nonce') || '') : '';
    }

    var sheetEl = null;
    var ruleIndexBySelector = {};
    var uidCounter = 0;

    function getSheet() {
        if (sheetEl && sheetEl.isConnected) return sheetEl.sheet;
        sheetEl = document.getElementById('ars-dynamic-style');
        if (!sheetEl) {
            sheetEl = document.createElement('style');
            sheetEl.id = 'ars-dynamic-style';
            sheetEl.setAttribute('nonce', getPageNonce());
            document.head.appendChild(sheetEl);
        }
        return sheetEl.sheet;
    }

    function declarationsToText(props) {
        return Object.keys(props).map(function (prop) {
            return prop + ':' + props[prop] + ';';
        }).join('');
    }

    // Inserts (or replaces) a rule for `selectorText` with the given
    // property -> value map, in the shared managed stylesheet.
    function setRule(selectorText, props) {
        var sheet = getSheet();
        if (!sheet) return;
        if (Object.prototype.hasOwnProperty.call(ruleIndexBySelector, selectorText)) {
            try { sheet.deleteRule(ruleIndexBySelector[selectorText]); } catch (e) { /* already gone */ }
        }
        try {
            var idx = sheet.insertRule(selectorText + '{' + declarationsToText(props) + '}', sheet.cssRules.length);
            ruleIndexBySelector[selectorText] = idx;
        } catch (e) {
            // Invalid selector/value for this browser — fail quietly rather
            // than breaking the page.
        }
    }

    // Applies CSS custom properties / declarations to a single element,
    // reading/writing at runtime, without ever touching element.style.
    function setVars(el, props) {
        if (!el) return;
        var key = el.getAttribute('data-ars-id');
        if (!key) {
            key = 'a' + (++uidCounter);
            el.setAttribute('data-ars-id', key);
        }
        setRule('[data-ars-id="' + key + '"]', props);
    }

    window.ArsStyle = {
        getPageNonce: getPageNonce,
        setRule: setRule,
        setVars: setVars,
    };
})(window, document);
