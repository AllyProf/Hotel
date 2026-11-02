@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@if($viewStoreInvoice ?? null)
@php
  $invoiceModalTab = $invoiceModalTab ?? 'invoices';
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $poSearch = $poSearch ?? '';
  $poStatus = $poStatus ?? 'all';
  $storePoPermissions = $storePoPermissions ?? ['invoice' => true];
  $suggestedStoreInvoiceNumber = $suggestedStoreInvoiceNumber ?? '';
  $qtyUnit = config('hotel_stores.po_line_qty_unit', 'Pkts');

  $closeParams = array_filter([
    'tab' => $invoiceModalTab,
    'po_from' => $poFrom,
    'po_to' => $poTo,
    'po_search' => $poSearch !== '' ? $poSearch : null,
    'po_status' => $invoiceModalTab === 'purchase-orders' && $poStatus !== 'all' ? $poStatus : null,
  ]);
  $closeUrl = route('hotel.stores.index', $closeParams);

  $isDraft = $viewStoreInvoice->isOpen();
  $invoiceNoValue = old('invoice_number', $viewStoreInvoice->invoice_number ?? '');
  $invoiceNoPlaceholder = $isDraft && $invoiceNoValue === '' ? $suggestedStoreInvoiceNumber : '';
  $invoiceDateValue = old(
    'invoiced_at',
    $viewStoreInvoice->invoiced_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
  );

  $formatPoQty = function ($qty) use ($qtyUnit): string {
    $q = (float) $qty;
    $display = abs($q - round($q)) < 0.001 ? (string) (int) round($q) : rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');

    return trim($display.' '.$qtyUnit);
  };

  $totalQty = $viewStoreInvoice->items->sum(fn ($line) => (float) $line->quantity);
  $totalQtyLabel = $formatPoQty($totalQty);
  if (str_ends_with($totalQtyLabel, ' '.$qtyUnit)) {
    $totalQtyLabel = str_replace(' '.$qtyUnit, '', $totalQtyLabel);
  }
@endphp

<div class="modal fade show stores-invoice-detail-overlay" id="storesInvoiceDetailModal" tabindex="-1" style="display:block;background:rgba(0,0,0,.45);" role="dialog">
  <div class="modal-dialog modal-lg stores-invoice-detail-dialog" role="document">
    <div class="modal-content stores-invoice-detail-modal">
      <div class="modal-body pb-0">
        <h4 class="stores-invoice-detail-title mb-0">Order # {{ $viewStoreInvoice->po_number }}</h4>
        <div class="stores-modal-divider"></div>

        <form method="POST"
          action="{{ route('hotel.stores.purchase-orders.invoice', $viewStoreInvoice) }}"
          id="storesInvoiceDetailForm">
          @csrf
          <input type="hidden" name="po_from" value="{{ $poFrom }}">
          <input type="hidden" name="po_to" value="{{ $poTo }}">
          <input type="hidden" name="return_tab" value="{{ $invoiceModalTab }}">
          @if($poSearch !== '')
            <input type="hidden" name="po_search" value="{{ $poSearch }}">
          @endif
          @if($invoiceModalTab === 'purchase-orders' && $poStatus !== 'all')
            <input type="hidden" name="po_status" value="{{ $poStatus }}">
          @endif

          <div class="row stores-invoice-detail-fields">
            <div class="col-md-4 form-group">
              <label class="control-label">Vendor</label>
              <input type="text" class="form-control" readonly value="{{ $viewStoreInvoice->vendor?->name ?? '—' }}">
            </div>
            <div class="col-md-4 form-group">
              <label class="control-label" for="storesInvoiceNo">Invoice No.</label>
              <input type="text" name="invoice_number" id="storesInvoiceNo" class="form-control"
                value="{{ $invoiceNoValue }}" placeholder="{{ $invoiceNoPlaceholder }}" maxlength="80"
                @if(! $storePoPermissions['invoice']) readonly @endif>
            </div>
            <div class="col-md-4 form-group">
              <label class="control-label" for="storesInvoiceDate">Invoice Date</label>
              <input type="date" name="invoiced_at" id="storesInvoiceDate" class="form-control"
                value="{{ $invoiceDateValue }}"
                @if(! $storePoPermissions['invoice']) readonly @endif>
            </div>
          </div>

          <div class="table-responsive stores-invoice-detail-table-wrap">
            <table class="table table-bordered stores-po-list-table stores-invoice-detail-table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th class="text-right">Qty</th>
                  <th class="text-right">Rate</th>
                  <th class="text-right">Pre tax</th>
                  <th class="text-right">Tax</th>
                  <th class="text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach($viewStoreInvoice->items as $line)
                  <tr>
                    <td>{{ $line->name }}</td>
                    <td class="text-right text-nowrap">{{ $formatPoQty($line->quantity) }}</td>
                    <td class="text-right">{{ $storeItemService->formatMoney((float) $line->rate, $hotel) }}</td>
                    <td class="text-right">{{ $storeItemService->formatMoney((float) $line->pre_tax, $hotel) }}</td>
                    <td class="text-right">{{ $storeItemService->formatMoney((float) $line->tax, $hotel) }}</td>
                    <td class="text-right">{{ $storeItemService->formatMoney((float) $line->total, $hotel) }}</td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr class="stores-invoice-detail-total-row">
                  <th>Total</th>
                  <th class="text-right">{{ $totalQtyLabel }}</th>
                  <th></th>
                  <th class="text-right">{{ $storeItemService->formatMoney((float) $viewStoreInvoice->pre_tax, $hotel) }}</th>
                  <th class="text-right">{{ $storeItemService->formatMoney((float) $viewStoreInvoice->tax, $hotel) }}</th>
                  <th class="text-right">{{ $storeItemService->formatMoney((float) $viewStoreInvoice->total, $hotel) }}</th>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="stores-invoice-detail-actions">
            @if($viewStoreInvoice->isInvoiced())
              <a href="{{ route('hotel.stores.purchase-orders.invoice-print', $viewStoreInvoice) }}"
                class="btn btn-stores-outline" target="_blank" rel="noopener">Print</a>
              <button type="submit" formaction="{{ route('hotel.stores.purchase-orders.invoice-email', $viewStoreInvoice) }}"
                formmethod="POST" class="btn btn-stores-outline"
                @if(! $viewStoreInvoice->vendor?->email) disabled title="Add vendor email in Settings → Vendor" @endif>
                Send Email to Vendor
              </button>
            @else
              <button type="button" class="btn btn-stores-outline" disabled title="Available after invoice is submitted">Print</button>
              <button type="button" class="btn btn-stores-outline" disabled title="Available after invoice is submitted">Send Email to Vendor</button>
            @endif
            @if($storePoPermissions['invoice'])
              <button type="submit" class="btn btn-stores-outline">{{ $isDraft ? 'Submit' : 'Submit' }}</button>
            @endif
            <a href="{{ $closeUrl }}" class="btn btn-stores-cancel">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endif
