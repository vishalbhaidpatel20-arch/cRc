{{-- Server-side fallback pager. The data grid has its own pager on screen; this bar only appears when a list has more rows
     than config('crc.per_page') (200), or without JavaScript. --}}
@if ($paginator->hasPages())
<div class="pager" style="margin-top:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <span class="muted">
        @if ($paginator->total() > 0)
            Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        @else
            No results
        @endif
    </span>
    <div style="display:flex;gap:1rem;align-items:center">
        @if ($paginator->onFirstPage())
            <span class="muted">&laquo; Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">&laquo; Previous</a>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ max(1, $paginator->lastPage()) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next &raquo;</a>
        @else
            <span class="muted">Next &raquo;</span>
        @endif
    </div>
</div>
@endif
