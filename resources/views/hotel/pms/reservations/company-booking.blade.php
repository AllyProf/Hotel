@extends('layouts.app')

@section('title', 'Company Booking')

@push('styles')
  <style>
    .cb-page-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
    }

    .cb-page-head h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 500;
      color: #333;
    }

    .cb-section-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--brand, #940000);
      border-bottom: 1px solid #ddd;
      padding-bottom: 6px;
      margin: 0 0 14px;
    }

    .cb-room-table {
      background: #f5f5f5;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      padding: 12px;
      margin-bottom: 16px;
    }

    .cb-room-table__head,
    .cb-room-row {
      display: grid;
      grid-template-columns: 1.4fr 0.7fr 0.9fr 0.9fr 40px;
      gap: 10px;
      align-items: end;
    }

    .cb-room-table__head {
      font-size: 12px;
      font-weight: 700;
      color: #555;
      margin-bottom: 8px;
      padding: 0 4px;
    }

    .cb-room-row-wrap { margin-bottom: 8px; }

    .cb-add-row {
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

    .cb-add-row:hover { background: var(--brand-dark, #7a0000); }

    .cb-remove-row {
      width: 36px;
      height: 36px;
      border: none;
      background: transparent;
      color: #999;
      font-size: 18px;
      cursor: pointer;
    }

    .cb-remove-row:hover { color: #c0392b; }

    .cb-availability-msg { min-height: 18px; padding-top: 4px; font-size: 12px; }
    .cb-availability-msg.is-ok { color: #28a745; }
    .cb-availability-msg.is-bad { color: #dc3545; }

    .cb-total-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 16px;
      justify-content: flex-end;
      font-size: 13px;
      color: #444;
      margin-top: 8px;
    }

    .cb-total-summary strong { color: var(--brand, #940000); }
    .cb-total-summary .js-cb-remaining-wrap.is-hidden { display: none; }
    .cb-total-summary .js-cb-remaining-wrap.is-due strong { color: #c0392b; }

    .cb-discount-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .cb-discount-wrap .input-group { max-width: 150px; }
    .cb-discount-wrap .js-cb-discount-type { max-width: 120px; flex: 0 0 auto; }

    .cb-phone-group { display: flex; align-items: stretch; }
    .cb-phone-group .select2-container { width: 108px !important; flex: 0 0 108px; }
    .cb-phone-group .select2-container .select2-selection--single {
      height: 38px; border-radius: 4px 0 0 4px; border-right: 0; display: flex; align-items: center;
    }
    .cb-phone-group .js-cb-guest-phone { border-radius: 0 4px 4px 0; flex: 1 1 auto; }

    .cb-email-wrap { position: relative; }
    .cb-email-suggest {
      position: absolute; top: 100%; left: 0; right: 0; z-index: 30; background: #fff;
      border: 1px solid #ddd; border-top: 0; border-radius: 0 0 4px 4px; max-height: 160px;
      overflow-y: auto; display: none; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }
    .cb-email-suggest.is-open { display: block; }
    .cb-email-suggest button {
      display: block; width: 100%; border: 0; background: #fff; text-align: left;
      padding: 7px 10px; font-size: 12px; cursor: pointer;
    }
    .cb-email-suggest button:hover { background: #f5f5f5; }

    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 6px; }
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
    ]]);
    $discountType = old('discount_type', 'percent');
    $discountValue = old('discount_value', old('discount_percent', 0));
  @endphp

  <div class="cb-page-head">
    <h3><i class="fa fa-building"></i> Company Booking</h3>
    <a href="{{ route('hotel.reservations.index') }}" class="btn btn-primary btn-sm">Back</a>
  </div>

  <div class="tile">
    <div class="tile-body">
      <form action="{{ route('hotel.reservations.company-booking.store') }}" method="POST" id="cbForm">
        @csrf

        <div class="cb-section-title">Company</div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Bill To Company <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control js-res-bill-to js-cb-bill-to @error('bill_to') is-invalid @enderror"
                  name="bill_to" value="{{ old('bill_to') }}"
                  placeholder="Type company name or import registered company" required>
                <input type="hidden" name="bill_to_company_id" class="js-res-bill-to-company-id"
                  value="{{ old('bill_to_company_id') }}">
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-res-bill-to-search" title="Import registered company">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
              @error('bill_to')<div class="text-danger small">{{ $message }}</div>@enderror
              @error('bill_to_company_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Contact Person (Company Leader)</label>
              <input type="text" class="form-control js-cb-company-contact-person" name="company_contact_person"
                value="{{ old('company_contact_person') }}" placeholder="Company leader / contact person">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Contact Email</label>
              <div class="cb-email-wrap">
                <input type="email" class="form-control js-cb-company-contact-email" name="company_contact_email"
                  value="{{ old('company_contact_email') }}" placeholder="Enter email" autocomplete="off">
                <div class="cb-email-suggest js-cb-company-contact-email-suggest"></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Contact Phone</label>
              <div class="cb-phone-group">
                <select class="js-cb-company-phone-code" id="cb_company_phone_code" aria-label="Company phone country code"></select>
                <input type="hidden" class="js-cb-company-phone-country-code" name="company_contact_phone_country_code"
                  value="{{ old('company_contact_phone_country_code', $options['default_phone_code'] ?? '+255') }}">
                <input type="text" class="form-control js-cb-company-contact-phone" name="company_contact_phone"
                  value="{{ old('company_contact_phone') }}" placeholder="Phone number">
              </div>
            </div>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-12">
            <label class="animated-checkbox mb-0 js-cb-send-invoice-wrap">
              <input type="checkbox" name="send_invoice_to_company" value="1" class="js-cb-send-invoice"
                {{ old('send_invoice_to_company') ? 'checked' : '' }}>
              <span class="label-text">Send invoice by email</span>
            </label>
            <small class="text-muted d-none js-cb-send-invoice-hint mt-1"></small>
            @error('send_invoice_to_company')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="cb-section-title">Stay details</div>
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Check-in <span class="text-danger">*</span></label>
              <input type="date" class="form-control js-cb-checkin" name="checkin" value="{{ $checkin }}" required>
              @error('checkin')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Check-out <span class="text-danger">*</span></label>
              <input type="date" class="form-control js-cb-checkout" name="checkout" value="{{ $checkout }}" required>
            </div>
          </div>
          <div class="col-md-3">
            <label class="animated-checkbox d-block mt-4">
              <input type="checkbox" name="tax_inclusive" value="1" class="js-cb-tax-inclusive"
                {{ old('tax_inclusive', true) ? 'checked' : '' }}>
              <span class="label-text">Tax Inclusive (VAT {{ number_format($options['vat_rate'] ?? 18, 2) }}%)</span>
            </label>
          </div>
        </div>

        <div class="cb-room-table">
          <div class="cb-room-table__head">
            <div>Room Type</div>
            <div># Rooms</div>
            <div># Guests / room</div>
            <div>Rate Plan</div>
            <div></div>
          </div>
          <div id="cbRoomRows">
            @foreach($oldLines as $index => $line)
              @include('hotel.pms.reservations.partials._company-room-row', [
                'index' => $index,
                'line' => $line,
                'options' => $options,
                'showRemove' => $index > 0,
              ])
            @endforeach
          </div>
          <div class="text-right mt-2">
            <button type="button" class="cb-add-row js-cb-add-row" title="Add room type">+</button>
          </div>
        </div>
        @error('lines')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div class="cb-section-title">Guest Leader Details</div>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Leader Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control js-cb-guest-name" name="guest_name"
                  value="{{ old('guest_name') }}" placeholder="Guest group leader" required>
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-cb-guest-search" title="Search guest">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
              @error('guest_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Leader Email</label>
              <div class="cb-email-wrap">
                <input type="email" class="form-control js-cb-guest-email" name="guest_email"
                  value="{{ old('guest_email') }}" placeholder="Enter email" autocomplete="off">
                <div class="cb-email-suggest js-cb-guest-email-suggest"></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Leader Phone</label>
              <div class="cb-phone-group">
                <select class="js-cb-phone-code" id="cb_phone_code"></select>
                <input type="hidden" class="js-cb-phone-country-code" name="guest_phone_country_code"
                  value="{{ old('guest_phone_country_code', $options['default_phone_code'] ?? '+255') }}">
                <input type="text" class="form-control js-cb-guest-phone" name="guest_phone"
                  value="{{ old('guest_phone') }}" placeholder="Phone number">
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Country</label>
              <select class="form-control js-cb-country" id="cb_country" name="guest_country_code">
                <option value="">Select country</option>
              </select>
              <input type="hidden" class="js-cb-country-name" name="guest_country"
                value="{{ old('guest_country', $options['default_country'] ?? '') }}">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">City</label>
              <select class="form-control js-cb-city" id="cb_city" name="guest_city">
                <option value="">Select city</option>
                @if(old('guest_city'))
                  <option value="{{ old('guest_city') }}" selected>{{ old('guest_city') }}</option>
                @endif
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Notes</label>
              <input type="text" class="form-control" name="special_request" value="{{ old('special_request') }}"
                placeholder="Optional booking notes">
            </div>
          </div>
        </div>

        <div class="cb-section-title">Payment</div>
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Payment Mode</label>
              <input type="text" class="form-control" value="Bill to Company" readonly>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Amount Paid</label>
              <input type="number" step="0.01" min="0" class="form-control js-cb-advance-amount" name="advance_amount"
                value="{{ old('advance_amount', 0) }}">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Discount</label>
              <div class="cb-discount-wrap">
                <select class="form-control js-cb-discount-type" name="discount_type">
                  <option value="percent" {{ $discountType === 'percent' ? 'selected' : '' }}>By %</option>
                  <option value="amount" {{ $discountType === 'amount' ? 'selected' : '' }}>By amount</option>
                </select>
                <div class="input-group">
                  <input type="number" step="0.01" min="0" @if($discountType === 'percent') max="100" @endif
                    class="form-control js-cb-discount" name="discount_value" value="{{ $discountValue }}">
                  <div class="input-group-append">
                    <span class="input-group-text js-cb-discount-suffix">{{ $discountType === 'amount' ? ($options['currency'] ?? 'USD') : '%' }}</span>
                  </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm js-cb-discount-undo">Undo</button>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">Payment notes</label>
              <input type="text" class="form-control" name="advance_comments" value="{{ old('advance_comments') }}">
            </div>
          </div>
        </div>

        <div class="cb-total-summary">
          <span>Subtotal: <strong class="js-cb-subtotal">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
          <span>Discount: <strong class="js-cb-discount-amount">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
          <span>Total: <strong class="js-cb-total">0.00</strong> {{ $options['currency'] ?? 'USD' }}</span>
          <span class="js-cb-remaining-wrap is-hidden">
            Remaining: <strong class="js-cb-remaining">0.00</strong> {{ $options['currency'] ?? 'USD' }}
          </span>
        </div>

        <div class="text-right mt-3 pt-3 border-top">
          <button type="submit" class="btn btn-primary btn-lg js-cb-submit" style="background:#940000;border-color:#940000;">
            <i class="fa fa-building"></i> Create Company Booking
          </button>
        </div>
      </form>
    </div>
  </div>

  @include('hotel.pms.reservations.partials._guest-search-modal')
  @include('hotel.pms.reservations.partials._company-modals')

  <template id="cbRoomRowTemplate">
    @include('hotel.pms.reservations.partials._company-room-row', [
      'index' => '__INDEX__',
      'line' => [
        'hotel_room_id' => $options['default_room_id'] ?? '',
        'hotel_rate_plan_id' => $options['default_rate_plan_id'] ?? '',
        'room_count' => 1,
        'guest_count' => 1,
      ],
      'options' => $options,
      'showRemove' => true,
    ])
  </template>

  <script type="application/json" id="cb-room-data">@json($options['rooms'])</script>
  <script type="application/json" id="cb-default-country">@json($options['default_country_code'] ?? '')</script>
  <script type="application/json" id="cb-default-phone-code">@json($options['default_phone_code'] ?? '+255')</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var roomData = [];
      var defaultCountryCode = '';
      var defaultPhoneCode = '+255';
      var discountCurrency = @json($options['currency'] ?? 'USD');
      var availabilityUrl = @json(route('hotel.reservations.company-booking.check-availability'));
      var csrf = @json(csrf_token());
      var rowsWrap = document.getElementById('cbRoomRows');
      var rowIndex = rowsWrap ? rowsWrap.querySelectorAll('.cb-room-row-wrap').length : 0;
      var availabilityTimers = new WeakMap();
      var CountryModule = null;
      var CityModule = null;
      var companyRates = {};
      var importedCompanyName = '';
      var emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'live.com', 'aol.com', 'protonmail.com', 'mail.com', 'zoho.com'
      ];

      try {
        roomData = JSON.parse(document.getElementById('cb-room-data').textContent || '[]');
        defaultCountryCode = JSON.parse(document.getElementById('cb-default-country').textContent || '""');
        defaultPhoneCode = JSON.parse(document.getElementById('cb-default-phone-code').textContent || '"+255"');
      } catch (e) {}

      function formatMoney(value) {
        return (Math.round(value * 100) / 100).toFixed(2);
      }

      var checkin = document.querySelector('.js-cb-checkin');
      var checkout = document.querySelector('.js-cb-checkout');

      function blockNights() {
        if (!checkin || !checkout || !checkin.value || !checkout.value) return 1;
        var start = new Date(checkin.value + 'T00:00:00');
        var end = new Date(checkout.value + 'T00:00:00');
        return Math.max(1, Math.round((end - start) / 86400000) || 1);
      }

      function planRate(planSelect) {
        if (!planSelect) return 0;
        var planId = planSelect.value;
        if (planId && companyRates[planId] && parseFloat(companyRates[planId]) > 0) {
          return parseFloat(companyRates[planId]);
        }
        var option = planSelect.options[planSelect.selectedIndex];
        return parseFloat(option?.getAttribute('data-rate') || '0') || 0;
      }

      function calcSubtotal() {
        var nights = blockNights();
        var subtotal = 0;
        if (!rowsWrap) return 0;

        rowsWrap.querySelectorAll('.cb-room-row-wrap').forEach(function (wrap) {
          var planSelect = wrap.querySelector('.js-cb-rate-plan');
          var count = parseInt(wrap.querySelector('.js-cb-room-count')?.value || '0', 10) || 0;
          subtotal += planRate(planSelect) * nights * count;
        });

        return subtotal;
      }

      function updateTotals() {
        var subtotal = calcSubtotal();
        var typeSelect = document.querySelector('.js-cb-discount-type');
        var discountInput = document.querySelector('.js-cb-discount');
        var discountValue = parseFloat(discountInput?.value || '0') || 0;
        var discountAmount = typeSelect?.value === 'percent'
          ? subtotal * (Math.min(100, discountValue) / 100)
          : Math.min(discountValue, subtotal);
        var total = Math.max(0, subtotal - discountAmount);
        var amountPaid = parseFloat(document.querySelector('.js-cb-advance-amount')?.value || '0') || 0;
        var remaining = Math.max(0, total - amountPaid);

        document.querySelector('.js-cb-subtotal').textContent = formatMoney(subtotal);
        document.querySelector('.js-cb-discount-amount').textContent = formatMoney(discountAmount);
        document.querySelector('.js-cb-total').textContent = formatMoney(total);

        var remainingWrap = document.querySelector('.js-cb-remaining-wrap');
        var remainingEl = document.querySelector('.js-cb-remaining');
        if (remainingWrap && remainingEl) {
          var showRemaining = amountPaid > 0 && remaining > 0.009;
          remainingWrap.classList.toggle('is-hidden', !showRemaining);
          remainingWrap.classList.toggle('is-due', showRemaining);
          remainingEl.textContent = formatMoney(remaining);
        }
      }

      function updateDiscountField() {
        var typeSelect = document.querySelector('.js-cb-discount-type');
        var discountInput = document.querySelector('.js-cb-discount');
        var suffix = document.querySelector('.js-cb-discount-suffix');
        if (!typeSelect || !discountInput || !suffix) return;
        var isPercent = typeSelect.value === 'percent';
        suffix.textContent = isPercent ? '%' : discountCurrency;
        if (isPercent) discountInput.max = '100'; else discountInput.removeAttribute('max');
        updateTotals();
      }

      function planOptionsForRoom(roomId, selectedId) {
        var room = roomData.find(function (item) { return String(item.id) === String(roomId); });
        if (!room) return '<option value="">Select rate plan</option>';
        return (room.rate_plans || []).map(function (plan) {
          var selected = String(plan.id) === String(selectedId) ? ' selected' : '';
          return '<option value="' + plan.id + '" data-rate="' + plan.base_rate + '"' + selected + '>' + plan.label + '</option>';
        }).join('');
      }

      function otherRoomCounts(currentWrap) {
        var others = [];
        if (!rowsWrap) return others;

        rowsWrap.querySelectorAll('.cb-room-row-wrap').forEach(function (wrap) {
          if (wrap === currentWrap) return;
          var roomSelect = wrap.querySelector('.js-cb-room');
          var countInput = wrap.querySelector('.js-cb-room-count');
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

        var msg = wrap.querySelector('.js-cb-availability-msg');
        var roomSelect = wrap.querySelector('.js-cb-room');
        var countInput = wrap.querySelector('.js-cb-room-count');

        if (!msg || !roomSelect || !countInput || !checkin || !checkout) return;

        var roomCount = parseInt(countInput.value || '0', 10);
        if (!checkin.value || !checkout.value || !roomSelect.value || roomCount < 1) {
          msg.textContent = '';
          msg.className = 'cb-availability-msg js-cb-availability-msg small';
          return;
        }

        msg.textContent = 'Checking availability...';
        msg.className = 'cb-availability-msg js-cb-availability-msg small';

        fetch(availabilityUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({
            checkin: checkin.value,
            checkout: checkout.value,
            hotel_room_id: parseInt(roomSelect.value, 10),
            room_count: roomCount,
            other_room_counts: otherRoomCounts(wrap)
          })
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            msg.textContent = data.message || '';
            msg.className = 'cb-availability-msg js-cb-availability-msg small ' + (data.ok ? 'is-ok' : 'is-bad');
          })
          .catch(function () {
            msg.textContent = 'Could not check availability.';
            msg.className = 'cb-availability-msg js-cb-availability-msg small is-bad';
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
        rowsWrap.querySelectorAll('.cb-room-row-wrap').forEach(scheduleAvailability);
      }

      function bindRow(wrap) {
        if (!wrap) return;

        var roomSelect = wrap.querySelector('.js-cb-room');
        var planSelect = wrap.querySelector('.js-cb-rate-plan');
        var countInput = wrap.querySelector('.js-cb-room-count');
        var removeBtn = wrap.querySelector('.js-cb-remove-row');

        function syncPlans() {
          if (!roomSelect || !planSelect) return;
          var selected = planSelect.value;
          planSelect.innerHTML = planOptionsForRoom(roomSelect.value, selected);
          refreshAllAvailability();
          updateTotals();
        }

        if (roomSelect) {
          roomSelect.addEventListener('change', syncPlans);
        }
        if (planSelect) {
          planSelect.addEventListener('change', function () {
            updateTotals();
          });
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
        rowsWrap.querySelectorAll('.cb-room-row-wrap').forEach(function (wrap, index) {
          wrap.querySelectorAll('[name]').forEach(function (input) {
            input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
          });
        });
        rowIndex = rowsWrap.querySelectorAll('.cb-room-row-wrap').length;
      }

      if (rowsWrap) {
        rowsWrap.querySelectorAll('.cb-room-row-wrap').forEach(bindRow);
      }

      document.querySelector('.js-cb-add-row')?.addEventListener('click', function () {
        var template = document.getElementById('cbRoomRowTemplate');
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

      function resolveInvoiceEmail() {
        var contactEmail = document.querySelector('.js-cb-company-contact-email');
        return contactEmail ? (contactEmail.value || '').trim() : '';
      }

      function updateSendInvoiceState() {
        var hint = document.querySelector('.js-cb-send-invoice-hint');
        var checkbox = document.querySelector('.js-cb-send-invoice');
        if (!checkbox) return;

        var email = resolveInvoiceEmail();
        if (!email) {
          checkbox.disabled = true;
          if (checkbox.checked) checkbox.checked = false;
        } else {
          checkbox.disabled = false;
        }
        if (hint) {
          hint.classList.add('d-none');
        }
      }

      function bindEmailAutocomplete(emailInput, suggestBox) {
        if (!emailInput || !suggestBox || emailInput.dataset.bound === '1') return;
        emailInput.dataset.bound = '1';

        function hideSuggestions() {
          suggestBox.classList.remove('is-open');
          suggestBox.innerHTML = '';
        }

        emailInput.addEventListener('input', function () {
          var value = emailInput.value.trim();
          if (!value || value.indexOf(' ') !== -1 || value.indexOf('@') !== -1) {
            hideSuggestions();
            if (emailInput.classList.contains('js-cb-company-contact-email')) {
              updateSendInvoiceState();
            }
            return;
          }

          suggestBox.innerHTML = emailDomains.map(function (domain) {
            var full = value + '@' + domain;
            return '<button type="button" data-email="' + full + '">' + full + '</button>';
          }).join('');
          suggestBox.classList.add('is-open');
        });

        suggestBox.addEventListener('click', function (event) {
          var button = event.target.closest('button[data-email]');
          if (!button) return;
          emailInput.value = button.getAttribute('data-email') || '';
          hideSuggestions();
          if (emailInput.classList.contains('js-cb-company-contact-email')) {
            updateSendInvoiceState();
          }
        });

        document.addEventListener('click', function (event) {
          if (!emailInput.contains(event.target) && !suggestBox.contains(event.target)) {
            hideSuggestions();
          }
        });

        if (emailInput.classList.contains('js-cb-company-contact-email')) {
          emailInput.addEventListener('change', updateSendInvoiceState);
        }
      }

      function fillCompanyPhone(phone) {
        var phoneInput = document.querySelector('.js-cb-company-contact-phone');
        var phoneCodeSelect = document.querySelector('.js-cb-company-phone-code');
        var phoneCodeHidden = document.querySelector('.js-cb-company-phone-country-code');
        if (!phoneInput) return;

        phone = String(phone || '').trim();
        if (!phone) {
          phoneInput.value = '';
          return;
        }

        var matchedCode = '';
        if (phone.indexOf('+') === 0 && phoneCodeSelect) {
          Array.prototype.slice.call(phoneCodeSelect.options).forEach(function (option) {
            if (!option.value || matchedCode) return;
            if (phone.indexOf(option.value) === 0 && option.value.length > matchedCode.length) {
              matchedCode = option.value;
            }
          });
        }

        if (matchedCode && typeof jQuery !== 'undefined') {
          jQuery(phoneCodeSelect).val(matchedCode).trigger('change.select2');
          if (phoneCodeHidden) phoneCodeHidden.value = matchedCode;
          phoneInput.value = phone.slice(matchedCode.length).replace(/^0+/, '');
        } else {
          phoneInput.value = phone;
        }
      }

      window.onGbCompanySelected = function (company) {
        companyRates = company && company.contracted_rates_map ? company.contracted_rates_map : {};
        importedCompanyName = company && company.name ? company.name : '';

        var contactPerson = document.querySelector('.js-cb-company-contact-person');
        var contactEmail = document.querySelector('.js-cb-company-contact-email');
        if (contactPerson) {
          contactPerson.value = company && company.contact_person ? company.contact_person : '';
        }
        if (contactEmail) {
          contactEmail.value = company && company.email && company.email !== '—' ? company.email : '';
        }
        fillCompanyPhone(company ? company.phone : '');

        updateSendInvoiceState();
        updateTotals();
      };

      window.fillGbGuest = function (guest) {
        var nameInput = document.querySelector('.js-cb-guest-name');
        var emailInput = document.querySelector('.js-cb-guest-email');
        var phoneInput = document.querySelector('.js-cb-guest-phone');
        if (nameInput) nameInput.value = guest.name || '';
        if (emailInput) emailInput.value = guest.email || '';
        if (phoneInput) phoneInput.value = guest.phone || '';
      };

      document.querySelector('.js-cb-tax-inclusive')?.addEventListener('change', updateTotals);
      document.querySelector('.js-cb-discount-type')?.addEventListener('change', updateDiscountField);
      document.querySelector('.js-cb-discount')?.addEventListener('input', updateTotals);
      document.querySelector('.js-cb-advance-amount')?.addEventListener('input', updateTotals);
      document.querySelector('.js-cb-discount-undo')?.addEventListener('click', function () {
        var discountInput = document.querySelector('.js-cb-discount');
        if (discountInput) discountInput.value = '0';
        updateTotals();
      });
      document.querySelector('.js-cb-bill-to')?.addEventListener('input', function () {
        var billTo = document.querySelector('.js-cb-bill-to');
        var companyId = document.querySelector('.js-res-bill-to-company-id');
        if (!billTo || !companyId) return;

        if (billTo.value.trim() !== importedCompanyName) {
          companyId.value = '';
          companyRates = {};
          importedCompanyName = '';
          updateTotals();
        }
      });

      document.querySelector('.js-cb-guest-search')?.addEventListener('click', function () {
        if (typeof jQuery !== 'undefined') jQuery('#gbSelectGuestModal').modal('show');
      });

      document.getElementById('cbForm')?.addEventListener('submit', function (event) {
        if (!document.querySelector('.js-cb-bill-to')?.value.trim()) {
          event.preventDefault();
          alert('Please enter or import a company name.');
          return;
        }
        var btn = document.querySelector('.js-cb-submit');
        if (btn && !btn.disabled) {
          btn.disabled = true;
          btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';
        }
      });

      function initPhoneSelect(phoneSelect, phoneHidden, selectedCode) {
        if (!phoneSelect || typeof jQuery === 'undefined' || !CountryModule) return;

        var $phone = jQuery(phoneSelect);
        if ($phone.data('select2')) $phone.select2('destroy');
        $phone.empty();
        CountryModule.getAllCountries().sort(function (a, b) { return a.name.localeCompare(b.name); }).forEach(function (country) {
          var phone = '+' + country.phonecode;
          var selected = (selectedCode || defaultPhoneCode) === phone ? ' selected' : '';
          $phone.append('<option value="' + phone + '" data-flag="' + country.flag + '"' + selected + '>' + phone + '</option>');
        });
        $phone.select2({
          width: '108px',
          templateResult: function (option) {
            if (!option.id) return option.text;
            return jQuery('<span><span class="select2-flag">' + (jQuery(option.element).data('flag') || '') + '</span> ' + option.text + '</span>');
          },
          templateSelection: function (option) {
            if (!option.id) return option.text;
            return jQuery('<span><span class="select2-flag">' + (jQuery(option.element).data('flag') || '') + '</span> ' + option.text + '</span>');
          }
        });
        $phone.off('change.cb').on('change.cb', function () {
          if (phoneHidden) phoneHidden.value = $phone.val() || '';
        });
        var initial = selectedCode || defaultPhoneCode;
        if (initial) {
          $phone.val(initial).trigger('change.select2');
          if (phoneHidden) phoneHidden.value = initial;
        }
      }

      function initCountryCity(selectedCountryCode, selectedCity) {
        var countrySelect = document.querySelector('.js-cb-country');
        var citySelect = document.querySelector('.js-cb-city');
        var countryNameInput = document.querySelector('.js-cb-country-name');
        if (!countrySelect || !citySelect || typeof jQuery === 'undefined' || !CountryModule || !CityModule) return;

        var $country = jQuery(countrySelect);
        var $city = jQuery(citySelect);
        if ($country.data('select2')) $country.select2('destroy');
        if ($city.data('select2')) $city.select2('destroy');

        $country.empty().append('<option value="">Select country</option>');
        CountryModule.getAllCountries().sort(function (a, b) { return a.name.localeCompare(b.name); }).forEach(function (country) {
          var selected = (selectedCountryCode || defaultCountryCode) === country.isoCode ? ' selected' : '';
          $country.append('<option value="' + country.isoCode + '" data-name="' + country.name + '" data-flag="' + country.flag + '"' + selected + '>' + country.name + '</option>');
        });

        function formatCountry(option) {
          if (!option.id) return option.text;
          return jQuery('<span><span class="select2-flag">' + (jQuery(option.element).data('flag') || '') + '</span> ' + option.text + '</span>');
        }

        function loadCities(countryCode, cityName) {
          $city.empty().append('<option value="">Select city</option>');
          if (!countryCode) return;
          var seen = {};
          (CityModule.getCitiesOfCountry(countryCode) || []).forEach(function (city) {
            if (seen[city.name]) return;
            seen[city.name] = true;
            var selected = cityName === city.name ? ' selected' : '';
            $city.append('<option value="' + city.name + '"' + selected + '>' + city.name + '</option>');
          });
        }

        $country.select2({ width: '100%', placeholder: 'Select country', templateResult: formatCountry, templateSelection: formatCountry });
        $city.select2({ width: '100%', placeholder: 'Select city' });
        $country.off('change.cb').on('change.cb', function () {
          if (countryNameInput) countryNameInput.value = jQuery('option:selected', $country).data('name') || '';
          loadCities($country.val(), null);
          $city.val('').trigger('change.select2');
        });

        var initialCountry = selectedCountryCode || defaultCountryCode || $country.val();
        if (initialCountry) {
          $country.val(initialCountry).trigger('change.select2');
          if (countryNameInput && !countryNameInput.value) {
            countryNameInput.value = jQuery('option:selected', $country).data('name') || '';
          }
          loadCities(initialCountry, selectedCity || '');
        }
      }

      updateDiscountField();
      refreshAllAvailability();
      updateTotals();
      bindEmailAutocomplete(
        document.querySelector('.js-cb-guest-email'),
        document.querySelector('.js-cb-guest-email-suggest')
      );
      bindEmailAutocomplete(
        document.querySelector('.js-cb-company-contact-email'),
        document.querySelector('.js-cb-company-contact-email-suggest')
      );
      updateSendInvoiceState();

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          CountryModule = module.Country;
          CityModule = module.City;
          initCountryCity(
            document.querySelector('.js-cb-country')?.value || defaultCountryCode,
            document.querySelector('.js-cb-city')?.value || ''
          );
          initPhoneSelect(
            document.querySelector('.js-cb-phone-code'),
            document.querySelector('.js-cb-phone-country-code'),
            document.querySelector('.js-cb-phone-country-code')?.value || defaultPhoneCode
          );
          initPhoneSelect(
            document.querySelector('.js-cb-company-phone-code'),
            document.querySelector('.js-cb-company-phone-country-code'),
            document.querySelector('.js-cb-company-phone-country-code')?.value || defaultPhoneCode
          );
        })
        .catch(function () {});
    })();
  </script>
@endpush
