/*
 * Loaded synchronously in <head>, before the page is painted (see layouts/app.blade.php).
 *
 *  1. Marks the page as script-enabled (<html class="js">). CSS then hides the plain server-rendered table of a data grid
 *     (`html.js .grid-skeleton ~ table[data-grid]`) so the full, unpaginated rows are never painted.
 *  2. Holds the ONE copy of the grid sizing rule. The placeholder uses it immediately, and resources/js/grid.jsx uses it
 *     when the real grid mounts, so both have exactly the same height (no layout jump).
 */
(function () {
    'use strict';

    var PAGE_SIZE = 6;    // every grid shows 6 rows per page
    var ROW_HEIGHT = 56;  // row height (two lines of text; longer text is clipped)
    var CHROME = 93;      // column header (49px) + AG Grid pager bar (42px) + borders (2px)

    document.documentElement.classList.add('js');

    // A grid is as tall as its rows on the page (at most 6), so a short list gets a short grid.
    window.crcGridLayout = function (total) {
        return { pageSize: PAGE_SIZE, rowHeight: ROW_HEIGHT, height: Math.max(1, Math.min(total, PAGE_SIZE)) * ROW_HEIGHT + CHROME };
    };

    // Give a placeholder the height its grid will have.
    window.crcFitSkeleton = function (el) {
        el.style.height = window.crcGridLayout(Number(el.getAttribute('data-total')) || 0).height + 'px';
    };

    // Safety net: if the grid code never arrives (network error, blocked file), show the plain table instead of
    // leaving the placeholder up for ever.
    setTimeout(function () {
        if (document.querySelector('.grid-skeleton')) { document.documentElement.classList.remove('js'); }
    }, 8000);
})();
