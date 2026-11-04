// Data grids (AG Grid + React) are loaded only on pages that have a table marked data-grid.
// public/js/partial.js fires "partial:updated" after it swaps part of the page, so new tables get a grid too.
function needsGrid() {
    return document.querySelector('table[data-grid]') || document.querySelector('.grid-host');
}

let loading = null;
function load() {
    return (loading ??= import('./grid.jsx'));
}

// If the grid code cannot load, drop the "js" marker so the plain table shows instead of the placeholder.
function mount() {
    load()
        .then((m) => m.mountGrids())
        .catch(() => document.documentElement.classList.remove('js'));
}

if (needsGrid()) {
    mount();
}

document.addEventListener('partial:updated', () => {
    if (needsGrid()) {
        mount();
    }
});
