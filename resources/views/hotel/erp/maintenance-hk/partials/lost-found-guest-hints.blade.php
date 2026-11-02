@php
  $hints = $hints ?? [];
  $itemRef = $itemRef ?? '';
  $lostFoundItemId = $lostFoundItemId ?? null;
  $lostFoundFilter = $lostFoundFilter ?? 'open';
  $notificationByReservation = $notificationByReservation ?? [];
  $itemIsOpen = $itemIsOpen ?? true;
@endphp

<div class="py-2 px-1">
  <strong class="small text-uppercase text-muted">Guests who stayed in this room (recent)</strong>
  @if($hints === [])
    <p class="text-muted small mb-0 mt-1">No matching reservations in the last few weeks. Try the booking ID on the reservation screen or widen the search manually.</p>
  @else
    <div class="table-responsive mt-2">
      <table class="table table-sm table-bordered mb-0 bg-white">
        <thead>
          <tr>
            <th>Guest</th>
            <th>Contact</th>
            <th>Stay</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($hints as $hint)
            @php
              $resId = (int) ($hint['reservation_id'] ?? 0);
              $sent = $notificationByReservation[$resId] ?? null;
            @endphp
            <tr>
              <td>
                {{ $hint['guest_name'] }}
                @if(!empty($hint['in_house']))
                  <span class="badge badge-success ml-1">Likely in-house</span>
                @endif
              </td>
              <td class="small">
                @if($hint['phone'])
                  <div>{{ $hint['phone'] }}</div>
                @endif
                @if($hint['email'])
                  <div class="text-muted">{{ $hint['email'] }}</div>
                @endif
                @if(! $hint['phone'] && ! $hint['email'])
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small text-nowrap">{{ $hint['checkin'] }} → {{ $hint['checkout'] }}</td>
              <td><span class="badge {{ $hint['pms_badge'] }}">{{ $hint['pms_status'] }}</span></td>
              <td class="text-nowrap">
                <a href="{{ $hint['show_url'] }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">Reservation</a>
                @if($itemRef !== '')
                  <button type="button" class="btn btn-outline-secondary btn-sm js-lf-use-guest"
                    data-item-ref="{{ $itemRef }}"
                    data-guest-name="{{ $hint['guest_name'] }}">
                    Use name
                  </button>
                @endif
                @if($lostFoundItemId && $itemIsOpen && ! empty($hint['email']))
                  <form method="POST" action="{{ route('hotel.lost-found.notify-guest', $lostFoundItemId) }}" class="d-inline js-swal-confirm">
                    @csrf
                    <input type="hidden" name="reservation_id" value="{{ $resId }}">
                    <input type="hidden" name="filter" value="{{ $lostFoundFilter }}">
                    <button type="submit" class="btn btn-outline-info btn-sm"
                      data-title="Send email notification?"
                      data-text="Email {{ $hint['guest_name'] }} about possible found item {{ $itemRef }}? The message includes buttons to reply or call the front desk."
                      data-confirm="Send email">Notify guest</button>
                  </form>
                @endif
                @if($sent)
                  <div class="text-muted small mt-1">Emailed {{ $sent['sent_at'] }}</div>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
