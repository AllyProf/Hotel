@if($goodsReceiveOrder ?? null)
@php
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $poSearch = $poSearch ?? '';
  $goodsReceiveReturnTab = $goodsReceiveReturnTab ?? ($mainTab ?? 'invoices');
  if (! in_array($goodsReceiveReturnTab, ['purchase-orders', 'invoices'], true)) {
    $goodsReceiveReturnTab = 'invoices';
  }
  $readOnly = (bool) $goodsReceiveOrder->goods_received;

  $cancelUrl = route('hotel.stores.index', array_filter([
    'tab' => $goodsReceiveReturnTab,
    'po_from' => $poFrom,
    'po_to' => $poTo,
    'po_search' => $poSearch !== '' ? $poSearch : null,
  ]));
@endphp

<div class="modal fade show stores-invoice-detail-overlay" id="storesGoodsReceiveModal" tabindex="-1" style="display:block;background:rgba(0,0,0,.45);" role="dialog">
  <div class="modal-dialog modal-lg stores-invoice-detail-dialog" role="document">
    <div class="modal-content stores-invoice-detail-modal stores-goods-receive-modal">
      <div class="modal-body pb-0">
        <h4 class="stores-invoice-detail-title mb-0">Goods received — {{ $goodsReceiveOrder->po_number }}</h4>
        <p class="text-muted small mb-0 mt-1">
          {{ $goodsReceiveOrder->vendor?->name ?? '—' }}
          · Invoice {{ $goodsReceiveOrder->invoice_number ?? '—' }}
        </p>
        <div class="stores-modal-divider"></div>

        @include('hotel.erp.stores.partials._goods-receive-form', [
          'goodsReceiveReturnTab' => $goodsReceiveReturnTab,
        ])
      </div>
    </div>
  </div>
</div>
@endif
