@php
  $groups = $payload['groups'] ?? [];
  $availableColors = ['bg' => '#bbf7d0', 'border' => '#16a34a', 'text' => '#14532d'];
  $statusColors = [
    'available' => $availableColors,
    'cleaned' => $availableColors,
    'dirty' => ['bg' => '#fed7aa', 'border' => '#ea580c', 'text' => '#7c2d12'],
    'maintenance' => ['bg' => '#fecaca', 'border' => '#dc2626', 'text' => '#991b1b'],
    'inactive' => ['bg' => '#e5e7eb', 'border' => '#9ca3af', 'text' => '#374151'],
  ];
@endphp

@push('styles')
  <style>
    .mh-room-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }
    .mh-room-card {
      border: 2px solid #dee2e6;
      border-radius: 6px;
      overflow: hidden;
      background: #fff;
      font-size: 13px;
      max-width: 220px;
    }
    .mh-room-card__head {
      padding: 10px 12px;
      line-height: 1.3;
    }
    .mh-room-card__no {
      display: block;
      font-weight: 700;
      font-size: 16px;
    }
    .mh-room-card__status {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin-top: 2px;
    }
    .mh-room-card__body {
      padding: 10px 12px 12px;
      background: #fff;
      border-top: 1px solid rgba(0, 0, 0, 0.06);
    }
    .mh-room-type-title {
      font-size: 13px;
      font-weight: 700;
      color: #333;
      margin: 16px 0 10px;
    }
  </style>
@endpush

@forelse($groups as $group)
  <div class="mh-room-type-title">{{ $group['name'] }}</div>
  <div class="mh-room-grid">
    @foreach($group['units'] as $unit)
      @php
        $status = $unit['status'] ?? 'available';
        $colors = $statusColors[$status] ?? $statusColors['available'];
      @endphp
      <div class="mh-room-card" style="border-color: {{ $colors['border'] }};">
        <div class="mh-room-card__head" style="background: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
          <span class="mh-room-card__no">{{ $unit['label'] }}</span>
          <span class="mh-room-card__status">{{ $status === 'cleaned' ? 'Available' : $unit['status_label'] }}</span>
        </div>
        <div class="mh-room-card__body">
          <form method="POST" action="{{ route('hotel.maintenance-hk.rooms.status', $unit['id']) }}">
            @csrf
            <label class="small text-muted mb-1">Change status</label>
            <select name="status" class="form-control form-control-sm mb-2" aria-label="Room status">
              @foreach(['available', 'dirty', 'cleaned', 'maintenance'] as $st)
                <option value="{{ $st }}" {{ $status === $st ? 'selected' : '' }}>
                  {{ config('hotel_erp_hk.room_status_labels.'.$st, $st) }}
                </option>
              @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary btn-block">Save</button>
          </form>
        </div>
      </div>
    @endforeach
  </div>
@empty
  <p class="text-muted mb-0">
    No room units configured. Add rooms under <a href="{{ route('hotel.rooms.index') }}">Rooms</a>.
  </p>
@endforelse
