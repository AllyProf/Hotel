@inject('goodsReceiveService', 'App\Services\Stores\StoreGoodsReceiveService')
@php
  $storePoPermissions = $storePoPermissions ?? ['invoice' => true];
  $receiveStatuses = config('hotel_stores.goods_receive_statuses', []);
  $qtyUnit = config('hotel_stores.po_line_qty_unit', 'Pkts');
  $readOnly = (bool) ($readOnly ?? $goodsReceiveOrder->goods_received);
  $goodsReceiveReturnTab = $goodsReceiveReturnTab ?? 'purchase-orders';
  if (! in_array($goodsReceiveReturnTab, ['purchase-orders', 'invoices'], true)) {
    $goodsReceiveReturnTab = 'purchase-orders';
  }
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $poSearch = $poSearch ?? '';

  $formatPoQty = function ($qty) use ($qtyUnit): string {
    $q = (float) $qty;
    $display = abs($q - round($q)) < 0.001 ? (string) (int) round($q) : rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');

    return trim($display.' '.$qtyUnit);
  };
@endphp

@if($readOnly)
  <p class="alert alert-light border small mb-3">
    Received on {{ $goodsReceiveOrder->goods_received_at?->format('d M Y h:i A') ?? '—' }}
    @if($goodsReceiveOrder->goodsReceivedBy)
      by {{ $goodsReceiveOrder->goodsReceivedBy->name }}
    @endif
  </p>
@else
  <p class="text-muted small mb-3">
    Check each line: mark <strong>All received</strong>, <strong>Partial (few received)</strong>, or <strong>Missing</strong>, then submit.
  </p>
@endif

<form method="POST" action="{{ route('hotel.stores.invoices.goods-receive', $goodsReceiveOrder) }}" id="storesGoodsReceiveForm">
  @csrf
  <input type="hidden" name="return_tab" value="{{ $goodsReceiveReturnTab }}">
  <input type="hidden" name="po_from" value="{{ $poFrom }}">
  <input type="hidden" name="po_to" value="{{ $poTo }}">
  @if($poSearch !== '')
    <input type="hidden" name="po_search" value="{{ $poSearch }}">
  @endif

  <div class="table-responsive stores-invoice-detail-table-wrap">
    <table class="table table-bordered stores-po-list-table stores-goods-receive-table mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th class="text-right">Ordered</th>
          <th style="min-width:100px">Received</th>
          <th style="min-width:160px">Status</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        @foreach($goodsReceiveOrder->items as $line)
          @php
            $ordered = (float) $line->quantity;
            $defaultStatus = old('lines.'.$line->id.'.receive_status', $line->receive_status ?? 'full');
            $defaultReceived = old('lines.'.$line->id.'.received_quantity', $line->received_quantity ?? $ordered);
            $defaultNotes = old('lines.'.$line->id.'.receive_notes', $line->receive_notes ?? '');
          @endphp
          <tr class="js-stores-gr-line" data-ordered="{{ $ordered }}">
            <td>{{ $line->name }}</td>
            <td class="text-right text-nowrap">{{ $formatPoQty($ordered) }}</td>
            <td class="stores-gr-received-cell">
              @if($readOnly)
                {{ $formatPoQty((float) ($line->received_quantity ?? 0)) }}
              @else
                <input type="number" step="0.01" min="0"
                  name="lines[{{ $line->id }}][received_quantity]"
                  class="form-control form-control-sm js-stores-gr-qty"
                  value="{{ $defaultReceived }}">
              @endif
            </td>
            <td>
              @if($readOnly)
                {{ $goodsReceiveService->receiveStatusLabel($line->receive_status) }}
              @else
                <select name="lines[{{ $line->id }}][receive_status]" class="form-control form-control-sm js-stores-gr-status" required>
                  @foreach($receiveStatuses as $key => $label)
                    <option value="{{ $key }}" @selected($defaultStatus === $key)>{{ $label }}</option>
                  @endforeach
                </select>
              @endif
            </td>
            <td>
              @if($readOnly)
                {{ $line->receive_notes ?: '—' }}
              @else
                <input type="text" name="lines[{{ $line->id }}][receive_notes]"
                  class="form-control form-control-sm" maxlength="500"
                  placeholder="Optional" value="{{ $defaultNotes }}">
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="form-group mt-3">
    <label class="control-label" for="storesGrHeaderNotes">Remarks</label>
    @if($readOnly)
      <p class="mb-0">{{ $goodsReceiveOrder->goods_receive_notes ?: '—' }}</p>
    @else
      <textarea name="goods_receive_notes" id="storesGrHeaderNotes" class="form-control" rows="2" maxlength="2000"
        placeholder="Optional notes for this delivery">{{ old('goods_receive_notes', $goodsReceiveOrder->goods_receive_notes) }}</textarea>
    @endif
  </div>

  <div class="stores-invoice-detail-actions d-flex flex-wrap gap-2">
    @if(! $readOnly && ($storePoPermissions['invoice'] ?? false))
      <button type="submit" class="btn btn-stores-outline">Complete receive</button>
    @endif
    @if(isset($cancelUrl))
      <a href="{{ $cancelUrl }}" class="btn btn-stores-cancel">{{ $readOnly ? 'Back' : 'Cancel' }}</a>
    @endif
  </div>
</form>

@if(! $readOnly)
@push('scripts')
<script>
(function ($) {
  function syncLine($row) {
    var ordered = parseFloat($row.data('ordered')) || 0;
    var status = $row.find('.js-stores-gr-status').val();
    var $qty = $row.find('.js-stores-gr-qty');
    if (status === 'full') {
      $qty.val(ordered).prop('readonly', true);
    } else if (status === 'missing') {
      $qty.val(0).prop('readonly', true);
    } else {
      $qty.prop('readonly', false);
      var cur = parseFloat($qty.val()) || 0;
      if (cur >= ordered || cur <= 0) {
        var partial = ordered > 1 ? ordered - 1 : ordered / 2;
        $qty.val(partial);
      }
    }
  }
  $('.js-stores-gr-line').each(function () { syncLine($(this)); });
  $(document).on('change', '.js-stores-gr-status', function () {
    syncLine($(this).closest('.js-stores-gr-line'));
  });
})(jQuery);
</script>
@endpush
@endif
