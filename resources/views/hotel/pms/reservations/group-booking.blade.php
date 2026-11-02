@extends('layouts.app')

@section('title', 'Group Bookings')

@push('styles')
  <style>
    .gb-page-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
    }

    .gb-page-head h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 500;
      color: #333;
    }

    .gb-room-table {
      background: #f5f5f5;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      padding: 12px;
      margin-bottom: 16px;
    }

    .gb-room-table__head,
    .gb-room-row {
      display: grid;
      grid-template-columns: 1.4fr 0.7fr 0.9fr 0.9fr 0.9fr 40px;
      gap: 10px;
      align-items: end;
    }

    .gb-room-table__head {
      font-size: 12px;
      font-weight: 700;
      color: #555;
      margin-bottom: 8px;
      padding: 0 4px;
    }

    .gb-room-row-wrap {
      margin-bottom: 8px;
    }

    .gb-availability-msg {
      padding: 2px 4px 0;
      min-height: 18px;
    }

    .gb-availability-msg.is-ok { color: #28a745; }
    .gb-availability-msg.is-bad { color: #dc3545; }

    .gb-add-row {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: none;
      background: var(--brand, #940000);
      color: #fff;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
    }

    .gb-add-row:hover { background: var(--brand-dark, #7a0000); }

    .gb-remove-row {
      width: 36px;
      height: 36px;
      border: none;
      background: transparent;
      color: #999;
      font-size: 18px;
      cursor: pointer;
    }

    .gb-remove-row:hover { color: #c0392b; }

    .gb-section-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--brand, #940000);
      border-bottom: 1px solid #ddd;
      padding-bottom: 6px;
      margin: 18px 0 12px;
    }

    .gb-discount-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .gb-discount-wrap .input-group { max-width: 140px; }
    .gb-discount-wrap .js-gb-discount-type { max-width: 120px; flex: 0 0 auto; }

    .gb-total-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 16px 24px;
      justify-content: flex-end;
      margin: 8px 0 16px;
      font-size: 14px;
      color: #444;
    }

    .gb-total-summary strong { color: var(--brand, #940000); font-size: 16px; }

    .gb-total-summary .js-gb-remaining-wrap.is-hidden { display: none; }

    .gb-total-summary .js-gb-remaining-wrap.is-due strong { color: #c0392b; }

    .gb-phone-group {
      display: flex;
      align-items: stretch;
    }

    .gb-phone-group .select2-container {
      width: 118px !important;
      flex: 0 0 118px;
    }

    .gb-phone-group .select2-container .select2-selection--single {
      height: 38px;
      border-radius: 4px 0 0 4px;
      border-right: 0;
      display: flex;
      align-items: center;
    }

    .gb-phone-group .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px;
      padding-left: 8px;
      padding-right: 24px;
    }

    .gb-phone-group .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
    }

    .gb-phone-group .js-gb-guest-phone {
      border-radius: 0 4px 4px 0;
      flex: 1 1 auto;
    }

    .gb-email-wrap { position: relative; }

    .gb-email-suggest {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 20;
      background: #fff;
      border: 1px solid #ddd;
      border-top: 0;
      border-radius: 0 0 4px 4px;
      max-height: 180px;
      overflow-y: auto;
      display: none;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }

    .gb-email-suggest.is-open { display: block; }

    .gb-email-suggest button {
      display: block;
      width: 100%;
      border: 0;
      background: #fff;
      text-align: left;
      padding: 8px 12px;
      cursor: pointer;
    }

    .gb-email-suggest button:hover,
    .gb-email-suggest button.is-active { background: #f5f5f5; }

    .gb-select2-host { min-height: 38px; }
    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 6px; }

    @media (max-width: 991px) {
      .gb-room-table__head { display: none; }
      .gb-room-row {
        grid-template-columns: 1fr 1fr;
        background: #fff;
        border: 1px solid #e8e8e8;
        border-radius: 4px;
        padding: 10px;
      }
    }
  </style>
@endpush

@section('content')
  @php
    $checkin = old('checkin', $options['default_checkin'] ?? now()->format('Y-m-d'));
    $checkout = old('checkout', $options['default_checkout'] ?? now()->addDay()->format('Y-m-d'));
    $oldLines = old('lines', [[
      'hotel_room_id' => $options['default_room_id'] ?? '',
      'hotel_rate_plan_id' => $options['default_rate_plan_id'] ?? '',
      'room_count' => 1,
      'guest_count' => 1,
      'daily_rate' => $options['default_rate'] ?? 0,
    ]]);
    $selectedCountryCode = old('guest_country_code', $options['default_country_code'] ?? '');
    $selectedCity = old('guest_city', '');
    $discountType = old('discount_type', 'percent');
    $discountValue = old('discount_value', old('discount_percent', 0));
    $phoneCountryCode = old('guest_phone_country_code', $hotel->phone_country_code ?? '+255');
    $guestPhone = old('guest_phone', '');
    $defaultBookedBy = old('booked_by', auth()->user()->name ?? '');
  @endphp

  <div class="gb-page-head">
    <h3>Group Bookings</h3>
    <a href="{{ route('hotel.reservations.index') }}" class="btn btn-primary btn-sm">Back</a>
  </div>

  <div class="tile">
    <div class="tile-body">
      <form action="{{ route('hotel.reservations.group-booking.store') }}" method="POST" id="gbForm">
        @csrf

        <div class="row align-items-end">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Group Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('group_name') is-invalid @enderror" name="group_name"
                value="{{ old('group_name') }}" required>
              @error('group_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Check-in <span class="text-danger">*</span></label>
              <input type="date" class="form-control js-gb-checkin @error('checkin') is-invalid @enderror" name="checkin"
                value="{{ $checkin }}" required>
              @error('checkin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Check-out <span class="text-danger">*</span></label>
              <input type="date" class="form-control js-gb-checkout @error('checkout') is-invalid @enderror" name="checkout"
                value="{{ $checkout }}" required>
              @error('checkout')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-2">
            <label class="animated-checkbox d-block mb-2">
              <input type="checkbox" name="tax_inclusive" value="1" {{ old('tax_inclusive', true) ? 'checked' : '' }}>
              <span class="label-text">Tax Inclusive</span>
            </label>
          </div>
        </div>

        <div class="gb-room-table">
          <div class="gb-room-table__head">
            <div>Room Type</div>
            <div>#rooms</div>
            <div>#Guests/ room</div>
            <div>Rateplan</div>
            <div>Rate/ day</div>
            <div></div>
          </div>
          <div id="gbRoomRows">
            @foreach($oldLines as $index => $line)
              @include('hotel.pms.reservations.partials._group-room-row', [
                'index' => $index,
                'line' => $line,
                'options' => $options,
                'showRemove' => $index > 0,
              ])
            @endforeach
          </div>
          <div class="text-right mt-2">
            <button type="button" class="gb-add-row js-gb-add-row" title="Add room type">+</button>
          </div>
        </div>

        <div class="gb-total-summary">
          <span>Subtotal: <strong class="js-gb-subtotal">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
          <span>Discount: <strong class="js-gb-discount-amount">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
          <span>Total: <strong class="js-gb-total">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
          <span class="js-gb-remaining-wrap is-hidden">
            Remaining: <strong class="js-gb-remaining">0.00</strong> {{ $options['currency'] ?? 'USD' }}
          </span>
        </div>

        @error('lines')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Payment Mode</label>
              <select class="form-control js-gb-payment-mode @error('payment_mode') is-invalid @enderror" name="payment_mode">
                @foreach($options['payment_modes'] as $mode)
                  <option value="{{ $mode }}" {{ old('payment_mode', $options['default_payment_mode'] ?? '') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                @endforeach
              </select>
              @error('payment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3 js-gb-payment-provider-wrap d-none">
            <div class="form-group">
              <label class="control-label js-gb-payment-provider-label">Provider</label>
              <select class="form-control js-gb-payment-provider @error('payment_provider') is-invalid @enderror" name="payment_provider"
                data-selected="{{ old('payment_provider') }}">
                <option value="">Select provider</option>
              </select>
              @error('payment_provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Amount Paid</label>
              <input type="number" step="0.01" min="0" class="form-control js-gb-advance-amount" name="advance_amount"
                value="{{ old('advance_amount', 0) }}">
            </div>
          </div>
          <div class="col-md-3 js-gb-bill-to-wrap">
            <div class="form-group">
              <label class="control-label js-gb-bill-to-label">Bill To</label>
              <div class="input-group">
                <input type="text" class="form-control js-res-bill-to @error('bill_to') is-invalid @enderror" name="bill_to"
                  value="{{ old('bill_to') }}" readonly>
                <input type="hidden" name="bill_to_company_id" class="js-res-bill-to-company-id" value="{{ old('bill_to_company_id') }}">
                <input type="hidden" class="js-gb-bill-to-company-email" value="">
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-res-bill-to-search" title="Select company">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
              @error('bill_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              @error('bill_to_company_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              <label class="animated-checkbox mt-2 mb-0 js-gb-send-invoice-wrap d-none">
                <input type="checkbox" name="send_invoice_to_company" value="1" class="js-gb-send-invoice"
                  {{ old('send_invoice_to_company') ? 'checked' : '' }}>
                <span class="label-text">Send invoice to company email</span>
              </label>
              <small class="text-muted d-none js-gb-send-invoice-hint"></small>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Guest Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control js-gb-guest-name @error('guest_name') is-invalid @enderror" name="guest_name"
                  value="{{ old('guest_name') }}" placeholder="Group lead / main guest" required>
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-gb-guest-search" title="Search guest">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
              @error('guest_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Booked By</label>
              <input type="text" class="form-control @error('booked_by') is-invalid @enderror" name="booked_by"
                value="{{ $defaultBookedBy }}" placeholder="Staff or agent name">
              @error('booked_by')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-5">
            <div class="form-group gb-email-wrap">
              <label class="control-label">Email</label>
              <input type="email" class="form-control js-gb-guest-email" name="guest_email" value="{{ old('guest_email') }}"
                placeholder="Enter email" autocomplete="off">
              <div class="gb-email-suggest js-gb-email-suggest" role="listbox"></div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Phone</label>
              <div class="gb-phone-group">
                <select class="js-gb-phone-code" id="gb_guest_phone_code" aria-label="Phone country code"></select>
                <input type="hidden" name="guest_phone_country_code" id="gb_guest_phone_country_code" value="{{ $phoneCountryCode }}">
                <input type="text" class="form-control js-gb-guest-phone @error('guest_phone') is-invalid @enderror" name="guest_phone"
                  value="{{ $guestPhone }}" placeholder="Phone number">
              </div>
              @error('guest_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group gb-select2-host">
              <label class="control-label">City</label>
              <select class="form-control" id="gb_guest_city" name="guest_city">
                <option value="">Select city</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group gb-select2-host">
              <label class="control-label">Country</label>
              <select class="form-control" id="gb_guest_country_code" name="guest_country_code">
                <option value="">Select country</option>
              </select>
              <input type="hidden" name="guest_country" id="gb_guest_country_name" value="{{ old('guest_country', $options['default_country'] ?? '') }}">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Special Request</label>
              <input type="text" class="form-control" name="special_request" value="{{ old('special_request') }}"
                placeholder="Ex : Need Extra Pillow.">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Segment</label>
              <select class="form-control" name="segment">
                @foreach($options['segments'] as $segment)
                  <option value="{{ $segment }}" {{ old('segment', $options['default_segment'] ?? '') === $segment ? 'selected' : '' }}>{{ $segment }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Discount Value</label>
              <div class="gb-discount-wrap">
                <select class="form-control js-gb-discount-type" name="discount_type">
                  <option value="percent" {{ $discountType === 'percent' ? 'selected' : '' }}>By %</option>
                  <option value="amount" {{ $discountType === 'amount' ? 'selected' : '' }}>By amount</option>
                </select>
                <div class="input-group">
                  <input type="number" step="0.01" min="0" @if($discountType === 'percent') max="100" @endif
                    class="form-control js-gb-discount" name="discount_value" value="{{ $discountValue }}">
                  <div class="input-group-append">
                    <span class="input-group-text js-gb-discount-suffix">{{ $discountType === 'amount' ? ($options['currency'] ?? 'USD') : '%' }}</span>
                  </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm js-gb-discount-undo">Undo</button>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <div class="form-group">
              <label class="control-label">Comments</label>
              <input type="text" class="form-control" name="advance_comments" value="{{ old('advance_comments') }}" placeholder="Payment or booking notes">
            </div>
          </div>
        </div>

        <div class="mt-4 pt-3 border-top">
          <button type="submit" class="btn btn-primary btn-lg js-gb-submit">
            <i class="fa fa-check-circle"></i> Create Group Booking
          </button>
          <a href="{{ route('hotel.reservations.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  @include('hotel.pms.reservations.partials._company-modals')
  @include('hotel.pms.reservations.partials._guest-search-modal')

  <template id="gbRoomRowTemplate">
    @include('hotel.pms.reservations.partials._group-room-row', [
      'index' => '__INDEX__',
      'line' => [
        'hotel_room_id' => $options['default_room_id'] ?? '',
        'hotel_rate_plan_id' => $options['default_rate_plan_id'] ?? '',
        'room_count' => 1,
        'guest_count' => 1,
        'daily_rate' => $options['default_rate'] ?? 0,
      ],
      'options' => $options,
      'showRemove' => true,
    ])
  </template>

  <script type="application/json" id="gb-room-data">@json($options['rooms'])</script>
  <script type="application/json" id="gb-payment-provider-config">@json($options['payment_provider_config'] ?? ['bank_providers' => [], 'mobile_payment_providers' => []])</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var roomData = [];
      var paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
      try {
        roomData = JSON.parse(document.getElementById('gb-room-data').textContent || '[]');
        paymentProviderConfig = JSON.parse(document.getElementById('gb-payment-provider-config').textContent || '{}');
      } catch (e) {
        roomData = [];
        paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
      }

      function providerOptionsForMode(mode) {
        var value = (mode || '').toLowerCase();

        if (value.indexOf('bank') !== -1) {
          return paymentProviderConfig.bank_providers || [];
        }

        if (/mobile|m-pesa|mpesa|wallet|tigo|airtel|halopesa|upi/.test(value)) {
          return paymentProviderConfig.mobile_payment_providers || [];
        }

        return [];
      }

      function updatePaymentProviderField(modeSelect, wrap, labelEl, providerSelect) {
        if (!modeSelect || !wrap || !providerSelect) return;

        var options = providerOptionsForMode(modeSelect.value);
        var modeValue = (modeSelect.value || '').toLowerCase();
        var previous = providerSelect.value || providerSelect.getAttribute('data-selected') || '';

        providerSelect.innerHTML = '<option value="">Select provider</option>';
        options.forEach(function (name) {
          var option = document.createElement('option');
          option.value = name;
          option.textContent = name;
          if (previous && previous === name) {
            option.selected = true;
          }
          providerSelect.appendChild(option);
        });

        providerSelect.removeAttribute('data-selected');

        var show = options.length > 0;
        wrap.classList.toggle('d-none', !show);
        providerSelect.required = show;

        if (labelEl) {
          var label = modeValue.indexOf('bank') !== -1 ? 'Bank' : 'Mobile Payment Provider';
          labelEl.innerHTML = label + (show ? ' <span class="text-danger">*</span>' : '');
        }

        if (!show) {
          providerSelect.value = '';
        }
      }

      function isBillToCompanyMode(mode) {
        return /bill\s*to\s*company/i.test(mode || '');
      }

      function updateBillToRequirement() {
        var paymentMode = document.querySelector('.js-gb-payment-mode');
        var billToLabel = document.querySelector('.js-gb-bill-to-label');
        var billToInput = document.querySelector('.js-res-bill-to');
        var billToCompanyId = document.querySelector('.js-res-bill-to-company-id');
        var required = paymentMode && isBillToCompanyMode(paymentMode.value);

        if (billToLabel) {
          billToLabel.innerHTML = 'Bill To' + (required ? ' <span class="text-danger">*</span>' : '');
        }

        if (billToInput) billToInput.required = required;
        if (billToCompanyId) billToCompanyId.required = required;
        updateSendInvoiceOption();
      }

      function updateSendInvoiceOption() {
        var paymentMode = document.querySelector('.js-gb-payment-mode');
        var wrap = document.querySelector('.js-gb-send-invoice-wrap');
        var hint = document.querySelector('.js-gb-send-invoice-hint');
        var checkbox = document.querySelector('.js-gb-send-invoice');
        var companyId = document.querySelector('.js-res-bill-to-company-id');
        var companyEmail = document.querySelector('.js-gb-bill-to-company-email');
        var show = paymentMode && isBillToCompanyMode(paymentMode.value);

        if (wrap) wrap.classList.toggle('d-none', !show);
        if (!show) {
          if (checkbox) checkbox.checked = false;
          if (hint) {
            hint.classList.add('d-none');
            hint.textContent = '';
          }
          return;
        }

        var hasCompany = companyId && companyId.value;
        var email = companyEmail ? (companyEmail.value || '').trim() : '';

        if (hint) {
          if (!hasCompany) {
            hint.textContent = 'Select a company to enable invoice email.';
            hint.classList.remove('d-none');
          } else if (!email) {
            hint.textContent = 'Selected company has no email on file.';
            hint.classList.remove('d-none');
          } else {
            hint.textContent = 'Invoice will be sent to ' + email + '.';
            hint.classList.remove('d-none');
          }
        }

        if (checkbox) {
          checkbox.disabled = !hasCompany || !email;
          if (checkbox.disabled) checkbox.checked = false;
        }
      }

      window.onGbCompanySelected = function (company) {
        var emailInput = document.querySelector('.js-gb-bill-to-company-email');
        if (emailInput) {
          emailInput.value = company && company.email && company.email !== '—' ? company.email : '';
        }
        updateSendInvoiceOption();
      };

      function bindPaymentProviderFields() {
        var paymentMode = document.querySelector('.js-gb-payment-mode');
        var paymentProviderWrap = document.querySelector('.js-gb-payment-provider-wrap');
        var paymentProviderLabel = document.querySelector('.js-gb-payment-provider-label');
        var paymentProvider = document.querySelector('.js-gb-payment-provider');

        if (paymentMode) {
          paymentMode.addEventListener('change', function () {
            updatePaymentProviderField(paymentMode, paymentProviderWrap, paymentProviderLabel, paymentProvider);
            updateBillToRequirement();
          });
          updatePaymentProviderField(paymentMode, paymentProviderWrap, paymentProviderLabel, paymentProvider);
          updateBillToRequirement();
        }
      }

      var checkin = document.querySelector('.js-gb-checkin');
      var checkout = document.querySelector('.js-gb-checkout');
      var rowsWrap = document.getElementById('gbRoomRows');
      var rowIndex = rowsWrap ? rowsWrap.querySelectorAll('.gb-room-row-wrap').length : 0;
      var availabilityUrl = @json(route('hotel.reservations.group-booking.check-availability'));
      var csrf = @json(csrf_token());
      var availabilityTimers = new WeakMap();
      var oldCountryCode = @json($selectedCountryCode);
      var oldCity = @json($selectedCity);
      var oldPhoneCode = @json($phoneCountryCode);
      var emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'live.com', 'aol.com', 'protonmail.com', 'mail.com', 'zoho.com',
        'yandex.com', 'gmx.com', 'msn.com'
      ];

      function planOptionsForRoom(roomId, selectedPlanId) {
        var room = roomData.find(function (item) { return String(item.id) === String(roomId); });
        if (!room) return '';
        return (room.rate_plans || []).map(function (plan) {
          var selected = String(plan.id) === String(selectedPlanId) ? ' selected' : '';
          return '<option value="' + plan.id + '" data-rate="' + plan.base_rate + '"' + selected + '>' + plan.label + '</option>';
        }).join('');
      }

      function otherRoomCounts(currentWrap) {
        var others = [];
        if (!rowsWrap) return others;

        rowsWrap.querySelectorAll('.gb-room-row-wrap').forEach(function (wrap) {
          if (wrap === currentWrap) return;
          var roomSelect = wrap.querySelector('.js-gb-room');
          var countInput = wrap.querySelector('.js-gb-room-count');
          if (!roomSelect || !countInput || !roomSelect.value) return;

          var roomCount = parseInt(countInput.value || '0', 10);
          if (roomCount < 1) return;

          others.push({
            hotel_room_id: parseInt(roomSelect.value, 10),
            room_count: roomCount
          });
        });

        return others;
      }

      function checkRowAvailability(wrap) {
        if (!wrap) return;

        var msg = wrap.querySelector('.js-gb-availability-msg');
        var roomSelect = wrap.querySelector('.js-gb-room');
        var countInput = wrap.querySelector('.js-gb-room-count');

        if (!msg || !roomSelect || !countInput || !checkin || !checkout) return;

        var roomCount = parseInt(countInput.value || '0', 10);
        if (!checkin.value || !checkout.value || !roomSelect.value || roomCount < 1) {
          msg.textContent = '';
          msg.className = 'gb-availability-msg js-gb-availability-msg text-muted small';
          return;
        }

        msg.textContent = 'Checking availability...';
        msg.className = 'gb-availability-msg js-gb-availability-msg text-muted small';

        fetch(availabilityUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({
            checkin: checkin.value,
            checkout: checkout.value,
            hotel_room_id: parseInt(roomSelect.value, 10),
            room_count: roomCount,
            other_room_counts: otherRoomCounts(wrap)
          })
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            msg.textContent = data.message || '';
            msg.className = 'gb-availability-msg js-gb-availability-msg small ' + (data.ok ? 'is-ok' : 'is-bad');
          })
          .catch(function () {
            msg.textContent = 'Could not check availability.';
            msg.className = 'gb-availability-msg js-gb-availability-msg small is-bad';
          });
      }

      function scheduleAvailability(wrap) {
        clearTimeout(availabilityTimers.get(wrap));
        availabilityTimers.set(wrap, setTimeout(function () {
          checkRowAvailability(wrap);
        }, 350));
      }

      function refreshAllAvailability() {
        if (!rowsWrap) return;
        rowsWrap.querySelectorAll('.gb-room-row-wrap').forEach(scheduleAvailability);
      }

      function bindRow(wrap) {
        var row = wrap.querySelector('.gb-room-row');
        if (!row) return;

        var roomSelect = wrap.querySelector('.js-gb-room');
        var planSelect = wrap.querySelector('.js-gb-rate-plan');
        var rateInput = wrap.querySelector('.js-gb-daily-rate');
        var countInput = wrap.querySelector('.js-gb-room-count');
        var removeBtn = wrap.querySelector('.js-gb-remove-row');

        function syncPlans() {
          if (!roomSelect || !planSelect) return;
          var selected = planSelect.value;
          planSelect.innerHTML = planOptionsForRoom(roomSelect.value, selected);
          var option = planSelect.options[planSelect.selectedIndex];
          if (option && rateInput) {
            rateInput.value = option.getAttribute('data-rate') || rateInput.value;
          }
          refreshAllAvailability();
        }

        if (roomSelect) {
          roomSelect.addEventListener('change', syncPlans);
        }
        if (planSelect) {
          planSelect.addEventListener('change', function () {
            var option = planSelect.options[planSelect.selectedIndex];
            if (option && rateInput) {
              rateInput.value = option.getAttribute('data-rate') || '0';
            }
            updateTotals();
          });
        }

        if (rateInput) {
          rateInput.addEventListener('input', updateTotals);
          rateInput.addEventListener('change', updateTotals);
        }

        if (countInput) {
          countInput.addEventListener('input', function () {
            refreshAllAvailability();
            updateTotals();
          });
          countInput.addEventListener('change', function () {
            refreshAllAvailability();
            updateTotals();
          });
        }

        if (removeBtn) {
          removeBtn.addEventListener('click', function () {
            wrap.remove();
            reindexRows();
            refreshAllAvailability();
            updateTotals();
          });
        }

        syncPlans();
      }

      function reindexRows() {
        if (!rowsWrap) return;
        rowsWrap.querySelectorAll('.gb-room-row-wrap').forEach(function (wrap, index) {
          wrap.querySelectorAll('[name]').forEach(function (input) {
            input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
          });
        });
        rowIndex = rowsWrap.querySelectorAll('.gb-room-row-wrap').length;
      }

      if (rowsWrap) {
        rowsWrap.querySelectorAll('.gb-room-row-wrap').forEach(bindRow);
      }

      document.querySelector('.js-gb-add-row')?.addEventListener('click', function () {
        var template = document.getElementById('gbRoomRowTemplate');
        if (!template || !rowsWrap) return;
        var html = template.innerHTML.replace(/__INDEX__/g, String(rowIndex));
        var container = document.createElement('div');
        container.innerHTML = html.trim();
        var wrap = container.firstElementChild;
        rowsWrap.appendChild(wrap);
        bindRow(wrap);
        rowIndex += 1;
        updateTotals();
        refreshAllAvailability();
      });

      if (checkin) {
        checkin.addEventListener('change', function () {
          refreshAllAvailability();
          updateTotals();
        });
      }
      if (checkout) {
        checkout.addEventListener('change', function () {
          refreshAllAvailability();
          updateTotals();
        });
      }

      var discountCurrency = @json($options['currency'] ?? 'USD');

      function formatMoney(value) {
        return (Math.round(value * 100) / 100).toFixed(2);
      }

      function calcNights() {
        if (!checkin || !checkout || !checkin.value || !checkout.value) return 1;
        var start = new Date(checkin.value + 'T00:00:00');
        var end = new Date(checkout.value + 'T00:00:00');
        var nights = Math.round((end - start) / 86400000);
        return Math.max(1, nights || 1);
      }

      function calcSubtotal() {
        var nights = calcNights();
        var subtotal = 0;

        if (!rowsWrap) return 0;

        rowsWrap.querySelectorAll('.gb-room-row-wrap').forEach(function (wrap) {
          var rate = parseFloat(wrap.querySelector('.js-gb-daily-rate')?.value || '0') || 0;
          var count = parseInt(wrap.querySelector('.js-gb-room-count')?.value || '0', 10) || 0;
          subtotal += rate * nights * count;
        });

        return subtotal;
      }

      function updateTotals() {
        var subtotal = calcSubtotal();
        var typeSelect = document.querySelector('.js-gb-discount-type');
        var discountInput = document.querySelector('.js-gb-discount');
        var discountValue = parseFloat(discountInput?.value || '0') || 0;
        var discountAmount = 0;

        if (typeSelect?.value === 'percent') {
          discountAmount = subtotal * (Math.min(100, discountValue) / 100);
        } else {
          discountAmount = discountValue;
        }

        var total = Math.max(0, subtotal - discountAmount);
        var amountPaid = parseFloat(document.querySelector('.js-gb-advance-amount')?.value || '0') || 0;
        var remaining = Math.max(0, total - amountPaid);
        var subtotalEl = document.querySelector('.js-gb-subtotal');
        var discountEl = document.querySelector('.js-gb-discount-amount');
        var totalEl = document.querySelector('.js-gb-total');
        var remainingWrap = document.querySelector('.js-gb-remaining-wrap');
        var remainingEl = document.querySelector('.js-gb-remaining');

        if (subtotalEl) subtotalEl.textContent = formatMoney(subtotal);
        if (discountEl) discountEl.textContent = formatMoney(discountAmount);
        if (totalEl) totalEl.textContent = formatMoney(total);

        if (remainingWrap && remainingEl) {
          var showRemaining = amountPaid > 0 && remaining > 0.009;
          remainingWrap.classList.toggle('is-hidden', !showRemaining);
          remainingWrap.classList.toggle('is-due', showRemaining);
          remainingEl.textContent = formatMoney(remaining);
        }
      }

      function updateDiscountField() {
        var typeSelect = document.querySelector('.js-gb-discount-type');
        var discountInput = document.querySelector('.js-gb-discount');
        var suffix = document.querySelector('.js-gb-discount-suffix');
        if (!typeSelect || !discountInput || !suffix) return;

        var isPercent = typeSelect.value === 'percent';
        suffix.textContent = isPercent ? '%' : discountCurrency;
        if (isPercent) {
          discountInput.max = '100';
        } else {
          discountInput.removeAttribute('max');
        }
        updateTotals();
      }

      document.querySelector('.js-gb-discount-type')?.addEventListener('change', updateDiscountField);
      document.querySelector('.js-gb-discount')?.addEventListener('input', updateTotals);
      document.querySelector('.js-gb-discount')?.addEventListener('change', updateTotals);
      document.querySelector('.js-gb-advance-amount')?.addEventListener('input', updateTotals);
      document.querySelector('.js-gb-advance-amount')?.addEventListener('change', updateTotals);
      updateDiscountField();

      document.querySelector('.js-gb-discount-undo')?.addEventListener('click', function () {
        var discountInput = document.querySelector('.js-gb-discount');
        if (discountInput) discountInput.value = '0';
        updateTotals();
      });

      function bindEmailAutocomplete() {
        var emailInput = document.querySelector('.js-gb-guest-email');
        var suggestBox = document.querySelector('.js-gb-email-suggest');
        if (!emailInput || !suggestBox) return;

        function hideSuggestions() {
          suggestBox.classList.remove('is-open');
          suggestBox.innerHTML = '';
        }

        function renderSuggestions() {
          var value = emailInput.value.trim();
          if (!value || value.indexOf(' ') !== -1) {
            hideSuggestions();
            return;
          }

          var atIndex = value.indexOf('@');
          var localPart = atIndex === -1 ? value : value.slice(0, atIndex);
          var domainPart = atIndex === -1 ? '' : value.slice(atIndex + 1).toLowerCase();

          if (!localPart) {
            hideSuggestions();
            return;
          }

          var matches = emailDomains.filter(function (domain) {
            return !domainPart || domain.indexOf(domainPart) === 0;
          }).slice(0, 8).map(function (domain) {
            return localPart + '@' + domain;
          });

          if (!matches.length) {
            hideSuggestions();
            return;
          }

          suggestBox.innerHTML = matches.map(function (full) {
            return '<button type="button" data-email="' + full.replace(/"/g, '&quot;') + '">' + full + '</button>';
          }).join('');
          suggestBox.classList.add('is-open');

          suggestBox.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('mousedown', function (event) {
              event.preventDefault();
              emailInput.value = btn.getAttribute('data-email') || '';
              hideSuggestions();
            });
          });
        }

        emailInput.addEventListener('input', renderSuggestions);
        emailInput.addEventListener('focus', renderSuggestions);
        emailInput.addEventListener('blur', function () {
          window.setTimeout(hideSuggestions, 150);
        });
      }

      window.fillGbGuest = function (guest) {
        var nameInput = document.querySelector('.js-gb-guest-name');
        var emailInput = document.querySelector('.js-gb-guest-email');
        var phoneInput = document.querySelector('.js-gb-guest-phone');
        var phoneCodeSelect = document.getElementById('gb_guest_phone_code');
        var phoneCodeHidden = document.getElementById('gb_guest_phone_country_code');

        if (nameInput) nameInput.value = guest.name || '';
        if (emailInput) emailInput.value = guest.email || '';

        if (phoneInput) {
          var phone = String(guest.phone || '').trim();
          var matchedCode = '';

          if (phone.indexOf('+') === 0 && phoneCodeSelect) {
            Array.prototype.slice.call(phoneCodeSelect.options).forEach(function (option) {
              if (!option.value || matchedCode) return;
              if (phone.indexOf(option.value) === 0 && option.value.length > matchedCode.length) {
                matchedCode = option.value;
              }
            });
          }

          if (matchedCode) {
            if (phoneCodeSelect && typeof jQuery !== 'undefined') {
              jQuery(phoneCodeSelect).val(matchedCode).trigger('change.select2');
            }
            if (phoneCodeHidden) phoneCodeHidden.value = matchedCode;
            phoneInput.value = phone.slice(matchedCode.length).replace(/^0+/, '');
          } else {
            phoneInput.value = phone;
          }
        }
      };

      var form = document.getElementById('gbForm');
      if (form) {
        form.addEventListener('submit', function () {
          var btn = form.querySelector('.js-gb-submit');
          if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';
          }
        });
      }

      function bootLocationSelects(Country, City) {
        jQuery(function ($) {
          var countries = Country.getAllCountries().sort(function (a, b) {
            return a.name.localeCompare(b.name);
          });
          var $country = $('#gb_guest_country_code');
          var $city = $('#gb_guest_city');
          var $countryName = $('#gb_guest_country_name');
          var $phoneCodeSelect = $('#gb_guest_phone_code');
          var $phoneCountryCode = $('#gb_guest_phone_country_code');

          countries.forEach(function (country) {
            var phone = '+' + country.phonecode;
            var selected = oldCountryCode === country.isoCode ? ' selected' : '';
            $country.append(
              '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-flag="' + country.flag + '" data-phone="' + phone + '"' + selected + '>' + country.name + '</option>'
            );
            var phoneSelected = oldPhoneCode === phone ? ' selected' : '';
            $phoneCodeSelect.append(
              '<option value="' + phone + '" data-flag="' + country.flag + '" data-iso="' + country.isoCode + '"' + phoneSelected + '>' + country.flag + ' ' + phone + '</option>'
            );
          });

          function formatCountry(option) {
            if (!option.id) return option.text;
            var flag = $(option.element).data('flag') || '';
            return $('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
          }

          function formatPhoneCode(option) {
            if (!option.id) return option.text;
            var flag = $(option.element).data('flag') || '';
            return $('<span><span class="select2-flag">' + flag + '</span> ' + option.id + '</span>');
          }

          function loadCities(countryCode, selectedCity) {
            $city.empty().append('<option value="">Select city</option>');
            if (!countryCode) return;

            var cities = City.getCitiesOfCountry(countryCode) || [];
            var seen = {};
            cities.forEach(function (city) {
              if (seen[city.name]) return;
              seen[city.name] = true;
              var selected = selectedCity === city.name ? ' selected' : '';
              $city.append('<option value="' + city.name + '"' + selected + '>' + city.name + '</option>');
            });
          }

          function updateCountryName() {
            $countryName.val($('option:selected', $country).data('name') || '');
          }

          function syncPhoneCodeFromCountry() {
            var phone = $('option:selected', $country).data('phone');
            if (!phone) return;
            $phoneCodeSelect.val(phone).trigger('change.select2');
            $phoneCountryCode.val(phone);
          }

          $country.select2({
            width: '100%',
            placeholder: 'Select country',
            templateResult: formatCountry,
            templateSelection: formatCountry,
          });
          $city.select2({ width: '100%', placeholder: 'Select city' });
          $phoneCodeSelect.select2({
            width: '118px',
            minimumResultsForSearch: 6,
            templateResult: formatPhoneCode,
            templateSelection: formatPhoneCode,
          });

          $country.on('change', function () {
            updateCountryName();
            syncPhoneCodeFromCountry();
            loadCities($(this).val(), null);
            $city.val('').trigger('change.select2');
          });

          $phoneCodeSelect.on('change', function () {
            $phoneCountryCode.val($(this).val() || '');
            var iso = $('option:selected', this).data('iso');
            if (iso && $country.val() !== iso) {
              $country.val(iso).trigger('change.select2');
              updateCountryName();
              loadCities(iso, null);
            }
          });

          if (oldCountryCode) {
            updateCountryName();
            loadCities(oldCountryCode, oldCity);
          }

          if (oldPhoneCode) {
            $phoneCountryCode.val(oldPhoneCode);
          }
        });
      }

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          bootLocationSelects(module.Country, module.City);
        })
        .catch(function () {});

      bindPaymentProviderFields();
      bindEmailAutocomplete();
      updateSendInvoiceOption();
      refreshAllAvailability();
      updateTotals();
    })();
  </script>
@endpush
