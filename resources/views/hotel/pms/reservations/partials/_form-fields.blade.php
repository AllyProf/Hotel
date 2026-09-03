@php
  $checkin = old('checkin', $options['default_checkin'] ?? now()->format('Y-m-d'));
  $checkout = old('checkout', $options['default_checkout'] ?? now()->addDay()->format('Y-m-d'));
  $selectedRoomId = (int) old('hotel_room_id', $options['default_room_id'] ?? 0);
  $selectedPlanId = (int) old('hotel_rate_plan_id', $options['default_rate_plan_id'] ?? 0);
  $guestType = old('guest_type', $options['default_guest_type'] ?? 'international');
  $dailyRate = old('daily_rate', $guestType === 'local'
    ? ($options['default_local_rate'] ?? 0)
    : ($options['default_rate'] ?? 0));
  $currency = $guestType === 'local'
    ? ($options['local_currency'] ?? 'USD')
    : ($options['currency'] ?? 'USD');
  $selectedCountryCode = old('guest_country_code', $options['default_country_code'] ?? '');
  $selectedCity = old('guest_city', '');
@endphp

<div class="row">
  <div class="col-md-3 col-lg-2">
    <div class="form-group">
      <label class="control-label">Check-In Date <span class="text-danger">*</span></label>
      <input type="date" class="form-control js-res-checkin @error('checkin') is-invalid @enderror" name="checkin"
        value="{{ $checkin }}" required>
      @error('checkin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3 col-lg-2">
    <div class="form-group">
      <label class="control-label">Check-Out Date <span class="text-danger">*</span></label>
      <input type="date" class="form-control js-res-checkout @error('checkout') is-invalid @enderror" name="checkout"
        value="{{ $checkout }}" required>
      @error('checkout')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2 col-lg-1">
    <div class="form-group">
      <label class="control-label">Nights</label>
      <input type="text" class="form-control js-res-nights" readonly tabindex="-1" value="1">
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="form-group">
      <label class="control-label">Room Type <span class="text-danger">*</span></label>
      <select class="form-control js-res-room @error('hotel_room_id') is-invalid @enderror" name="hotel_room_id" required>
        @foreach($options['rooms'] as $room)
          <option value="{{ $room['id'] }}" {{ (int) $room['id'] === $selectedRoomId ? 'selected' : '' }}>
            {{ $room['name'] }}
          </option>
        @endforeach
      </select>
      @error('hotel_room_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3 col-lg-2">
    <div class="form-group">
      <label class="control-label">Rate Plan <span class="text-danger">*</span></label>
      <select class="form-control js-res-rate-plan @error('hotel_rate_plan_id') is-invalid @enderror" name="hotel_rate_plan_id" required>
        @foreach($options['rooms'] as $room)
          @foreach($room['rate_plans'] as $plan)
            <option value="{{ $plan['id'] }}"
              data-room-id="{{ $room['id'] }}"
              data-rate="{{ $plan['base_rate'] }}"
              data-local-rate="{{ $plan['local_rate'] }}"
              data-intl-currency="{{ $plan['international_currency'] }}"
              data-local-currency="{{ $plan['local_currency'] }}"
              {{ (int) $plan['id'] === $selectedPlanId ? 'selected' : '' }}>
              {{ $plan['label'] }}
            </option>
          @endforeach
        @endforeach
      </select>
      @error('hotel_rate_plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2 col-lg-1">
    <div class="form-group">
      <label class="control-label">#Guest <span class="text-danger">*</span></label>
      <select class="form-control @error('guest_count') is-invalid @enderror" name="guest_count" required>
        @for($i = 1; $i <= 8; $i++)
          <option value="{{ $i }}" {{ (int) old('guest_count', 1) === $i ? 'selected' : '' }}>{{ $i }}</option>
        @endfor
      </select>
      @error('guest_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2 col-lg-1">
    <div class="form-group">
      <label class="control-label">#Rooms <span class="text-danger">*</span></label>
      <select class="form-control js-res-room-count @error('room_count') is-invalid @enderror" name="room_count" required>
        @for($i = 1; $i <= 10; $i++)
          <option value="{{ $i }}" {{ (int) old('room_count', 1) === $i ? 'selected' : '' }}>{{ $i }}</option>
        @endfor
      </select>
      @error('room_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3 col-lg-2">
    <div class="form-group">
      <label class="control-label">Booked By</label>
      <input type="text" class="form-control @error('booked_by') is-invalid @enderror" name="booked_by"
        value="{{ old('booked_by') }}">
      @error('booked_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Business Segment</label>
      <select class="form-control @error('segment') is-invalid @enderror" name="segment">
        @foreach($options['segments'] as $segment)
          <option value="{{ $segment }}" {{ old('segment', $options['default_segment'] ?? '') === $segment ? 'selected' : '' }}>
            {{ $segment }}
          </option>
        @endforeach
      </select>
      @error('segment')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Bill To</label>
      <div class="input-group">
        <input type="text" class="form-control js-res-bill-to @error('bill_to') is-invalid @enderror" name="bill_to"
          value="{{ old('bill_to') }}" readonly>
        <input type="hidden" name="bill_to_company_id" class="js-res-bill-to-company-id"
          value="{{ old('bill_to_company_id') }}">
        <div class="input-group-append">
          <button type="button" class="btn btn-outline-secondary js-res-bill-to-search" title="Select company">
            <i class="fa fa-search"></i>
          </button>
        </div>
      </div>
      @error('bill_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Payment Mode</label>
      <select class="form-control @error('payment_mode') is-invalid @enderror" name="payment_mode">
        @foreach($options['payment_modes'] as $mode)
          <option value="{{ $mode }}" {{ old('payment_mode', $options['default_payment_mode'] ?? '') === $mode ? 'selected' : '' }}>
            {{ $mode }}
          </option>
        @endforeach
      </select>
      @error('payment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Guest Type</label>
      <select class="form-control js-res-guest-type @error('guest_type') is-invalid @enderror" name="guest_type">
        <option value="international" {{ $guestType === 'international' ? 'selected' : '' }}>International</option>
        <option value="local" {{ $guestType === 'local' ? 'selected' : '' }}>Local</option>
      </select>
      @error('guest_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Per day avg Rate <span class="text-danger">*</span></label>
      <div class="input-group">
        <div class="input-group-prepend"><span class="input-group-text js-res-currency-label">{{ $currency }}</span></div>
        <input type="number" step="0.01" min="0" class="form-control js-res-daily-rate @error('daily_rate') is-invalid @enderror"
          name="daily_rate" value="{{ $dailyRate }}" required>
      </div>
      @error('daily_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label d-block">&nbsp;</label>
      <label class="animated-checkbox mt-2">
        <input type="checkbox" name="tax_inclusive" value="1" class="js-res-tax-inclusive"
          {{ old('tax_inclusive', true) ? 'checked' : '' }}>
        <span class="label-text">Tax Inclusive</span>
      </label>
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Per day avg Tax</label>
      <input type="number" step="0.01" min="0" class="form-control js-res-daily-tax @error('daily_tax') is-invalid @enderror"
        name="daily_tax" value="{{ old('daily_tax', 0) }}">
      @error('daily_tax')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Room No</label>
      <select class="form-control js-res-room-unit @error('room_unit_id') is-invalid @enderror" name="room_unit_id">
        <option value="">Select room</option>
        @foreach($options['rooms'] as $room)
          @foreach($room['units'] as $unit)
            <option value="{{ $unit['id'] }}" data-room-id="{{ $room['id'] }}"
              {{ (int) old('room_unit_id') === (int) $unit['id'] ? 'selected' : '' }}>
              {{ $unit['label'] }}
            </option>
          @endforeach
        @endforeach
      </select>
      @error('room_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<hr class="my-3">

<h4 class="settings-section-title">Guest Details</h4>

<div class="row">
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Name <span class="text-danger">*</span></label>
      <input type="text" class="form-control @error('guest_name') is-invalid @enderror" name="guest_name"
        value="{{ old('guest_name') }}" required>
      @error('guest_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Email</label>
      <input type="email" class="form-control @error('guest_email') is-invalid @enderror" name="guest_email"
        value="{{ old('guest_email') }}">
      @error('guest_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Phone</label>
      <div class="input-group">
        <input type="text" class="form-control @error('guest_phone') is-invalid @enderror" name="guest_phone"
          value="{{ old('guest_phone') }}">
        <div class="input-group-append">
          <button type="button" class="btn btn-outline-secondary" disabled title="Coming soon"><i class="fa fa-search"></i></button>
        </div>
      </div>
      @error('guest_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2">
    <div class="form-group">
      <label class="control-label">Gender</label>
      <select class="form-control @error('guest_gender') is-invalid @enderror" name="guest_gender">
        <option value="">Select</option>
        @foreach($options['genders'] as $gender)
          <option value="{{ $gender }}" {{ old('guest_gender') === $gender ? 'selected' : '' }}>{{ $gender }}</option>
        @endforeach
      </select>
      @error('guest_gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2">
    <div class="form-group">
      <label class="control-label d-block">&nbsp;</label>
      <label class="animated-checkbox mt-2">
        <input type="checkbox" name="guest_vip" value="1" {{ old('guest_vip') ? 'checked' : '' }}>
        <span class="label-text">VIP Guest</span>
      </label>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-3">
    <div class="form-group res-select2-host">
      <label class="control-label">Country</label>
      <select class="form-control res-select-country @error('guest_country_code') is-invalid @enderror"
        id="res_guest_country_code" name="guest_country_code">
        <option value="">Select country</option>
      </select>
      <input type="hidden" name="guest_country" id="res_guest_country_name"
        value="{{ old('guest_country', $options['default_country'] ?? '') }}">
      @error('guest_country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      @error('guest_country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group res-select2-host">
      <label class="control-label">City</label>
      <select class="form-control res-select-city @error('guest_city') is-invalid @enderror"
        id="res_guest_city" name="guest_city">
        <option value="">Select city</option>
      </select>
      @error('guest_city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2">
    <div class="form-group">
      <label class="control-label">Zip</label>
      <input type="text" class="form-control @error('guest_zip') is-invalid @enderror" name="guest_zip"
        value="{{ old('guest_zip') }}">
      @error('guest_zip')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Address</label>
      <input type="text" class="form-control @error('guest_address') is-invalid @enderror" name="guest_address"
        value="{{ old('guest_address') }}" placeholder="1234 Main St">
      @error('guest_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Special Request</label>
      <input type="text" class="form-control @error('special_request') is-invalid @enderror" name="special_request"
        value="{{ old('special_request') }}">
      @error('special_request')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Identity</label>
      <select class="form-control @error('identity_type') is-invalid @enderror" name="identity_type">
        <option value="">Select</option>
        @foreach($options['identity_types'] as $type)
          <option value="{{ $type }}" {{ old('identity_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
        @endforeach
      </select>
      @error('identity_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">ID detail</label>
      <input type="text" class="form-control @error('identity_detail') is-invalid @enderror" name="identity_detail"
        value="{{ old('identity_detail') }}">
      @error('identity_detail')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-2">
    <div class="form-group">
      <label class="control-label">Photo Id</label>
      <div class="custom-file">
        <input type="file" class="custom-file-input @error('photo_id') is-invalid @enderror" id="photo_id" name="photo_id">
        <label class="custom-file-label" for="photo_id">Upload</label>
      </div>
      @error('photo_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<label class="animated-checkbox">
  <input type="checkbox" name="send_payment_link" value="1" {{ old('send_payment_link') ? 'checked' : '' }}>
  <span class="label-text">Send Payment Link</span>
</label>

<script type="application/json" id="res-room-data">@json($options['rooms'])</script>
