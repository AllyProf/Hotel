@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $storeInvoices = $storeInvoices ?? null;
  $poSearch = $poSearch ?? '';
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $viewStoreInvoice = $viewStoreInvoice ?? null;
  $currency = strtoupper(trim((string) ($hotel->currency ?? 'TZS'))) ?: 'TZS';
  $poMoneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;
@endphp

<div class="stores-po-page-head">
  <button type="button" class="btn btn-stores-po-bank" disabled title="Coming soon">
    Get Bank Payment File
  </button>
</div>

<form method="GET" action="{{ route('hotel.stores.index') }}" class="stores-toolbar stores-po-filter-bar">
  <input type="hidden" name="tab" value="invoices">
  <div class="stores-po-filter-row">
    <div class="stores-filter-fields">
      <div class="stores-filter-field">
        <label class="control-label" for="storesInvFrom">Start Date :</label>
        <input type="date" name="po_from" id="storesInvFrom" class="form-control" value="{{ $poFrom }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field">
        <label class="control-label" for="storesInvTo">End Date :</label>
        <input type="date" name="po_to" id="storesInvTo" class="form-control" value="{{ $poTo }}" onchange="this.form.submit()">
      </div>
    </div>
    <div class="stores-po-inline-actions stores-po-inline-actions--search-only">
      <input type="search" name="po_search" class="form-control stores-po-inline-search stores-po-inline-search--solo" placeholder="Search…" value="{{ $poSearch }}">
    </div>
  </div>
</form>

<div class="stores-table-wrap table-responsive stores-po-table-wrap">
  <table class="table table-bordered table-hover stores-po-list-table mb-0">
    <thead>
      <tr>
        <th style="width:36px"><input type="checkbox" disabled aria-label="Select all"></th>
        <th>Order #</th>
        <th>Invoice Date</th>
        <th class="text-right">Pre tax</th>
        <th class="text-right">Tax</th>
        <th class="text-right">Total</th>
        <th>Status</th>
        <th>Invoice#</th>
        <th>Vendor</th>
        <th>Goods received</th>
        <th style="width:48px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($storeInvoices ?? [] as $inv)
        <tr>
          <td class="text-center"><input type="checkbox" disabled></td>
          <td>{{ $inv->po_number }}</td>
          <td class="text-nowrap">{{ $inv->invoiceDateLabel() }}</td>
          <td class="text-right">{{ $storeItemService->formatMoney((float) $inv->pre_tax, $hotel) }}</td>
          <td class="text-right">{{ $storeItemService->formatMoney((float) $inv->tax, $hotel) }}</td>
          <td class="text-right">{{ $storeItemService->formatMoney((float) $inv->total, $hotel) }}</td>
          <td>{{ $inv->statusLabel() }}</td>
          <td>{{ $inv->invoice_number ?? '—' }}</td>
          <td>{{ $inv->vendor?->name ?? '—' }}</td>
          <td>
            <a href="{{ route('hotel.stores.index', array_filter([
              'tab' => 'invoices',
              'po_from' => $poFrom,
              'po_to' => $poTo,
              'po_search' => $poSearch !== '' ? $poSearch : null,
              'goods_receive' => $inv->id,
            ])) }}"
              class="btn btn-sm {{ $inv->goods_received ? 'btn-success' : 'btn-outline-success' }}">
              YES
            </a>
          </td>
          <td class="text-center">
            <a href="{{ route('hotel.stores.index', ['tab' => 'invoices', 'po_from' => $poFrom, 'po_to' => $poTo, 'po_search' => $poSearch, 'view' => $inv->id]) }}"
              class="stores-icon-btn text-info" title="View"><i class="fa fa-eye"></i></a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="11" class="text-center text-muted py-4">
            No invoices in this period. After Accounts approves a store PO, use <strong>Receive</strong> on <strong>Purchase Orders</strong> (or open invoiced orders here).
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($storeInvoices && method_exists($storeInvoices, 'hasPages') && $storeInvoices->hasPages())
  <div class="px-3 pb-3">{{ $storeInvoices->links() }}</div>
@endif

@include('hotel.erp.stores.partials.invoice-detail-modal', ['invoiceModalTab' => 'invoices'])
@include('hotel.erp.stores.partials.goods-receive-modal')
