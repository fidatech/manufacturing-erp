@if ($paginator->hasPages())
    <nav class="d-flex align-items-center justify-content-between gap-2 mt-3" aria-label="{{ __('Pages') }}">
        <span class="small text-muted">{{ __('Page :page of :pages', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }} · {{ __(':total records', ['total' => $paginator->total()]) }}</span>
        <span class="d-flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-light-secondary disabled">{{ __('Previous') }}</span>
            @else
                <a class="btn btn-sm btn-light-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-sm btn-primary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
            @else
                <span class="btn btn-sm btn-light-secondary disabled">{{ __('Next') }}</span>
            @endif
        </span>
    </nav>
@endif
