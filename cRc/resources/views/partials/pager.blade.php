@if ($paginator->hasPages())
    <div style="display:flex;gap:1rem;align-items:center">
        @if ($paginator->onFirstPage())
            <span class="muted">&laquo; Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">&laquo; Previous</a>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next &raquo;</a>
        @else
            <span class="muted">Next &raquo;</span>
        @endif
    </div>
@endif
