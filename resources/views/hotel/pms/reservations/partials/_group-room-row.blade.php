@php
  $roomId = (int) ($line['hotel_room_id'] ?? 0);
  $planId = (int) ($line['hotel_rate_plan_id'] ?? 0);
  $roomCount = (int) ($line['room_count'] ?? 1);
  $guestCount = (int) ($line['guest_count'] ?? 1);
  $dailyRate = $line['daily_rate'] ?? 0;
@endphp

<div class="gb-room-row-wrap">
  <div class="gb-room-row">
    <div>
      <select class="form-control form-control-sm js-gb-room" name="lines[{{ $index }}][hotel_room_id]" required>
        @foreach($options['rooms'] as $room)
          <option value="{{ $room['id'] }}" {{ (int) $room['id'] === $roomId ? 'selected' : '' }}>{{ $room['name'] }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <input type="number" min="1" max="50" step="1"
        class="form-control form-control-sm js-gb-room-count js-gb-check-availability"
        name="lines[{{ $index }}][room_count]" value="{{ $roomCount }}" required>
    </div>
    <div>
      <select class="form-control form-control-sm" name="lines[{{ $index }}][guest_count]" required>
        @for($i = 1; $i <= 8; $i++)
          <option value="{{ $i }}" {{ $guestCount === $i ? 'selected' : '' }}>{{ $i }}</option>
        @endfor
      </select>
    </div>
    <div>
      <select class="form-control form-control-sm js-gb-rate-plan" name="lines[{{ $index }}][hotel_rate_plan_id]" required>
        @foreach($options['rooms'] as $room)
          @foreach($room['rate_plans'] as $plan)
            <option value="{{ $plan['id'] }}" data-room-id="{{ $room['id'] }}" data-rate="{{ $plan['base_rate'] }}"
              {{ (int) $plan['id'] === $planId ? 'selected' : '' }}
              {{ (int) $room['id'] !== $roomId ? 'hidden disabled' : '' }}>
              {{ $plan['label'] }}
            </option>
          @endforeach
        @endforeach
      </select>
    </div>
    <div>
      <input type="number" step="0.01" min="0" class="form-control form-control-sm js-gb-daily-rate"
        name="lines[{{ $index }}][daily_rate]" value="{{ $dailyRate }}" required>
    </div>
    <div class="text-center">
      @if(!empty($showRemove))
        <button type="button" class="gb-remove-row js-gb-remove-row" title="Remove row">&times;</button>
      @endif
    </div>
  </div>
  <div class="gb-availability-msg js-gb-availability-msg text-muted small"></div>
</div>
