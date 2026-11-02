@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $stockData = $stockData ?? null;
  $filters = $filters ?? ['category_id' => 0, 'search' => ''];
  $qtyUnit = config('hotel_stores.po_line_qty_unit', 'Pkts');
@endphp

<div class="stores-toolbar">
  <form method="GET" action="{{ route('hotel.stores.index') }}" class="stores-toolbar-form stores-master-filter-form">
    <input type="hidden" name="tab" value="stock">

    <div class="stores-toolbar-row">
      <div class="stores-filter-fields">
        <div class="stores-filter-field">
          <label class="control-label" for="storesStockCategory">{{ $ui['category'] ?? 'Category' }}</label>
          <select name="category_id" id="storesStockCategory" class="form-control" onchange="this.form.submit()">
            <option value="0">{{ $ui['all'] ?? 'All' }}</option>
            @foreach($stockData['categories'] as $cat)
              <option value="{{ $cat->id }}" @selected($filters['category_id'] === $cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="stores-toolbar-actions">
        <div class="stores-search-field">
          <label class="control-label stores-search-label" for="storesStockSearch">{{ $ui['search_placeholder'] ?? 'Search Item…' }}</label>
          <div class="input-group stores-search-input">
            <input type="search" name="search" id="storesStockSearch" class="form-control"
              placeholder="{{ $ui['search_placeholder'] ?? 'Search Item…' }}" value="{{ $filters['search'] }}">
            <div class="input-group-append">
              <button type="submit" class="btn btn-light border" aria-label="Search"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="stores-total-value">
  <strong>{{ $ui['total_value'] ?? 'Total Value' }}:</strong>
  {{ $storeItemService->formatMoney((float) $stockData['total_value'], $hotel) }}
</div>

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        <th>Name</th>
        <th>Category</th>
        <th>Vendor</th>
        <th class="text-right">Stock</th>
        <th>Unit &amp; packaging</th>
        <th class="text-right">Rate</th>
        <th class="text-right">Value</th>
      </tr>
    </thead>
    <tbody>
      @forelse($stockData['items'] as $item)
        <tr>
          <td>{{ $item->name }}</td>
          <td>{{ $item->category?->name ?? '—' }}</td>
          <td>{{ $item->vendor?->name ?? '—' }}</td>
          <td class="text-right">
            @php
              $stock = (float) $item->stock;
              $stockDisplay = abs($stock - round($stock)) < 0.001 ? (string) (int) round($stock) : rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.');
            @endphp
            <strong>{{ $stockDisplay }}</strong>
            @if($item->storePackage?->abbreviation || $item->storePackage?->name)
              <span class="text-muted">{{ $item->storePackage->abbreviation ?: $item->storePackage->name }}</span>
            @else
              <span class="text-muted">{{ $qtyUnit }}</span>
            @endif
          </td>
          <td>
            @if($item->storePackage)
              {{ $item->storePackage->name }}
              @if($item->pieces_per_receive_unit && (float) $item->pieces_per_receive_unit !== 1.0)
                <span class="text-muted small">({{ $item->pieces_per_receive_unit }} pcs / unit)</span>
              @endif
            @else
              —
            @endif
          </td>
          <td class="text-right">{{ $storeItemService->formatMoney((float) $item->rate, $hotel) }}</td>
          <td class="text-right">{{ $storeItemService->formatMoney($item->lineValue(), $hotel) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            No inventoried items yet. Mark items as <strong>Inventoried</strong> under Settings → Master Items, then receive goods on an invoice.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
