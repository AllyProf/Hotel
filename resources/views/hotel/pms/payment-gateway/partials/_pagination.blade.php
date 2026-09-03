@php
  $queryBase = request()->except('page');
  $firstQuery = array_merge($queryBase, ['page' => 1]);
  $prevQuery = array_merge($queryBase, ['page' => max(1, $paginator->currentPage() - 1)]);
  $nextQuery = array_merge($queryBase, ['page' => min($paginator->lastPage(), $paginator->currentPage() + 1)]);
  $lastQuery = array_merge($queryBase, ['page' => max(1, $paginator->lastPage())]);
@endphp

<div class="pg-footer">
  <div class="pg-per-page">
    <span>Items per page:</span>
    <select class="form-control js-pg-report-per-page">
      @foreach($perPageOptions ?? [20, 50, 100] as $option)
        <option value="{{ $option }}" {{ (int) ($filters['per_page'] ?? 20) === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
      @endforeach
    </select>
  </div>

  <div class="pg-count">
    {{ $paginator->firstItem() ?? 0 }} – {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
  </div>

  <div class="pg-nav">
    <a href="{{ $paginator->onFirstPage() ? '#' : route($routeName, $firstQuery) }}"
      class="pg-nav-btn {{ $paginator->onFirstPage() ? 'is-disabled' : '' }}" title="First page">&laquo;</a>
    <a href="{{ $paginator->onFirstPage() ? '#' : route($routeName, $prevQuery) }}"
      class="pg-nav-btn {{ $paginator->onFirstPage() ? 'is-disabled' : '' }}" title="Previous page">&lsaquo;</a>
    <a href="{{ $paginator->onLastPage() ? '#' : route($routeName, $nextQuery) }}"
      class="pg-nav-btn {{ $paginator->onLastPage() ? 'is-disabled' : '' }}" title="Next page">&rsaquo;</a>
    <a href="{{ $paginator->onLastPage() ? '#' : route($routeName, $lastQuery) }}"
      class="pg-nav-btn {{ $paginator->onLastPage() ? 'is-disabled' : '' }}" title="Last page">&raquo;</a>
  </div>
</div>
