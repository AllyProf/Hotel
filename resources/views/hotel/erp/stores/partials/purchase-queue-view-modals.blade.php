@inject('accountsData', 'App\Services\AccountsDataService')

@php
  $queueLists = $storeDepartmentPurchaseLists ?? collect();
  $queueStorePos = $storeKeeperPurchaseOrders ?? collect();
  $poCurrency = $accountsData->storeDeskCurrency($hotel);
@endphp

@foreach($queueLists as $list)
  @php
    $list->loadMissing('lines.receivingPackage', 'department', 'creator', 'purchaseOrder.paymentExpense');
    $linkedPo = $list->purchaseOrder;
    $listCurrency = $list->resolvedBudgetCurrency();
    $budgetTotalLabel = $list->budget_total !== null
      ? $accountsData->formatAccountsBudgetLabel((float) $list->budget_total, $listCurrency)
      : '—';
  @endphp
  <div class="modal fade" id="storeDeptPlView{{ $list->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg stores-invoice-detail-dialog" role="document">
      <div class="modal-content stores-invoice-detail-modal">
        <div class="modal-header">
          <h4 class="stores-invoice-detail-title mb-0">{{ $list->reference_no }} · line items</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3 small">
            <div class="col-md-3"><strong>Module</strong><br>{{ \App\Support\DepartmentProcurement::label($list->workflowKey()) }}</div>
            <div class="col-md-3"><strong>Department</strong><br>{{ $list->department?->name ?? '—' }}</div>
            <div class="col-md-3"><strong>Budget</strong><br>{{ $budgetTotalLabel }}</div>
            <div class="col-md-3"><strong>Sent</strong><br>{{ $list->sent_at?->format('d M Y H:i') ?? '—' }}</div>
            <div class="col-md-3 mt-2"><strong>Created by</strong><br>{{ $list->creator?->name ?? '—' }}</div>
            <div class="col-md-3 mt-2"><strong>Price status</strong><br>{{ $list->buyingPriceStatusLabel() }}</div>
            <div class="col-md-3 mt-2"><strong>Accounts</strong><br>{{ $list->accountingStatusLabel() }}</div>
            @if($linkedPo)
              <div class="col-md-3 mt-2"><strong>Payables</strong><br>
                @if($linkedPo->isPaid())
                  <span class="badge badge-primary">Paid</span>
                  @if($linkedPo->paid_at)
                    <span class="text-muted d-block">{{ $linkedPo->paid_at->format('d M Y') }}</span>
                  @endif
                @else
                  <span class="text-muted">{{ $linkedPo->storeDeskAccountsDisplayLabel() }}</span>
                @endif
              </div>
            @endif
          </div>
          @if($list->recipient_notes)
            <p class="small mb-3"><strong>Store notes:</strong> {{ $list->recipient_notes }}</p>
          @endif
          @include('hotel.erp.bar.partials._purchase-list-budget-table', [
            'purchaseList' => $list,
            'currency' => $listCurrency,
          ])
        </div>
        <div class="modal-footer stores-invoice-detail-actions">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endforeach

@foreach($queueStorePos as $order)
  @php
    $order->loadMissing(['items.masterItem.storePackage', 'vendor', 'storeDepartment', 'creator', 'paymentExpense', 'budgetReturnExpense']);
    $poBudgetLabel = $accountsData->formatAccountsBudgetLabel((float) $order->total, $poCurrency);
  @endphp
  <div class="modal fade" id="storePoQueueView{{ $order->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg stores-invoice-detail-dialog" role="document">
      <div class="modal-content stores-invoice-detail-modal">
        <div class="modal-header">
          <h4 class="stores-invoice-detail-title mb-0">{{ $order->po_number }} · line items</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3 small">
            <div class="col-md-4"><strong>Vendor</strong><br>{{ $order->vendor?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>Department</strong><br>{{ $order->storeDepartment?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>Total</strong><br>{{ $poBudgetLabel }}</div>
            <div class="col-md-4 mt-2"><strong>Created by</strong><br>{{ $order->creator?->name ?? '—' }}</div>
            <div class="col-md-4 mt-2"><strong>Accounts</strong><br>
              @if($order->isPaid())
                <span class="badge badge-primary">Paid</span>
                @if($order->paid_at)
                  <span class="text-muted d-block">{{ $order->paid_at->format('d M Y') }}</span>
                @endif
              @elseif($order->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_APPROVED)
                <span class="badge badge-success">{{ $order->storeDeskAccountsDisplayLabel() }}</span>
              @elseif($order->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_PENDING)
                <span class="badge badge-warning">{{ $order->storeDeskAccountsDisplayLabel() }}</span>
              @elseif($order->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_REJECTED)
                <span class="badge badge-danger">{{ $order->storeDeskAccountsDisplayLabel() }}</span>
              @else
                {{ $order->storeDeskAccountsDisplayLabel() }}
              @endif
            </div>
            <div class="col-md-4 mt-2"><strong>Store review</strong><br>{{ $order->storeDeskReviewLabel() }}</div>
            @if($order->isPaid() && $order->paymentExpense)
              <div class="col-md-4 mt-2"><strong>Payment posted</strong><br>
                {{ $accountsData->formatAccountsTableAmount((float) $order->paymentExpense->amount, $poCurrency) }}
                <span class="text-muted d-block">{{ $order->paymentExpense->expense_date?->format('d M Y') ?? '—' }}
                  · {{ $order->paymentExpense->paymentTypeLabel() }}</span>
              </div>
            @endif
            @if($order->hasBudgetReturn())
              <div class="col-md-4 mt-2"><strong>Budget return</strong><br>
                <span class="text-success">{{ $accountsData->formatAccountsTableAmount((float) $order->budget_return_amount, $poCurrency) }}</span>
                <span class="text-muted d-block">Posted to Expenses as paid in</span>
              </div>
            @endif
          </div>
          <div class="table-responsive stores-invoice-detail-table-wrap">
            <table class="table table-bordered stores-po-list-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Item</th>
                  <th class="text-right">Qty</th>
                  <th class="text-right">Buying price</th>
                  <th class="text-right">Tax</th>
                  <th class="text-right">Line total</th>
                </tr>
              </thead>
              <tbody>
                @forelse($order->items as $index => $line)
                  @php
                    $qty = (float) $line->quantity;
                    $qtyFormatted = $accountsData->formatAccountsTableAmount($qty, $poCurrency);
                    $pkg = trim((string) ($line->masterItem?->storePackage?->name ?? $line->masterItem?->unit ?? ''));
                    $qtyLabel = $pkg !== '' ? $qtyFormatted.' '.$pkg : $qtyFormatted;
                  @endphp
                  <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $line->name }}</td>
                    <td class="text-right">{{ $qtyLabel }}</td>
                    <td class="text-right">{{ $accountsData->formatAccountsTableAmount((float) $line->rate, $poCurrency) }}</td>
                    <td class="text-right">{{ $accountsData->formatAccountsTableAmount((float) $line->tax, $poCurrency) }}</td>
                    <td class="text-right">{{ $accountsData->formatAccountsTableAmount((float) $line->total, $poCurrency) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center text-muted py-3">No lines on this PO.</td>
                  </tr>
                @endforelse
              </tbody>
              @if($order->items->isNotEmpty())
                <tfoot>
                  <tr class="stores-invoice-detail-total-row">
                    <th colspan="5" class="text-right">PO total ({{ $poCurrency }})</th>
                    <th class="text-right">{{ $accountsData->formatAccountsTableAmount((float) $order->total, $poCurrency) }}</th>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
        </div>
        <div class="modal-footer stores-invoice-detail-actions">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endforeach
