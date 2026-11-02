<div class="ac-toolbar">
  <button type="button" class="btn btn-ac-outline" data-toggle="modal" data-target="#acAddVendorModal">Add Vendor</button>
  <a href="{{ route('hotel.expenses.index') }}" class="btn btn-ac-outline">PMS Expenses</a>
  <form method="GET" action="{{ route('hotel.accounts.index') }}" class="ac-search d-flex">
    <input type="hidden" name="tab" value="payables">
    <input class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search...">
  </form>
</div>

<div class="ac-content">
  @if($payables && $payables->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-bordered table-hover ac-table mb-0">
        <thead>
          <tr>
            @foreach($ui['payables']['columns'] ?? [] as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($payables as $po)
            @php
              $linkedBarList = ($barPurchaseListsByPoId ?? collect())[$po->id] ?? null;
              $payablesPayBlockReason = $po->isOpen() ? $po->payablesPaymentBlockReason($linkedBarList) : null;
            @endphp
            <tr>
              <td>{{ $po->po_number }}</td>
              <td>{{ $po->vendor?->name ?: '—' }}</td>
              <td>{{ $po->dateLabel() }}</td>
              <td>{{ number_format((float) $po->pre_tax, 2) }}</td>
              <td>{{ number_format((float) $po->tax, 2) }}</td>
              <td>{{ number_format((float) $po->total, 2) }}</td>
              <td>
                @if($po->isOpen())
                  <span class="badge badge-warning">{{ $po->statusLabel() }}</span>
                @else
                  <span class="badge badge-success">{{ $po->statusLabel() }}</span>
                  @if($po->paid_at)
                    <div class="small text-muted">{{ $po->paid_at->format('d/m/Y') }}</div>
                  @endif
                @endif
              </td>
              <td class="text-nowrap">
                @if($po->isOpen() && $payablesPayBlockReason)
                  <span class="small text-muted d-block" style="max-width:220px;">{{ $payablesPayBlockReason }}</span>
                @elseif($po->isOpen())
                  <form method="POST" action="{{ route('hotel.accounts.purchase-orders.pay', $po) }}" class="js-swal-confirm d-inline">
                    @csrf
                    <select name="payment_type" class="form-control form-control-sm d-inline-block mb-1" style="width:auto;min-width:120px;" required>
                      @foreach($paymentModes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                      @endforeach
                    </select>
                    <input type="date" name="expense_date" class="form-control form-control-sm d-inline-block mb-1" style="width:auto;" value="{{ now()->format('Y-m-d') }}" required>
                    <button type="submit" class="btn btn-sm btn-ac btn-block"
                      data-title="Mark PO as paid?"
                      data-text="Post {{ $po->po_number }} ({{ number_format((float) $po->total, 2) }}) as paid-out in Expenses?"
                      data-confirm="Yes, mark paid">Mark paid</button>
                  </form>
                @elseif($po->paymentExpense)
                  <a href="{{ route('hotel.expenses.index', ['from_date' => $po->paymentExpense->expense_date?->format('Y-m-d')]) }}" class="small">View in Expenses</a>
                  @if($linkedBarList && $linkedBarList->hasMarketBudgetReturn())
                    <div class="small text-success mt-1">Return {{ number_format((float) $linkedBarList->market_budget_return_amount, 2) }}</div>
                  @elseif($po->hasBudgetReturn())
                    <div class="small text-success mt-1">Return {{ number_format((float) $po->budget_return_amount, 2) }} (in Expenses)</div>
                  @endif
                @else
                  —
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($payables->hasPages())
      <div class="mt-3">{{ $payables->links() }}</div>
    @endif
  @else
    <div class="ac-empty">No data found</div>
  @endif

  @if($vendors && $vendors->isNotEmpty())
    <h5 class="mt-4 mb-3">Vendors</h5>
    <div class="table-responsive">
      <table class="table table-bordered table-hover ac-table mb-0">
        <thead>
          <tr>
            @foreach($ui['payables']['vendor_columns'] ?? [] as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($vendors as $vendor)
            <tr>
              <td>{{ $vendor->name }}</td>
              <td>{{ $vendor->contact_person ?: '—' }}</td>
              <td>{{ $vendor->phone ?: '—' }}</td>
              <td>{{ $vendor->state ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
