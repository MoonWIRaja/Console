@php
    $firstItem = $paginator->firstItem() ?? 0;
    $lastItem = $paginator->lastItem() ?? 0;
    $totalItems = $paginator->total();
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $pageStart = max(1, $currentPage - 2);
    $pageEnd = min($lastPage, $currentPage + 2);
@endphp

<div class="admin-billing-pagination">
    <p class="admin-billing-pagination-summary">
        Showing <strong>{{ $firstItem }}</strong> to <strong>{{ $lastItem }}</strong> of <strong>{{ $totalItems }}</strong> results.
    </p>

    <div class="admin-billing-pagination-actions">
        @if($paginator->onFirstPage())
            <span class="admin-billing-page-btn disabled" aria-disabled="true">&lsaquo;</span>
        @else
            <a class="admin-billing-page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&lsaquo;</a>
        @endif

        @if($pageStart > 1)
            <a class="admin-billing-page-btn" href="{{ $paginator->url(1) }}">1</a>
            @if($pageStart > 2)
                <span class="admin-billing-page-btn disabled" aria-hidden="true">...</span>
            @endif
        @endif

        @for($page = $pageStart; $page <= $pageEnd; $page++)
            @if($page === $currentPage)
                <span class="admin-billing-page-btn active" aria-current="page">{{ $page }}</span>
            @else
                <a class="admin-billing-page-btn" href="{{ $paginator->url($page) }}">{{ $page }}</a>
            @endif
        @endfor

        @if($pageEnd < $lastPage)
            @if($pageEnd < $lastPage - 1)
                <span class="admin-billing-page-btn disabled" aria-hidden="true">...</span>
            @endif
            <a class="admin-billing-page-btn" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a>
        @endif

        @if($paginator->hasMorePages())
            <a class="admin-billing-page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rsaquo;</a>
        @else
            <span class="admin-billing-page-btn disabled" aria-disabled="true">&rsaquo;</span>
        @endif
    </div>
</div>
