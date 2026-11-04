/*
 * Partial page refresh (no framework, no build step).
 *
 * Forms and same-page links are sent with fetch(); the server answers with its normal full page, and only the
 * areas marked data-region="name" that actually changed are swapped in the current page. The header, the filter
 * bars, anything the user is typing in and every untouched area stay exactly as they are.
 *
 * Opt out with data-no-ajax on a form or link. Without JavaScript everything still works as plain pages.
 */
(function () {
    'use strict';

    // ---------- helpers ----------

    function regionsOf(root) {
        var map = {};
        root.querySelectorAll('[data-region]').forEach(function (el) { map[el.getAttribute('data-region')] = el; });
        return map;
    }

    // Scripts that arrive through innerHTML do not run: replace them with fresh ones so pop-ups and the
    // template picker are wired up again inside a swapped area.
    function runScripts(container) {
        container.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            s.text = old.textContent;
            old.replaceWith(s);
        });
    }

    function sameOrigin(url) { return url.origin === location.origin; }

    function setBusy(on) { document.documentElement.classList.toggle('ajax-busy', on); }

    function resetSubmit(form) {
        delete form.dataset.busy;
        form.querySelectorAll('[data-submit]').forEach(function (b) {
            b.disabled = false;
            if (b.dataset.label) { b.textContent = b.dataset.label; }
        });
    }

    function showErrorPage(html) {
        document.open();
        document.write(html);
        document.close();
    }

    // ---------- applying a response ----------

    // Returns true when the page was updated in place.
    function applyDocument(doc, opts) {
        var current = regionsOf(document);
        var incoming = regionsOf(doc);
        var keys = Object.keys(incoming);
        var swapped = [];

        var sameLayout = !!document.querySelector('header') === !!doc.querySelector('header');
        if (!sameLayout) { return false; }

        // Moving to a different page swaps the whole content area; staying on the page swaps only what changed.
        var compatible = !opts.crossPage && keys.length > 0 && keys.length === Object.keys(current).length
            && keys.every(function (k) { return current[k]; });

        if (compatible) {
            keys.forEach(function (k) {
                // Unchanged: do not touch it. A data grid replaces its raw table, so compare with the server HTML it was built from.
                var shown = current[k]._srcHtml !== undefined ? current[k]._srcHtml : current[k].innerHTML;
                if (shown === incoming[k].innerHTML) { return; }
                current[k].innerHTML = incoming[k].innerHTML;
                current[k]._srcHtml = incoming[k].innerHTML;
                runScripts(current[k]);
                swapped.push(current[k]);
            });
        } else {
            // A different kind of page (for example a redirect elsewhere): swap the content area only.
            var main = document.querySelector('main');
            var newMain = doc.querySelector('main');
            if (!main || !newMain) { return false; }
            main.innerHTML = newMain.innerHTML;
            runScripts(main);
            swapped.push(main);
        }

        // Pop-ups that live outside the swapped areas: refresh the one that was submitted (fresh fields, or the
        // same fields with their errors), then open or close it the way the server decided.
        if (opts.dialogId) {
            doc.querySelectorAll('dialog.modal').forEach(function (nd) {
                var cd = document.getElementById(nd.id);
                if (!cd || swapped.some(function (r) { return r.contains(cd); })) { return; }
                var cf = cd.querySelector('[data-modal-form]');
                var nf = nd.querySelector('[data-modal-form]');
                if (cf && nf && cf.innerHTML !== nf.innerHTML) { cf.innerHTML = nf.innerHTML; }
            });
            var submitted = document.getElementById(opts.dialogId);
            var fresh = doc.getElementById(opts.dialogId);
            if (submitted && typeof submitted.showModal === 'function') {
                if (fresh && fresh.hasAttribute('data-open')) {
                    if (!submitted.open) { submitted.showModal(); }
                } else if (submitted.open) {
                    submitted.close();
                }
            }
        }
        return true;
    }

    // ---------- sending ----------

    var seq = 0;

    function send(url, init, opts) {
        var mine = ++seq;
        setBusy(true);
        return fetch(url, init).then(function (res) {
            if (opts.latestOnly && mine !== seq) { return; }   // a newer search was sent meanwhile: drop this reply
            if (res.status === 419) { location.reload(); return; }   // session/CSRF expired
            return res.text().then(function (html) {
                if (opts.latestOnly && mine !== seq) { return; }
                if (!res.ok) { showErrorPage(html); return; }
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var finalUrl = res.url || String(url);
                opts.crossPage = !!opts.forceMain || new URL(finalUrl, location.href).pathname !== location.pathname;
                if (!applyDocument(doc, opts)) { location.href = finalUrl; return; }
                if (doc.title) { document.title = doc.title; }
                if (finalUrl !== location.href && opts.history !== false) {
                    var state = { partial: true };
                    if (opts.push) { history.pushState(state, '', finalUrl); } else { history.replaceState(state, '', finalUrl); }
                }
                if (opts.crossPage) { window.scrollTo(0, 0); }
                document.dispatchEvent(new CustomEvent('partial:updated'));   // lets the data grid mount on new tables
            });
        }).finally(function () { setBusy(false); });
    }

    // ---------- forms ----------

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || e.defaultPrevented) { return; }
        if (form.hasAttribute('data-no-ajax') || (form.target && form.target !== '_self')) { return; }
        var action = new URL(form.getAttribute('action') || location.href, location.href);
        if (!sameOrigin(action) || !window.fetch || !window.FormData) { return; }

        e.preventDefault();
        var isGet = (form.getAttribute('method') || 'GET').toUpperCase() === 'GET';
        if (!isGet && form.dataset.busy) { return; }   // one click, one record (searches may overlap: the newest wins)
        form.dataset.busy = '1';

        var data;
        try { data = new FormData(form, e.submitter); } catch (err) { data = new FormData(form); }
        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        var init = { credentials: 'same-origin', cache: 'no-cache' };
        var url = action;
        if (method === 'GET') {
            action.search = new URLSearchParams(data).toString();
        } else {
            init.method = 'POST';
            init.body = data;
        }
        var dialog = form.closest('dialog');

        var auto = !!form.dataset.auto; delete form.dataset.auto;   // auto-search replaces the history entry instead of piling up new ones
        send(url, init, { push: method === 'GET' && !auto, latestOnly: method === 'GET', dialogId: dialog ? dialog.id : null })
            .catch(function () { form.submit(); })       // network trouble: fall back to the normal page
            .finally(function () { resetSubmit(form); });
    });

    // ---------- auto search ----------
    // Filter bars (GET forms with class "filters") apply as you type or change a field: no Search button needed.

    var typing = null;
    function autoSubmit(form) { form.dataset.auto = '1'; if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); } }

    document.addEventListener('input', function (e) {
        var el = e.target, form = el.form;
        if (!form || !form.matches('form.filters[method="GET" i]') || el.tagName !== 'INPUT') { return; }
        if (el.type === 'checkbox' || el.type === 'date' || el.type === 'radio') { return; }   // handled by "change"
        clearTimeout(typing);
        typing = setTimeout(function () { autoSubmit(form); }, 300);
    });

    document.addEventListener('change', function (e) {
        var el = e.target, form = el.form;
        if (!form || !form.matches('form.filters[method="GET" i]')) { return; }
        if (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'date' || el.type === 'radio') {
            clearTimeout(typing);
            autoSubmit(form);
        }
    });

    // ---------- links ----------

    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a') : null;
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }

        // "+ New ..." / "Edit" links open their pop-up (the href is the no-JavaScript fallback).
        var modalId = a.getAttribute('data-modal');
        if (modalId) {
            var dialog = document.getElementById(modalId);
            if (dialog && typeof dialog.showModal === 'function') {
                e.preventDefault();
                if (!dialog.open) { dialog.showModal(); }
            }
            return;
        }

        // Every other link on this site loads in place: same page (pagination, filters) refreshes only the changed
        // areas, another page swaps just the content area. The header and the script stay.
        if (!a.getAttribute('href') || a.target || a.hasAttribute('download') || a.hasAttribute('data-no-ajax')) { return; }
        var url = new URL(a.href, location.href);
        if (!sameOrigin(url) || !window.fetch) { return; }
        if (url.pathname === location.pathname && url.search === location.search && url.hash) { return; }   // jump to #anchor

        e.preventDefault();
        send(url, { credentials: 'same-origin', cache: 'no-cache' }, { push: true, dialogId: null })
            .catch(function () { location.href = url.href; });
    });

    // Back/forward: load that address in place too (the browser has already changed the address bar).
    history.replaceState({ partial: true }, '', location.href);
    window.addEventListener('popstate', function (e) {
        if (!e.state || !e.state.partial) { return; }
        var url = location.href;
        send(url, { credentials: 'same-origin', cache: 'no-cache' }, { push: false, history: false, forceMain: true, dialogId: null })
            .catch(function () { location.reload(); });
    });
})();
