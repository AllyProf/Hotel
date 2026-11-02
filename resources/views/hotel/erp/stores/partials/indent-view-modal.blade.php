@if($viewStoreIndent ?? null)
@php
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $poSearch = $poSearch ?? '';
  $indentStatus = $indentStatus ?? 'all';
  $indentIndexRoute = $indentIndexRoute ?? route('hotel.stores.index');
  $indentIndexTab = $indentIndexTab ?? 'indent';
  $closeQuery = array_filter([
    'tab' => $indentIndexTab,
    'po_from' => $poFrom,
    'po_to' => $poTo,
    'po_search' => $poSearch !== '' ? $poSearch : null,
    'indent_status' => $indentStatus !== 'all' ? $indentStatus : null,
  ]);
  $closeUrl = str_contains($indentIndexRoute, '?')
    ? $indentIndexRoute.'&'.http_build_query($closeQuery)
    : $indentIndexRoute.'?'.http_build_query($closeQuery);
@endphp
<div class="modal fade show stores-invoice-detail-overlay" tabindex="-1" style="display:block;background:rgba(0,0,0,.45);">
  <div class="modal-dialog modal-lg stores-invoice-detail-dialog">
    <div class="modal-content stores-invoice-detail-modal">
      <div class="modal-body pb-0">
        <h4 class="stores-invoice-detail-title mb-0">{{ $viewStoreIndent->indent_number }}</h4>
        <p class="text-muted small mb-0">{{ $viewStoreIndent->department?->name ?? '—' }} · {{ $viewStoreIndent->statusLabel() }}</p>
        <div class="stores-modal-divider"></div>
        <table class="table table-bordered stores-po-list-table mb-3">
          <thead>
            <tr>
              <th>Name</th>
              <th>Qty</th>
              <th>Comments</th>
            </tr>
          </thead>
          <tbody>
            @foreach($viewStoreIndent->items as $line)
              <tr>
                <td>{{ $line->name }}</td>
                <td>{{ $line->quantity }}@if($line->unit) {{ $line->unit }}@endif</td>
                <td>{{ $line->comments ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th>Total</th>
              <th>{{ $viewStoreIndent->items->sum('quantity') }}</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
        @if($viewStoreIndent->isReceived())
          <p class="small text-muted">Received {{ $viewStoreIndent->received_at?->format('d M Y h:i A') ?? '—' }}
            @if($viewStoreIndent->receivedByUser) by {{ $viewStoreIndent->receivedByUser->name }} @endif
          </p>
        @endif
        <div class="stores-invoice-detail-actions">
          <a href="{{ $closeUrl }}" class="btn btn-stores-cancel">Close</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endif
