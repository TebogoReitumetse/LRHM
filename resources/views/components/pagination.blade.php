@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-4 border-t border-gray-100 px-4 py-3 text-sm" aria-label="Pagination">
        <p class="text-gray-500">
            Showing <span class="font-medium text-gray-700">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-gray-700">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-gray-700">{{ $paginator->total() }}</span>
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-secondary btn-sm opacity-50">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn-secondary btn-sm">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn-secondary btn-sm">Next</a>
            @else
                <span class="btn-secondary btn-sm opacity-50">Next</span>
            @endif
        </div>
    </nav>
@endif
