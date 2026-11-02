@php
  $procW = $procWorkflow ?? 'bar';
  $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
  $deptDest = \App\Support\DepartmentProcurement::departmentStockDestKey($procW);
  $deptLabel = \App\Support\DepartmentProcurement::marketStockDestinations($procW)[$deptDest] ?? 'Department';
@endphp
<p class="text-muted small mb-3">
  Completed market purchases with stock for <strong>{{ $deptLabel }}</strong> or store. Confirm receiving to update inventory where catalog items match.
</p>

<div class="table-responsive">
  <table class="table table-hover table-bordered mb-0">
    <thead>
      <tr>
        <th>Reference</th>
        <th>Department</th>
        <th>Completed</th>
        <th class="text-center">{{ $deptLabel }}</th>
        <th class="text-center">Also store</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($stockReceiveLists as $list)
        @php
          $barCount = $list->marketStockLineCountFor($deptDest);
          $storeCount = $list->marketStockLineCountFor(\App\Models\HotelBarPurchaseList::STOCK_DEST_STORE);
        @endphp
        <tr>
          <td>
            <strong>{{ $list->reference_no }}</strong>
            @if($list->market_completion_notes)
              <div class="text-muted small">{{ Str::limit($list->market_completion_notes, 60) }}</div>
            @endif
          </td>
          <td>{{ $list->department?->name ?? '—' }}</td>
          <td>
            {{ $list->market_completed_at?->format('d M Y H:i') ?? '—' }}
            @if($list->marketCompletedBy)
              <div class="text-muted small">{{ $list->marketCompletedBy->name }}</div>
            @endif
          </td>
          <td class="text-center">
            <span class="badge badge-primary">{{ $barCount }}</span>
          </td>
          <td class="text-center">
            @if($storeCount > 0)
              <span class="badge badge-secondary">{{ $storeCount }}</span>
              <div class="text-muted small">posted on confirm</div>
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td class="text-nowrap text-right">
            <a href="{{ $procRoute('purchase-lists.market-stock-receive.form', $list) }}" class="btn btn-sm btn-success">
              <i class="fa fa-truck"></i> Receive
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center text-muted py-4">
            No stock waiting to be received for this department.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
