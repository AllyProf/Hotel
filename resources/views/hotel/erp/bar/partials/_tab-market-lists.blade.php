@php
  $procW = $procWorkflow ?? 'bar';
  $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
@endphp
<div class="table-responsive">
  <table class="table table-hover table-bordered mb-0">
    <thead>
      <tr>
        <th>Name</th>
        <th>Market</th>
        <th>Date</th>
        <th>Items</th>
        <th>Status</th>
        <th>Total Cost (Est / Actual)</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($marketLists as $list)
        @php
          $currency = $list->resolvedBudgetCurrency();
          $est = $list->marketEstimatedTotal();
          $actual = $list->marketActualTotal();
          $isPending = $list->isMarketPendingPurchase();
          $canReceive = $list->canStoreMarketReceive();
          $awaitingPay = $list->isAwaitingPayablesPaymentForMarket();
          $list->loadMissing('purchaseOrder');
          $displayDate = $list->marketTabDate();
        @endphp
        <tr>
          <td>
            <strong>{{ $list->marketTabDisplayName() }}</strong>
            <div class="text-muted small">{{ $list->reference_no }}</div>
          </td>
          <td class="text-muted">N/A</td>
          <td>{{ $displayDate?->format('d M Y') ?? '—' }}</td>
          <td>{{ $list->lines->count() }}</td>
          <td>
            @if($awaitingPay)
              <span class="badge badge-warning">Awaiting payment</span>
            @elseif($canReceive)
              <span class="badge badge-warning">Pending</span>
            @elseif($isPending)
              <span class="badge badge-secondary">Awaiting payment</span>
            @else
              <span class="badge badge-success">Completed</span>
              @if($list->marketStockDestinationLabel())
                <div class="text-muted small mt-1">{{ $list->marketStockDestinationLabel() }}</div>
              @endif
              @if($list->market_stock_posted_at)
                <div class="text-success small">Stock received</div>
              @elseif($list->isMarketStockPending())
                <div class="text-warning small">Awaiting receive</div>
              @endif
            @endif
          </td>
          <td>
            <strong>{{ number_format($est, 2) }} {{ $currency }}</strong>
            <span class="text-muted"> / {{ number_format($actual, 2) }} {{ $currency }}</span>
          </td>
          <td class="text-nowrap">
            <a href="{{ $procRoute('purchase-lists.print', $list) }}" class="btn btn-sm btn-light border" target="_blank" rel="noopener" title="Print">
              <i class="fa fa-print"></i>
            </a>
            @if($canReceive)
              <a href="{{ $procRoute('purchase-lists.market.form', $list) }}" class="btn btn-sm btn-light border" title="Edit">
                <i class="fa fa-pencil"></i>
              </a>
              <a href="{{ $procRoute('purchase-lists.market.form', $list) }}" class="btn btn-sm btn-success">
                <i class="fa fa-check"></i> Receive
              </a>
            @elseif($awaitingPay)
              <a href="{{ route('hotel.accounts.index', ['tab' => 'payables', 'search' => $list->purchaseOrder?->po_number]) }}" class="btn btn-sm btn-light border" title="Pay in Accounts">
                Payables
              </a>
            @elseif($isPending)
              <span class="text-muted small">Pay PO first</span>
            @else
              @if($list->isMarketStockPending())
                <a href="{{ $procRoute('purchase-lists.market-stock-receive.form', $list) }}" class="btn btn-sm btn-success">
                  <i class="fa fa-truck"></i> Receive stock
                </a>
              @endif
              <a href="{{ $procRoute('purchase-lists.show', $list) }}" class="btn btn-sm btn-light border">View</a>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            Nothing on market yet. Lists appear here after Accounts approves the budget.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
