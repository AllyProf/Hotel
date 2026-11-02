@extends('layouts.app')

@section('title', 'New conference booking')

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sw-panel { background:#fafafa;border:1px solid #e5e7eb;border-radius:4px;padding:16px;margin-bottom:16px; }
    .sw-panel__title { font-size:13px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.04em;margin:0 0 12px; }
    .sw-discount-wrap { display:flex;flex-wrap:wrap;gap:8px;align-items:center; }
    .sw-total-summary span { display:block;font-size:14px; }
    .sw-phone-group { display:flex;align-items:stretch; }
    .sw-phone-group .select2-container { width:108px!important;flex:0 0 108px; }
    .sw-phone-group .select2-container .select2-selection--single {
      height:38px;border-radius:4px 0 0 4px;border-right:0;display:flex;align-items:center;
    }
    .sw-phone-group .js-cf-phone-local { border-radius:0 4px 4px 0;flex:1 1 auto; }
    .sw-email-wrap { position:relative; }
    .sw-email-suggest {
      position:absolute;top:100%;left:0;right:0;z-index:30;background:#fff;
      border:1px solid #ddd;border-top:0;border-radius:0 0 4px 4px;max-height:180px;overflow-y:auto;display:none;
    }
    .sw-email-suggest.is-open { display:block; }
    .sw-email-suggest button {
      display:block;width:100%;text-align:left;border:0;background:#fff;padding:8px 12px;font-size:13px;cursor:pointer;
    }
    .sw-email-suggest button:hover { background:#f5f5f5; }
  </style>
  <link rel="stylesheet" href="{{ asset('panel-assets/css/select2.min.css') }}">
@endpush

@section('content')
  <div class="app-title">
    <div><h1><i class="fa fa-plus"></i> New event booking</h1></div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.conference.index') }}">Conference</a></li>
      <li class="breadcrumb-item">New</li>
    </ul>
  </div>

  <div class="row"><div class="col-md-12"><div class="tile sv-page">
    @if(session('error'))<div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>@endif

    @if($rates->isEmpty())
      <div class="sv-section">
        <p class="text-muted">No active conference rates. <a href="{{ route('hotel.services.show', $service) }}#conference-rates">Configure packages</a> first.</p>
      </div>
    @else
      <form method="POST" action="{{ route('hotel.conference.store') }}" class="sv-section">
        @csrf

        <div class="sw-panel">
          <p class="sw-panel__title">Event</p>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Event name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="event_name" required maxlength="200" value="{{ old('event_name') }}">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Organizer</label>
                <input type="text" class="form-control" name="organizer_name" maxlength="120" value="{{ old('organizer_name') }}">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Company</label>
                <input type="text" class="form-control" name="company_name" maxlength="120" value="{{ old('company_name') }}">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label class="control-label">Delegates</label>
                <input type="number" class="form-control js-cf-delegates" name="delegates" min="1" value="{{ old('delegates', 10) }}">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group mb-0">
                <label class="control-label">Start</label>
                <input type="datetime-local" class="form-control js-cf-start" name="starts_at" required
                  value="{{ old('starts_at', now()->format('Y-m-d\T08:00')) }}">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group mb-0">
                <label class="control-label">End</label>
                <input type="datetime-local" class="form-control js-cf-end" name="ends_at" required
                  value="{{ old('ends_at', now()->addDay()->format('Y-m-d\T17:00')) }}">
              </div>
            </div>
          </div>
        </div>

        <div class="sw-panel">
          <p class="sw-panel__title">Guest link</p>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">In-house guest</label>
                <select class="form-control js-cf-reservation" name="cm_reservation_id">
                  <option value="">External / walk-in</option>
                  @foreach($reservations as $res)
                    <option value="{{ $res->id }}" data-room="{{ $res->roomUnit?->room_number }}" data-guest="{{ $res->guestName() }}"
                      {{ (int) old('cm_reservation_id', $prefillReservationId ?? 0) === (int) $res->id ? 'selected' : '' }}>
                      Room {{ $res->roomUnit?->room_number ?? '—' }} — {{ $res->guestName() }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label class="control-label">Room</label>
                <input type="text" class="form-control js-cf-room" name="room_no" maxlength="20" value="{{ old('room_no') }}">
              </div>
            </div>
            <div class="col-md-4 js-cf-external">
              <div class="form-group mb-0">
                <label class="control-label">
                  Phone
                  @if(!empty($settings['require_contact_for_external']))<span class="text-danger">*</span>@endif
                </label>
                <div class="sw-phone-group">
                  <select class="js-cf-phone-code" id="cf_phone_code" aria-label="Country code"></select>
                  <input type="hidden" class="js-cf-phone-country-code" name="contact_phone_country_code"
                    value="{{ old('contact_phone_country_code', $defaultPhoneCode ?? '+255') }}">
                  <input type="tel" class="form-control js-cf-phone-local" name="contact_phone" maxlength="40"
                    value="{{ old('contact_phone') }}" placeholder="Phone number" autocomplete="tel-national">
                </div>
              </div>
            </div>
            <div class="col-md-4 js-cf-external">
              <div class="form-group mb-0">
                <label class="control-label">Email <span class="text-muted font-weight-normal">(optional)</span></label>
                <div class="sw-email-wrap">
                  <input type="email" class="form-control js-cf-email" name="contact_email" maxlength="160"
                    value="{{ old('contact_email') }}" placeholder="name@example.com" autocomplete="email">
                  <div class="sw-email-suggest js-cf-email-suggest" role="listbox"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="sw-panel">
          <p class="sw-panel__title">Package &amp; pricing</p>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Rate / package</label>
                <select class="form-control js-cf-rate" name="hotel_pms_conference_rate_id" required>
                  <option value="">Select…</option>
                  @foreach($rates as $rate)
                    <option value="{{ $rate->id }}"
                      data-unit="{{ $rate->pricing_unit }}"
                      data-price="{{ $rate->unit_price }}"
                      data-currency="{{ $rate->currency }}"
                      {{ (int) old('hotel_pms_conference_rate_id') === (int) $rate->id ? 'selected' : '' }}>
                      {{ $rate->displayTitle() }} — {{ $rate->formattedUnitPrice($hotel) }} / {{ $rate->pricingUnitLabel() }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-2 js-cf-qty-wrap" style="display:none;">
              <div class="form-group">
                <label class="control-label js-cf-qty-label">Half-days</label>
                <input type="number" step="0.5" min="0.5" class="form-control js-cf-billing" name="billing_quantity" value="{{ old('billing_quantity', 1) }}">
              </div>
            </div>
            @if($service->amount_editable)
              <div class="col-md-4">
                <label class="control-label">Discount</label>
                <div class="sw-discount-wrap">
                  <select class="form-control form-control-sm js-cf-discount-type" name="discount_type">
                    <option value="percent" {{ old('discount_type', 'percent') === 'percent' ? 'selected' : '' }}>By %</option>
                    <option value="amount" {{ old('discount_type') === 'amount' ? 'selected' : '' }}>By amount</option>
                  </select>
                  <input type="number" step="0.01" min="0" class="form-control form-control-sm js-cf-discount-value" name="discount_value" value="{{ old('discount_value', 0) }}">
                </div>
              </div>
            @endif
            <div class="col-md-4">
              <label class="control-label">Estimated total</label>
              <div class="sw-total-summary">
                <span>Subtotal: <strong class="js-cf-subtotal">—</strong></span>
                @if($service->amount_editable)
                  <span>Discount: <strong class="js-cf-discount-amt">—</strong></span>
                @endif
                <span>Total: <strong class="js-cf-total">—</strong></span>
              </div>
            </div>
          </div>
        </div>

        @include('hotel.pms.partials._desk-payment-fields', ['paymentOptions' => $paymentOptions ?? []])

        <div class="form-group">
          <label class="control-label">Notes</label>
          <input type="text" class="form-control" name="notes" maxlength="500" value="{{ old('notes') }}">
        </div>

        <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;"><i class="fa fa-check"></i> Save booking</button>
        <a href="{{ route('hotel.conference.index') }}" class="btn btn-light border ml-2">Cancel</a>
      </form>
    @endif
  </div></div></div>

  <script type="application/json" id="cf-default-phone-code">@json($defaultPhoneCode ?? '+255')</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  @include('hotel.pms.partials._desk-payment-script', ['paymentOptions' => $paymentOptions ?? []])
  <script>
    (function () {
      var defaultPhoneCode = '+255';
      var CountryModule = null;
      var emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'live.com', 'aol.com', 'protonmail.com', 'mail.com', 'zoho.com'
      ];
      try {
        defaultPhoneCode = JSON.parse(document.getElementById('cf-default-phone-code').textContent || '"+255"');
      } catch (e) {}
      var resSelect = document.querySelector('.js-cf-reservation');
      var roomInput = document.querySelector('.js-cf-room');
      var externalBlocks = document.querySelectorAll('.js-cf-external');
      var rateSelect = document.querySelector('.js-cf-rate');
      var delegatesInput = document.querySelector('.js-cf-delegates');
      var startInput = document.querySelector('.js-cf-start');
      var endInput = document.querySelector('.js-cf-end');
      var billingInput = document.querySelector('.js-cf-billing');
      var qtyWrap = document.querySelector('.js-cf-qty-wrap');
      var qtyLabel = document.querySelector('.js-cf-qty-label');
      var discountType = document.querySelector('.js-cf-discount-type');
      var discountValue = document.querySelector('.js-cf-discount-value');
      var subtotalEl = document.querySelector('.js-cf-subtotal');
      var discountAmtEl = document.querySelector('.js-cf-discount-amt');
      var totalEl = document.querySelector('.js-cf-total');

      function isWalkIn() { return !resSelect || resSelect.value === ''; }

      function syncGuestUi() {
        var walkIn = isWalkIn();
        externalBlocks.forEach(function (el) { el.style.display = walkIn ? '' : 'none'; });
        if (typeof window.deskPaymentSetWalkIn === 'function') window.deskPaymentSetWalkIn(walkIn);
        if (!walkIn && resSelect) {
          var opt = resSelect.options[resSelect.selectedIndex];
          if (roomInput) roomInput.value = opt.getAttribute('data-room') || '';
        }
      }

      function daysBetween() {
        if (!startInput?.value || !endInput?.value) return 1;
        var s = new Date(startInput.value);
        var e = new Date(endInput.value);
        var ms = e - s;
        if (ms <= 0) return 1;
        return Math.max(1, Math.ceil(ms / 86400000));
      }

      function selectedRate() {
        if (!rateSelect) return null;
        var opt = rateSelect.options[rateSelect.selectedIndex];
        if (!opt?.value) return null;
        return {
          unit: opt.dataset.unit,
          price: parseFloat(opt.dataset.price || '0'),
          currency: opt.dataset.currency || 'TZS'
        };
      }

      function updateTotal() {
        var rate = selectedRate();
        if (!rate) {
          if (subtotalEl) subtotalEl.textContent = '—';
          if (totalEl) totalEl.textContent = '—';
          if (typeof window.deskPaymentUpdateTotal === 'function') window.deskPaymentUpdateTotal(0, null);
          return;
        }
        var qty = 1;
        if (rate.unit === 'per_person') qty = parseInt(delegatesInput?.value || '1', 10) || 1;
        else if (rate.unit === 'per_day') qty = daysBetween();
        else if (rate.unit === 'per_half_day') qty = parseFloat(billingInput?.value || '1') || 1;
        var subtotal = rate.unit === 'fixed' ? rate.price : rate.price * qty;
        subtotal = Math.round(subtotal * 100) / 100;
        var disc = 0;
        if (discountType && discountValue) {
          var v = parseFloat(discountValue.value || '0') || 0;
          if (discountType.value === 'percent') disc = subtotal * Math.min(100, v) / 100;
          else disc = Math.min(v, subtotal);
        }
        disc = Math.round(disc * 100) / 100;
        var total = Math.round((subtotal - disc) * 100) / 100;
        var cur = rate.currency;
        if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2) + ' ' + cur;
        if (discountAmtEl) discountAmtEl.textContent = disc.toFixed(2) + ' ' + cur;
        if (totalEl) totalEl.textContent = total.toFixed(2) + ' ' + cur;
        if (typeof window.deskPaymentUpdateTotal === 'function') {
          window.deskPaymentUpdateTotal(total, cur);
        }
      }

      function syncRateUi() {
        var rate = selectedRate();
        if (qtyWrap) {
          var show = rate && rate.unit === 'per_half_day';
          qtyWrap.style.display = show ? '' : 'none';
          if (qtyLabel) qtyLabel.textContent = 'Half-days';
        }
        updateTotal();
      }

      if (resSelect) resSelect.addEventListener('change', syncGuestUi);
      [rateSelect, delegatesInput, startInput, endInput, billingInput, discountType, discountValue].forEach(function (el) {
        if (el) el.addEventListener('input', function () { syncRateUi(); });
        if (el) el.addEventListener('change', function () { syncRateUi(); });
      });

      function bindEmailAutocomplete() {
        var emailInput = document.querySelector('.js-cf-email');
        var suggestBox = document.querySelector('.js-cf-email-suggest');
        if (!emailInput || !suggestBox) return;

        function hideSuggestions() {
          suggestBox.classList.remove('is-open');
          suggestBox.innerHTML = '';
        }

        emailInput.addEventListener('input', function () {
          var value = emailInput.value.trim();
          if (!value || value.indexOf(' ') !== -1 || value.indexOf('@') !== -1) {
            hideSuggestions();
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
        });

        document.addEventListener('click', function (event) {
          if (!emailInput.contains(event.target) && !suggestBox.contains(event.target)) {
            hideSuggestions();
          }
        });
      }

      function initPhoneCountryCode(selectedCode) {
        var phoneSelect = document.querySelector('.js-cf-phone-code');
        var phoneHidden = document.querySelector('.js-cf-phone-country-code');
        if (!phoneSelect || typeof jQuery === 'undefined' || !CountryModule) return;

        var $phone = jQuery(phoneSelect);
        var countries = CountryModule.getAllCountries();
        phoneSelect.innerHTML = countries.map(function (c) {
          var dial = '+' + c.phonecode;
          return '<option value="' + dial + '">' + dial + ' ' + c.isoCode + '</option>';
        }).join('');

        $phone.select2({ width: '108px', minimumResultsForSearch: 5 });
        $phone.off('change.cf').on('change.cf', function () {
          if (phoneHidden) phoneHidden.value = $phone.val() || '';
        });

        var initial = selectedCode || phoneHidden?.value || defaultPhoneCode;
        if (initial) {
          $phone.val(initial).trigger('change.select2');
          if (phoneHidden) phoneHidden.value = initial;
        }
      }

      bindEmailAutocomplete();
      syncGuestUi();
      syncRateUi();

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          CountryModule = module.Country;
          initPhoneCountryCode(document.querySelector('.js-cf-phone-country-code')?.value || defaultPhoneCode);
        })
        .catch(function () {});
    })();
  </script>
@endpush
