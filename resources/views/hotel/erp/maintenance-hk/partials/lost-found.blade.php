@php
  $items = $payload['items'] ?? ($items ?? collect());
  $lfReturnTo = $lostFoundReturnTo ?? null;
  $lfFilter = $lostFoundFilter ?? request('filter', 'open');
  $lfAllowActions = $lostFoundAllowActions ?? ($lfReturnTo !== 'maintenance_hk');
  $lfColspan = $lfAllowActions ? 10 : 9;
  $lfShowGuestHints = ! empty($showGuestHints);
  $lfGuestHints = $guestHints ?? [];
@endphp

<div class="table-responsive">
  <table class="table table-hover table-bordered">
    <thead>
      <tr>
        <th>Ref</th>
        <th>Photo</th>
        <th>Item</th>
        <th>Category</th>
        <th>Room / location</th>
        <th>Storage</th>
        <th>Status</th>
        <th>Logged</th>
        <th>By</th>
        @if($lfAllowActions)
          <th>Actions</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @forelse($items as $item)
        @php
          $rowHints = $lfGuestHints[$item->id] ?? null;
        @endphp
        <tr>
          <td><strong>{{ $item->reference_no }}</strong></td>
          <td>
            @if($item->photoUrl())
              <a href="{{ $item->photoUrl() }}" target="_blank" rel="noopener">
                <img src="{{ $item->photoUrl() }}" alt="" class="img-thumbnail" style="max-width:72px;max-height:72px;object-fit:cover;">
              </a>
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td>
            {{ $item->item_name }}
            @if($item->description)
              <br><span class="text-muted small">{{ Str::limit($item->description, 80) }}</span>
            @endif
          </td>
          <td>{{ $item->categoryLabel() }}</td>
          <td>
            @if($item->room_no)
              Room {{ $item->room_no }}
            @elseif($item->location)
              {{ $item->location }}
            @else
              —
            @endif
          </td>
          <td>{{ $item->storage_location ?: '—' }}</td>
          <td>
            @include('hotel.erp.maintenance-hk.partials.status-badge', ['status' => $item->status, 'label' => $item->statusLabel()])
            @if(! $lfAllowActions && ! $item->isOpen())
              <div class="text-muted small mt-1">
                @if($item->claimed_by_name)
                  {{ $item->claimed_by_name }} ·
                @endif
                {{ $item->claimed_at?->format('d/m/Y H:i') ?? '—' }}
              </div>
            @elseif(! $lfAllowActions && $item->isOpen())
              <div class="text-muted small mt-1">Reception handles claim</div>
            @endif
          </td>
          <td>{{ $item->logged_at?->format('d/m/Y H:i') ?? '—' }}</td>
          <td>{{ $item->creator?->name ?: '—' }}</td>
          @if($lfAllowActions)
            <td class="text-nowrap" style="min-width:220px;">
              @if($item->isOpen())
                <form method="POST" action="{{ route('hotel.lost-found.update', $item) }}" class="mb-2 js-lf-claim-form js-swal-confirm" data-item-ref="{{ $item->reference_no }}">
                  @csrf
                  <input type="hidden" name="filter" value="{{ $lfFilter }}">
                  <input type="hidden" name="status" value="claimed">
                  <input type="text" name="claimed_by_name" class="form-control form-control-sm mb-1 js-lf-claimed-by" placeholder="Claimed by (guest name)" maxlength="255">
                  <textarea name="closure_notes" class="form-control form-control-sm mb-1" rows="2" placeholder="Internal notes (ID checked, staff handover…)" maxlength="2000"></textarea>
                  <button type="submit" class="btn btn-sm btn-success btn-block"
                    data-title="Mark as claimed?"
                    data-text="Confirm {{ $item->reference_no }} — {{ $item->item_name }} is returned to the guest."
                    data-confirm="Yes, mark claimed">Mark claimed</button>
                </form>
                <form method="POST" action="{{ route('hotel.lost-found.update', $item) }}" class="js-swal-confirm">
                  @csrf
                  <input type="hidden" name="filter" value="{{ $lfFilter }}">
                  <input type="hidden" name="status" value="disposed">
                  <button type="submit" class="btn btn-sm btn-secondary btn-block"
                    data-title="Mark as disposed?"
                    data-text="Close {{ $item->reference_no }} as disposed? The item will leave the open register."
                    data-confirm="Yes, mark disposed">Mark disposed</button>
                </form>
              @else
                <span class="text-muted small">
                  @if($item->claimed_by_name)
                    {{ $item->claimed_by_name }} ·
                  @endif
                  {{ $item->claimed_at?->format('d/m/Y H:i') ?? '—' }}
                </span>
              @endif
            </td>
          @endif
        </tr>
        @if($lfShowGuestHints && $item->hotel_room_unit_id)
          <tr class="table-light">
            <td colspan="{{ $lfColspan }}" class="p-0 border-left border-right">
              @include('hotel.erp.maintenance-hk.partials.lost-found-guest-hints', [
                'hints' => is_array($rowHints) ? $rowHints : [],
                'itemRef' => $item->reference_no,
                'lostFoundItemId' => $item->id,
                'lostFoundFilter' => $lfFilter,
                'notificationByReservation' => ($notificationByItem ?? [])[$item->id] ?? [],
                'itemIsOpen' => $item->isOpen(),
              ])
            </td>
          </tr>
        @endif
      @empty
        <tr>
          <td colspan="{{ $lfColspan }}" class="text-center text-muted py-4">No found items logged for this view.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
