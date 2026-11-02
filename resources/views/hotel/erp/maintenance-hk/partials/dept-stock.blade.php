@push('styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@php
  $canView = $hkDeptStockPermissions['view'] ?? false;
@endphp

@if(! $canView)
  <p class="text-muted mb-0">You do not have permission to view department stock.</p>
@else
  <form method="GET" action="{{ $deptStockIndexRoute ?? route('hotel.maintenance-hk.index', ['tab' => 'dept-stock']) }}" class="stores-toolbar stores-po-filter-bar mb-3">
    <input type="hidden" name="tab" value="dept-stock">
    <div class="stores-po-filter-row">
      <div class="stores-po-inline-actions ml-auto">
        <input type="search" name="po_search" class="form-control stores-po-inline-search stores-po-inline-search--solo" placeholder="Search item…" value="{{ $poSearch ?? '' }}">
      </div>
    </div>
  </form>

  <div class="stores-table-wrap table-responsive">
    <table class="table table-bordered table-hover stores-master-table mb-0">
      <thead>
        <tr>
          <th>Department</th>
          <th>Item</th>
          <th>Category</th>
          <th class="text-right">Quantity</th>
          <th>Unit</th>
        </tr>
      </thead>
      <tbody>
        @forelse($consumptionData['rows'] ?? [] as $row)
          @php
            $item = $row->masterItem;
            $qty = (float) $row->quantity;
            $qtyDisplay = abs($qty - round($qty)) < 0.001 ? (string) (int) round($qty) : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
            $unit = $item?->storePackage?->name ?: '—';
          @endphp
          <tr>
            <td>{{ $row->department?->name ?? '—' }}</td>
            <td>{{ $item?->name ?? '—' }}</td>
            <td>{{ $item?->category?->name ?? '—' }}</td>
            <td class="text-right"><strong>{{ $qtyDisplay }}</strong></td>
            <td>{{ $unit }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">No stock on hand. Receive approved store requests first.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif
