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

    var ROW_HEIGHT = 56;  // base row height (two lines of text; longer text is clipped)
    var BOTTOM_GAP = 56;  // card padding + page margin under the grid
    var CHROME = 93;      // column header (49px) + AG Grid pager bar (42px) + borders (2px)
    var MIN_ROWS = 3;

    document.documentElement.classList.add('js');

    // Whole rows only, stretched a little to fill the box exactly; a short list gets a grid as tall as its rows.
    // pageSize (optional) is for a small grid inside a page (dashboard cards, a company's contacts): a fixed number of rows per
    // page, as tall as its rows, no stretching to the window.
    window.crcGridLayout = function (el, total, pageSize) {
        if (pageSize) {
            return { pageSize: pageSize, rowHeight: ROW_HEIGHT, height: Math.max(1, Math.min(total, pageSize)) * ROW_HEIGHT + CHROME };
        }
        var top = el.getBoundingClientRect().top + window.scrollY;
        var available = window.innerHeight - top - BOTTOM_GAP - CHROME;   // the footer is below the fold: not counted
        var capacity = Math.max(MIN_ROWS, Math.floor(available / ROW_HEIGHT));

        if (total <= capacity) {
            return { pageSize: capacity, rowHeight: ROW_HEIGHT, height: Math.max(total, 1) * ROW_HEIGHT + CHROME };
        }
        var rowHeight = Math.max(ROW_HEIGHT, Math.floor(available / capacity));
        return { pageSize: capacity, rowHeight: rowHeight, height: capacity * rowHeight + CHROME };
    };

    // Give a placeholder the height its grid will have.
    window.crcFitSkeleton = function (el) {
        el.style.height = window.crcGridLayout(el, Number(el.getAttribute('data-total')) || 0, Number(el.getAttribute('data-page-size')) || 0).height + 'px';
    };

    // Safety net: if the grid code never arrives (network error, blocked file), show the plain table instead of
    // leaving the placeholder up for ever.
    setTimeout(function () {
        if (document.querySelector('.grid-skeleton')) { document.documentElement.classList.remove('js'); }
    }, 8000);
})();
