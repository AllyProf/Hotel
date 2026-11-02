@php
  $checkin = $booking['checkin'] ?? ($options['default_checkin'] ?? now()->format('Y-m-d'));
  $checkout = $booking['checkout'] ?? ($options['default_checkout'] ?? now()->addDay()->format('Y-m-d'));
  $roomId = (int) ($booking['hotel_room_id'] ?? ($options['default_room_id'] ?? 0));
  $planId = (int) ($booking['hotel_rate_plan_id'] ?? ($options['default_rate_plan_id'] ?? 0));
  $roomCount = (int) ($booking['room_count'] ?? 1);
  $guestCount = (int) ($booking['guest_count'] ?? 1);
  $taxInclusive = ! empty($booking['tax_inclusive']);
  $countryCode = $booking['guest_country_code'] ?? ($options['default_country_code'] ?? '');
  $city = $booking['guest_city'] ?? '';
  $phoneCountryCode = $booking['guest_phone_country_code'] ?? ($options['default_phone_code'] ?? '');
  $guestPhone = $booking['guest_phone'] ?? '';
  $discountType = $booking['discount_type'] ?? 'percent';
  $discountValue = $booking['discount_value'] ?? ($booking['discount_percent'] ?? 0);
  $advanceAmount = $booking['advance_amount'] ?? 0;
  $advanceComments = $booking['advance_comments'] ?? '';
@endphp

<div class="mb-booking-block" data-index="{{ $index }}">
  <div class="mb-booking-block__row mb-booking-block__row--top">
    <div class="mb-field">
      <label class="control-label small">Check-in</label>
      <input type="date" class="form-control form-control-sm js-mb-checkin js-mb-check-availability"
        name="bookings[{{ $index }}][checkin]" value="{{ $checkin }}" required>
    </div>
    <div class="mb-field">
      <label class="control-label small">Check-out</label>
      <input type="date" class="form-control form-control-sm js-mb-checkout js-mb-check-availability"
        name="bookings[{{ $index }}][checkout]" value="{{ $checkout }}" required>
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Room Type</label>
      <select class="form-control form-control-sm js-mb-room js-mb-check-availability" name="bookings[{{ $index }}][hotel_room_id]" required>
        @foreach($options['rooms'] as $room)
          <option value="{{ $room['id'] }}" {{ (int) $room['id'] === $roomId ? 'selected' : '' }}>{{ $room['name'] }}</option>
        @endforeach
      </select>
    </div>
    <div class="mb-field">
      <label class="control-label small">Rate Plan</label>
      <select class="form-control form-control-sm js-mb-rate-plan" name="bookings[{{ $index }}][hotel_rate_plan_id]" required>
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
    <div class="mb-field mb-field--narrow">
      <label class="control-label small">#Rooms</label>
      <input type="number" min="1" max="50" step="1"
        class="form-control form-control-sm js-mb-room-count js-mb-check-availability"
        name="bookings[{{ $index }}][room_count]" value="{{ $roomCount }}" required>
    </div>
    <div class="mb-field mb-field--narrow">
      <label class="control-label small">#Guests</label>
      <select class="form-control form-control-sm" name="bookings[{{ $index }}][guest_count]" required>
        @for($i = 1; $i <= 8; $i++)
          <option value="{{ $i }}" {{ $guestCount === $i ? 'selected' : '' }}>{{ $i }}</option>
        @endfor
      </select>
    </div>
    <div class="mb-field mb-field--check">
      <label class="animated-checkbox mb-0 mt-4">
        <input type="checkbox" name="bookings[{{ $index }}][tax_inclusive]" value="1" class="js-mb-tax-inclusive"
          {{ $taxInclusive ? 'checked' : '' }}>
        <span class="label-text small">Tax Inclusive (VAT {{ number_format($options['vat_rate'] ?? 18, 2) }}%)</span>
      </label>
    </div>
    <div class="mb-field mb-field--actions">
      <button type="button" class="btn btn-primary btn-sm mb-action-btn js-mb-add-row" title="Add booking">+</button>
      @if(!empty($showRemove))
        <button type="button" class="btn btn-secondary btn-sm mb-action-btn js-mb-remove-row" title="Remove booking">−</button>
      @endif
    </div>
  </div>

  <div class="mb-availability-msg js-mb-availability-msg small"></div>

  <div class="mb-booking-block__row mb-booking-block__row--bottom">
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Name <span class="text-danger">*</span></label>
      <div class="input-group input-group-sm">
        <input type="text" class="form-control js-mb-guest-name" name="bookings[{{ $index }}][guest_name]"
          value="{{ $booking['guest_name'] ?? '' }}" required>
        <div class="input-group-append">
          <button type="button" class="btn btn-outline-secondary js-mb-guest-search" title="Search guest">
            <i class="fa fa-search"></i>
          </button>
        </div>
      </div>
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Email</label>
      <div class="mb-email-wrap">
        <input type="email" class="form-control form-control-sm js-mb-guest-email" name="bookings[{{ $index }}][guest_email]"
          value="{{ $booking['guest_email'] ?? '' }}" placeholder="Enter email" autocomplete="off">
        <div class="mb-email-suggest js-mb-email-suggest" role="listbox"></div>
      </div>
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Phone</label>
      <div class="mb-phone-group">
        <select class="js-mb-phone-code" id="mb_phone_code_{{ $index }}" aria-label="Phone country code"></select>
        <input type="hidden" class="js-mb-phone-country-code" name="bookings[{{ $index }}][guest_phone_country_code]"
          value="{{ $phoneCountryCode }}">
        <input type="text" class="form-control form-control-sm js-mb-guest-phone" name="bookings[{{ $index }}][guest_phone]"
          value="{{ $guestPhone }}" placeholder="Phone number">
      </div>
    </div>
    <div class="mb-field">
      <label class="control-label small">Payment Mode</label>
      <select class="form-control form-control-sm js-mb-payment-mode" name="bookings[{{ $index }}][payment_mode]">
        @foreach($options['payment_modes'] as $mode)
          <option value="{{ $mode }}" {{ ($booking['payment_mode'] ?? $options['default_payment_mode'] ?? '') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
        @endforeach
      </select>
    </div>
    <div class="mb-field js-mb-payment-provider-wrap d-none">
      <label class="control-label small js-mb-payment-provider-label">Provider</label>
      <select class="form-control form-control-sm js-mb-payment-provider" name="bookings[{{ $index }}][payment_provider]"
        data-selected="{{ $booking['payment_provider'] ?? '' }}">
        <option value="">Select provider</option>
      </select>
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">City</label>
      <select class="form-control form-control-sm js-mb-city" id="mb_city_{{ $index }}" name="bookings[{{ $index }}][guest_city]">
        <option value="">Select city</option>
        @if($city !== '')
          <option value="{{ $city }}" selected>{{ $city }}</option>
        @endif
      </select>
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Country</label>
      <select class="form-control form-control-sm js-mb-country" id="mb_country_{{ $index }}" name="bookings[{{ $index }}][guest_country_code]">
        <option value="">Select country</option>
      </select>
      <input type="hidden" class="js-mb-country-name" name="bookings[{{ $index }}][guest_country]"
        value="{{ $booking['guest_country'] ?? ($options['default_country'] ?? '') }}">
    </div>
  </div>

  <div class="mb-booking-block__row mb-booking-block__row--payment">
    <div class="mb-field">
      <label class="control-label small">Amount Paid</label>
      <input type="number" step="0.01" min="0" class="form-control form-control-sm js-mb-advance-amount"
        name="bookings[{{ $index }}][advance_amount]" value="{{ $advanceAmount }}">
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Discount</label>
      <div class="mb-discount-wrap">
        <select class="form-control form-control-sm js-mb-discount-type" name="bookings[{{ $index }}][discount_type]">
          <option value="percent" {{ $discountType === 'percent' ? 'selected' : '' }}>By %</option>
          <option value="amount" {{ $discountType === 'amount' ? 'selected' : '' }}>By amount</option>
        </select>
        <div class="input-group input-group-sm">
          <input type="number" step="0.01" min="0" @if($discountType === 'percent') max="100" @endif
            class="form-control js-mb-discount" name="bookings[{{ $index }}][discount_value]" value="{{ $discountValue }}">
          <div class="input-group-append">
            <span class="input-group-text js-mb-discount-suffix">{{ $discountType === 'amount' ? ($options['currency'] ?? 'USD') : '%' }}</span>
          </div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm js-mb-discount-undo">Undo</button>
      </div>
    </div>
    <div class="mb-field mb-field--wide">
      <label class="control-label small">Payment notes</label>
      <input type="text" class="form-control form-control-sm js-mb-advance-comments"
        name="bookings[{{ $index }}][advance_comments]" value="{{ $advanceComments }}"
        placeholder="Payment or booking notes">
    </div>
    <div class="mb-field mb-field--summary">
      <div class="mb-row-total-summary">
        <span>Subtotal: <strong class="js-mb-subtotal">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
        <span>Discount: <strong class="js-mb-discount-amount">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
        <span>Total: <strong class="js-mb-total">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
        <span class="js-mb-remaining-wrap is-hidden">
          Remaining: <strong class="js-mb-remaining">0.00</strong> {{ $options['currency'] ?? 'USD' }}
        </span>
      </div>
    </div>
  </div>
</div>
