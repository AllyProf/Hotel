@php
  $roomLabels = ($consumptionShowRoomDistribution ?? false)
    ? $viewStoreConsumption->distributedRoomLabels()
    : [];
@endphp
<div class="modal-content stores-invoice-detail-modal">
  <div class="modal-header stores-modal-header py-3">
    <div>
      <h4 class="stores-invoice-detail-title mb-0">{{ $viewStoreConsumption->consumption_number }}</h4>
      <p class="text-muted small mb-0">
        {{ $viewStoreConsumption->department?->name ?? '—' }}
        · {{ $viewStoreConsumption->dateTimeLabel() }}
        · {{ $viewStoreConsumption->itemCount() }} {{ $viewStoreConsumption->itemCount() === 1 ? 'item' : 'items' }}
        · {{ \App\Models\HotelStoreConsumption::formatQuantity($viewStoreConsumption->totalQuantity()) }} total qty
      </p>
      @if($roomLabels !== [])
        <p class="text-muted small mb-0 mt-1"><strong>Room(s):</strong> {{ implode(', ', $roomLabels) }}</p>
      @endif
    </div>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
  </div>
  <div class="modal-body pt-0 pb-0">
    <div class="stores-modal-divider"></div>
    <div class="table-responsive">
      <table class="table table-bordered stores-po-list-table mb-0">
        <thead>
          <tr>
            <th>Item</th>
            <th class="text-right">Qty</th>
            @if($consumptionShowRoomDistribution ?? false)
              <th>Room(s)</th>
            @endif
            <th>Comments</th>
          </tr>
        </thead>
        <tbody>
          @foreach($viewStoreConsumption->items as $line)
            @php
              $lineRooms = ($consumptionShowRoomDistribution ?? false) ? $line->distributedRoomLabels() : [];
            @endphp
            <tr>
              <td>{{ $line->name }}</td>
              <td class="text-right text-nowrap">
                {{ \App\Models\HotelStoreConsumption::formatQuantity((float) $line->quantity) }}@if($line->displayUnit()) <span class="text-muted">{{ $line->displayUnit() }}</span>@endif
              </td>
              @if($consumptionShowRoomDistribution ?? false)
                <td>{{ $lineRooms !== [] ? implode(', ', $lineRooms) : '—' }}</td>
              @endif
              <td>{{ $line->comments ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <th>Total</th>
            <th class="text-right">{{ \App\Models\HotelStoreConsumption::formatQuantity($viewStoreConsumption->totalQuantity()) }}</th>
            @if($consumptionShowRoomDistribution ?? false)
              <th></th>
            @endif
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div class="modal-footer stores-po-modal-footer">
    <button type="button" class="btn btn-stores-cancel" data-dismiss="modal">Close</button>
  </div>
</div>
