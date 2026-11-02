@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $hotel = $hotel ?? auth()->user()?->hotel;
  $requesterLabel = $hotel ? $storeItemService->requesterLabelForIndent($hotel, $viewStoreIndent) : ($viewStoreIndent->department?->name ?? '—');
  $currency = strtoupper(trim((string) ($hotel->currency ?? 'TZS'))) ?: 'TZS';
  $moneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;
  $formatMoney = function (float $amount) use ($moneyDecimals): string {
    return number_format($amount, $moneyDecimals, '.', ',');
  };
@endphp
<div class="modal-content stores-invoice-detail-modal">
  <div class="modal-header stores-modal-header py-3">
    <div>
      <h4 class="stores-invoice-detail-title mb-0">{{ $viewStoreIndent->indent_number }}</h4>
      <p class="text-muted small mb-0">
        {{ $requesterLabel }}
        @if($viewStoreIndent->department && strcasecmp(trim($requesterLabel), trim($viewStoreIndent->department->name)) !== 0)
          <span class="text-muted">(HR: {{ $viewStoreIndent->department->name }})</span>
        @endif
        · {{ $viewStoreIndent->statusLabel() }}
      </p>
    </div>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
  </div>
  <div class="modal-body pt-0 pb-0">
    <div class="stores-modal-divider"></div>
    <div class="table-responsive">
      <table class="table table-bordered stores-po-list-table mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Qty</th>
            <th>Sell price</th>
            <th>Comments</th>
          </tr>
        </thead>
        <tbody>
          @foreach($viewStoreIndent->items as $line)
            @php
              $sell = $hotel ? $storeItemService->indentLineSellPrice($hotel, $line) : null;
            @endphp
            <tr>
              <td>{{ $line->name }}</td>
              <td>{{ $line->quantity }}@if($line->unit) {{ $line->unit }}@endif</td>
              <td>
                @if($sell)
                  {{ $formatMoney($sell['price']) }}@if($sell['unit']) / {{ $sell['unit'] }}@endif
                @else
                  —
                @endif
              </td>
              <td>{{ $line->comments ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <th>Total</th>
            <th>{{ $viewStoreIndent->items->sum('quantity') }}</th>
            <th colspan="2"></th>
          </tr>
        </tfoot>
      </table>
    </div>
    @if($viewStoreIndent->isReceived())
      <p class="small text-muted mt-3 mb-0">Received {{ $viewStoreIndent->received_at?->format('d M Y h:i A') ?? '—' }}
        @if($viewStoreIndent->receivedByUser) by {{ $viewStoreIndent->receivedByUser->name }} @endif
      </p>
    @endif
  </div>
  <div class="modal-footer stores-po-modal-footer">
    <button type="button" class="btn btn-stores-cancel" data-dismiss="modal">Close</button>
  </div>
</div>
