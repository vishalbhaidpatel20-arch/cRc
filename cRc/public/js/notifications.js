/*
 * Header notification bell (partials/notification-bell.blade.php).
 *
 *  - Opens/closes the dropdown (click the bell; click outside or Escape closes it).
 *  - Keeps the unread badge and the list current: polls /notifications/feed every 20 s while the tab is visible, when the
 *    tab becomes visible again, and after every in-place page update (partial.js fires "partial:updated").
 *  - Shows a small toast when a NEW unread notification arrives.
 *  - "Mark all as read" posts in place, no page change.
 * Without JavaScript the bell is a link to the full /notifications page.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-bell]');
    if (!root || !window.fetch) { return; }

    var toggle = root.querySelector('[data-bell-toggle]');
    var panel = root.querySelector('[data-bell-panel]');
    var countEl = root.querySelector('[data-bell-count]');
    var listEl = root.querySelector('[data-bell-list]');
    var toast = document.querySelector('[data-bell-toast]');
    var feedUrl = root.getAttribute('data-feed-url');
    var readUrl = root.getAttribute('data-read-url');
    var seenId;          // newest unread id already known (undefined until the first poll: no toast for old items)
    var toastTimer = null;
    var pollTimer = null;
    var pending = null;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function isOpen() { return !panel.hidden; }

    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function showToast(text) {
        if (!toast) { return; }
        toast.textContent = text;
        toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.hidden = true; }, 6000);
    }

    function render(data) {
        var n = data.unread || 0;
        countEl.textContent = n > 99 ? '99+' : String(n);
        countEl.hidden = n === 0;
        if (listEl && typeof data.html === 'string' && listEl.innerHTML !== data.html) { listEl.innerHTML = data.html; }
        document.title = (n > 0 ? '(' + (n > 99 ? '99+' : n) + ') ' : '') + document.title.replace(/^\(\d+\+?\)\s*/, '');

        if (seenId !== undefined && data.newest_unread_id && data.newest_unread_id !== seenId && n > 0) {
            showToast(data.newest_unread_text || 'You have a new notification');
        }
        seenId = data.newest_unread_id || null;
    }

    function refresh() {
        if (pending) { return pending; }
        pending = fetch(feedUrl, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) { if (data) { render(data); } })
            .catch(function () { /* offline or logged out: try again at the next poll */ })
            .finally(function () { pending = null; });
        return pending;
    }

    function startPolling() {
        clearInterval(pollTimer);
        pollTimer = setInterval(function () { if (!document.hidden) { refresh(); } }, 20000);
    }

    toggle.addEventListener('click', function (e) {
        e.preventDefault();               // the link is only the no-JavaScript fallback
        setOpen(!isOpen());
        if (isOpen()) { refresh(); }
    });

    document.addEventListener('click', function (e) {
        if (!isOpen()) { return; }
        var link = e.target.closest ? e.target.closest('a') : null;
        if (link && panel.contains(link)) { setOpen(false); return; }          // going to a ticket / the full list
        if (!root.contains(e.target)) { setOpen(false); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) { setOpen(false); toggle.focus(); }
    });

    root.querySelector('[data-bell-mark-read]').addEventListener('click', function () {
        fetch(readUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } })
            .then(function () { return refresh(); });
    });

    document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });

    var debounce = null;
    document.addEventListener('partial:updated', function () {   // a page changed in place (an assignment, a status change, ...)
        clearTimeout(debounce);
        debounce = setTimeout(refresh, 300);
    });

    refresh();
    startPolling();
})();
