/*
 * Left sidebar (resources/views/partials/sidebar.blade.php). It sits outside <main>, so it survives in-place
 * navigation (partial.js); this marks the link of the ticket list being shown, after every navigation.
 */
(function () {
    function markActive() {
        var menu = document.getElementById('side-menu');
        if (!menu) { return; }
        var onTickets = /^\/tickets\/?$/.test(location.pathname);
        var status = new URLSearchParams(location.search).get('status') || 'open';   // the list's default filter
        menu.querySelectorAll('[data-side-status]').forEach(function (a) {
            var active = onTickets && a.getAttribute('data-side-status') === status;
            a.classList.toggle('active', active);
            if (active) { a.setAttribute('aria-current', 'page'); } else { a.removeAttribute('aria-current'); }
        });
    }

    document.addEventListener('partial:updated', markActive);
    window.addEventListener('popstate', function () { setTimeout(markActive, 0); });
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', markActive); } else { markActive(); }
})();
