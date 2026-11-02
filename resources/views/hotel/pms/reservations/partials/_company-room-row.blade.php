@php
  $roomId = (int) ($line['hotel_room_id'] ?? ($options['default_room_id'] ?? 0));
  $planId = (int) ($line['hotel_rate_plan_id'] ?? ($options['default_rate_plan_id'] ?? 0));
  $roomCount = (int) ($line['room_count'] ?? 1);
  $guestCount = (int) ($line['guest_count'] ?? 1);
@endphp

<div class="cb-room-row-wrap">
  <div class="cb-room-row">
    <div>
      <select class="form-control form-control-sm js-cb-room" name="lines[{{ $index }}][hotel_room_id]" required>
        @foreach($options['rooms'] as $room)
          <option value="{{ $room['id'] }}" {{ (int) $room['id'] === $roomId ? 'selected' : '' }}>{{ $room['name'] }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <input type="number" min="1" max="50" step="1"
        class="form-control form-control-sm js-cb-room-count js-cb-check-availability"
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
      <select class="form-control form-control-sm js-cb-rate-plan" name="lines[{{ $index }}][hotel_rate_plan_id]" required>
        @foreach($options['rooms'] as $room)
          @foreach($room['rate_plans'] as $plan)
            <option value="{{ $plan['id'] }}" data-rate="{{ $plan['base_rate'] }}"
              {{ (int) $plan['id'] === $planId ? 'selected' : '' }}
              {{ (int) $room['id'] !== $roomId ? 'hidden disabled' : '' }}>
              {{ $plan['label'] }}
            </option>
          @endforeach
        @endforeach
      </select>
    </div>
    <div class="text-center">
      @if(!empty($showRemove))
        <button type="button" class="cb-remove-row js-cb-remove-row" title="Remove row">&times;</button>
      @endif
    </div>
  </div>
  <div class="cb-availability-msg js-cb-availability-msg small"></div>
</div>
