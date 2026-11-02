@inject('accountsData', 'App\Services\AccountsDataService')

@foreach($barBudgetsPending as $list)
  @php
    $listCurrency = $list->resolvedBudgetCurrency();
    $budgetTotalLabel = $list->budget_total !== null
      ? $accountsData->formatAccountsBudgetLabel((float) $list->budget_total, $listCurrency)
      : '—';
  @endphp
  <div class="modal fade" id="barBudgetView{{ $list->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header ac-modal-header text-white">
          <h5 class="modal-title">{{ $list->reference_no }} · sent items</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3 small">
            <div class="col-md-4"><strong>Module</strong><br>{{ \App\Support\DepartmentProcurement::label($list->workflowKey()) }}</div>
            <div class="col-md-4"><strong>Department</strong><br>{{ $list->department?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>Budget</strong><br>{{ $budgetTotalLabel }}</div>
            <div class="col-md-4 mt-2"><strong>Prepared by</strong><br>{{ $list->budget_prepared_by_name ?? '—' }}</div>
            <div class="col-md-4 mt-2"><strong>Submitted</strong><br>{{ $list->budget_prepared_at?->format('d M Y H:i') ?? '—' }}</div>
          </div>
          @if($list->recipient_notes)
            <p class="small mb-3"><strong>Store notes:</strong> {{ $list->recipient_notes }}</p>
          @endif
          @include('hotel.erp.bar.partials._purchase-list-budget-table', [
            'purchaseList' => $list,
            'currency' => $listCurrency,
          ])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endforeach

@foreach($barBudgetsRejected as $list)
  @php
    $listCurrency = $list->resolvedBudgetCurrency();
    $budgetTotalLabel = $list->budget_total !== null
      ? $accountsData->formatAccountsBudgetLabel((float) $list->budget_total, $listCurrency)
      : '—';
  @endphp
  <div class="modal fade" id="barBudgetView{{ $list->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header ac-modal-header text-white">
          <h5 class="modal-title">{{ $list->reference_no }} · sent items</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3 small">
            <div class="col-md-4"><strong>Department</strong><br>{{ $list->department?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>Budget</strong><br>{{ $budgetTotalLabel }}</div>
            <div class="col-md-4"><strong>Rejected</strong><br>{{ $list->accounting_reviewed_at?->format('d M Y H:i') ?? '—' }}</div>
          </div>
          @if($list->accounting_notes)
            <div class="alert alert-danger py-2 small">{{ $list->accounting_notes }}</div>
          @endif
          @include('hotel.erp.bar.partials._purchase-list-budget-table', [
            'purchaseList' => $list,
            'currency' => $listCurrency,
          ])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endforeach

@foreach($storeDeskPosPending ?? [] as $order)
  @php
    $order->loadMissing(['items.masterItem.storePackage', 'vendor', 'storeDepartment']);
    $poCurrency = $accountsData->storeDeskCurrency($hotel);
    $poBudgetLabel = $accountsData->formatAccountsBudgetLabel((float) $order->total, $poCurrency);
  @endphp
  <div class="modal fade" id="storePoBudgetView{{ $order->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header ac-modal-header text-white">
          <h5 class="modal-title">{{ $order->po_number }} · sent items</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3 small">
            <div class="col-md-4"><strong>Vendor</strong><br>{{ $order->vendor?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>Department</strong><br>{{ $order->storeDepartment?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>Total</strong><br>{{ $poBudgetLabel }}</div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover ac-table mb-0">
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
                @foreach($order->items as $index => $line)
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
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="5" class="text-right">PO total ({{ $poCurrency }})</th>
                  <th class="text-right">{{ $accountsData->formatAccountsTableAmount((float) $order->total, $poCurrency) }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endforeach
