import React from 'react';
import { createRoot } from 'react-dom/client';
import { AgGridReact } from 'ag-grid-react';
import { AllCommunityModule, ModuleRegistry } from 'ag-grid-community';

ModuleRegistry.registerModules([AllCommunityModule]);

// host element -> { root, columns, flex, rows }, so a grid can be re-laid-out on resize and unmounted when partial.js
// swaps its area out.
const grids = new Map();

function Cell({ html, text }) {
    // The HTML comes from our own server-rendered table cells (already escaped by Blade).
    return (
        <span
            title={text}
            style={{ display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden', lineHeight: 1.35 }}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

function Grid({ columns, heads, flex, rows, pageSize, rowHeight }) {
    const columnDefs = React.useMemo(
        () =>
            columns.map((title, i) => ({
                colId: 'c' + i,
                headerName: title,
                valueGetter: (p) => p.data.text[i],
                cellRenderer: (p) => <Cell html={p.data.html[i]} text={p.data.text[i]} />,
                sortable: title !== '' && !heads[i].hasAttribute('data-nosort'),
                comparator: (x, y) => String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' }),
                suppressHeaderMenuButton: true,
                flex: flex[i],
                minWidth: title === '' ? 150 : 110,
                cellStyle: { display: 'flex', alignItems: 'center' },
            })),
        [columns, heads, flex],
    );

    // After a create the new row is highlighted: jump to the page that holds it.
    const onFirstDataRendered = React.useCallback((e) => {
        let target = null;
        e.api.forEachNode((node) => {
            if (!target && node.data.cls && node.data.cls.includes('row-new')) target = node;
        });
        if (target) e.api.ensureNodeVisible(target);
    }, []);

    return (
        <AgGridReact
            columnDefs={columnDefs}
            rowData={rows}
            rowHeight={rowHeight}
            pagination={true}
            paginationPageSize={pageSize} // exactly as many rows as fit the box: no empty band above the pager
            paginationPageSizeSelector={false}
            getRowId={(p) => p.data.id}
            getRowClass={(p) => p.data.cls}
            onFirstDataRendered={onFirstDataRendered}
            suppressCellFocus={true}
            animateRows={false}
        />
    );
}

// The sizing rule lives in public/js/grid-size.js (window.crcGridLayout), shared with the placeholder that holds the
// grid's place while this code loads, so the two always have the same height.

function render(host) {
    const g = grids.get(host);
    const l = window.crcGridLayout(host, g.rows.length, g.fixed);
    host.style.height = l.height + 'px';
    // The key makes the grid start fresh when the row size or page size changes (window resized), so rows never keep a stale height.
    g.root.render(<Grid key={l.pageSize + 'x' + l.rowHeight} columns={g.columns} heads={g.heads} flex={g.flex} rows={g.rows} pageSize={l.pageSize} rowHeight={l.rowHeight} />);
}

let resizeBound = false;
function bindResize() {
    if (resizeBound) return;
    resizeBound = true;
    window.addEventListener('resize', () => grids.forEach((_, host) => render(host)));
}

// Turn each <table data-grid> into a grid; the plain table stays in the page for visitors without JavaScript.
export function mountGrids(scope = document) {
    // Forget grids whose area was replaced (their host is no longer in the page).
    grids.forEach((g, host) => {
        if (!host.isConnected) {
            g.root.unmount();
            grids.delete(host);
        } else {
            render(host); // something above may have appeared or gone (for example a flash message)
        }
    });
    bindResize();

    scope.querySelectorAll('table[data-grid]').forEach((table, n) => {
        const area = table.closest('[data-region]');
        if (area && area._srcHtml === undefined) area._srcHtml = area.innerHTML; // server HTML, before the grid replaces it
        const heads = [...table.querySelector('tr').querySelectorAll('th')];
        const columns = heads.map((th) => th.textContent.trim());
        const fixed = Number(table.dataset.pageSize) || 0; // small grid inside a page: fixed rows per page
        const flex = heads.map((th) => Number(th.dataset.flex) || 1); // <th data-flex="3"> asks for a wider column
        const rows = [];

        table.querySelectorAll('tr').forEach((tr, r) => {
            const cells = tr.querySelectorAll(':scope > td');
            if (cells.length !== columns.length) return; // header row or an "empty" message row
            rows.push({
                id: tr.id || 'row-' + n + '-' + r,
                cls: tr.className,
                text: [...cells].map((td) => td.textContent.replace(/\s+/g, ' ').trim()),
                html: [...cells].map((td) => td.innerHTML),
            });
        });

        if (rows.length === 0) return; // nothing to show: keep the table with its message

        // Mount inside the placeholder that has held this grid's place (same height, so nothing jumps).
        let host = table.previousElementSibling;
        while (host && !host.classList.contains('grid-skeleton')) host = host.previousElementSibling;
        if (host) {
            host.className = 'grid-host';
            host.removeAttribute('aria-hidden');
            host.replaceChildren();
            const script = table.previousElementSibling;
            if (script && script.tagName === 'SCRIPT') script.remove();
            table.remove();
        } else {
            host = document.createElement('div');
            host.className = 'grid-host';
            table.replaceWith(host);
        }
        grids.set(host, { root: createRoot(host), columns, heads: heads.map((th) => ({ hasAttribute: (n) => th.hasAttribute(n) })), flex, rows, fixed });
        render(host);
    });
}
