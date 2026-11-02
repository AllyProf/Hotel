@extends('layouts.app')

@section('title', 'Register pool entry')

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sv-header { padding: 18px 20px; border-bottom: 3px solid var(--sv-brand); }
    .sv-header h3 { margin: 0; font-size: 22px; font-weight: 400; color: #333; }
    .sw-panel {
      background: #fafafa;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 16px;
      margin-bottom: 16px;
    }
    .sw-panel__title {
      font-size: 13px;
      font-weight: 700;
      color: #555;
      margin: 0 0 12px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .sw-phone-group {
      display: flex;
      align-items: stretch;
    }

    .sw-phone-group .select2-container {
      width: 108px !important;
      flex: 0 0 108px;
    }

    .sw-phone-group .select2-container .select2-selection--single {
      height: 38px;
      border-radius: 4px 0 0 4px;
      border-right: 0;
      display: flex;
      align-items: center;
    }

    .sw-phone-group .js-sw-phone-local {
      border-radius: 0 4px 4px 0;
      flex: 1 1 auto;
    }

    .sw-email-wrap { position: relative; }

    .sw-email-suggest {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 30;
      background: #fff;
      border: 1px solid #ddd;
      border-top: 0;
      border-radius: 0 0 4px 4px;
      max-height: 180px;
      overflow-y: auto;
      display: none;
    }

    .sw-email-suggest.is-open { display: block; }

    .sw-email-suggest button {
      display: block;
      width: 100%;
      text-align: left;
      border: 0;
      background: #fff;
      padding: 8px 12px;
      font-size: 13px;
      cursor: pointer;
    }

    .sw-email-suggest button:hover { background: #f5f5f5; }

    .sw-discount-wrap {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
    }
    .sw-discount-wrap .js-sw-discount-type { max-width: 120px; flex: 0 0 auto; }
    .sw-discount-wrap .input-group { max-width: 160px; flex: 1 1 auto; }
    .sw-total-summary { font-size: 14px; line-height: 1.6; }
    .sw-total-summary span { display: block; }
  </style>
  <link rel="stylesheet" href="{{ asset('panel-assets/css/select2.min.css') }}">
@endpush

@section('content')
  @php
    $swim = $settings ?? $service->swimmingSettings();
    $inhouseAlwaysFree = ! empty($swim['complimentary_for_inhouse']);
    $allowDeskFree = ! empty($swim['allow_desk_complimentary_override']);
    $requireWalkinContact = ! empty($swim['require_contact_for_walkin']);
    $maxPersons = (int) ($swim['max_persons_per_entry'] ?? 10);
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-plus"></i> Register pool entry</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.swimming.index') }}">Swimming</a></li>
      <li class="breadcrumb-item">New</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile sv-page">
        @if(session('error'))
          <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        <div class="sv-header"><h3>New entry</h3></div>

        <div class="sv-section">
          @if($rates->isEmpty())
            <p class="text-muted">No active pool rates. <a href="{{ route('hotel.services.show', $service) }}#swimming-rates">Configure rate plans</a> first.</p>
          @else
            <form method="POST" action="{{ route('hotel.swimming.store') }}" id="swEntryForm">
              @csrf

              <div class="sw-panel">
                <p class="sw-panel__title"><i class="fa fa-calendar"></i> Visit date</p>
                <div class="row">
                  <div class="col-md-3">
                    <div class="form-group mb-0">
                      <label class="control-label">Pool date</label>
                      <input type="date" class="form-control js-sw-date" name="scheduled_date" required
                        min="{{ now()->format('Y-m-d') }}"
                        value="{{ old('scheduled_date', now()->format('Y-m-d')) }}">
                      <small class="text-muted">Register today for a future visit, or use today to enter now.</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="sw-panel">
                <p class="sw-panel__title"><i class="fa fa-user"></i> Guest</p>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="control-label">In-house guest</label>
                      <select class="form-control js-sw-reservation" name="cm_reservation_id">
                        <option value="">Walk-in / external</option>
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
                      <input type="text" class="form-control js-sw-room" name="room_no" maxlength="20" value="{{ old('room_no') }}">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="control-label">Guest name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control js-sw-guest" name="guest_name" maxlength="120" value="{{ old('guest_name') }}">
                    </div>
                  </div>
                </div>

                <div class="row js-sw-walkin-contact">
                  <div class="col-md-4">
                    <div class="form-group mb-0">
                      <label class="control-label">
                        Phone
                        @if($requireWalkinContact)<span class="text-danger">*</span>@endif
                      </label>
                      <div class="sw-phone-group">
                        <select class="js-sw-phone-code" id="sw_phone_code" aria-label="Country code"></select>
                        <input type="hidden" class="js-sw-phone-country-code" name="guest_phone_country_code"
                          value="{{ old('guest_phone_country_code', $defaultPhoneCode ?? '+255') }}">
                        <input type="tel" class="form-control js-sw-phone-local" name="guest_phone" maxlength="40"
                          value="{{ old('guest_phone') }}" placeholder="Phone number" autocomplete="tel-national">
                      </div>
                      <small class="text-muted">Country code + local number (walk-in).</small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-0">
                      <label class="control-label">Email <span class="text-muted font-weight-normal">(optional)</span></label>
                      <div class="sw-email-wrap">
                        <input type="email" class="form-control js-sw-email" name="guest_email" maxlength="160"
                          value="{{ old('guest_email') }}" placeholder="name@example.com"
                          autocomplete="email">
                        <div class="sw-email-suggest js-sw-email-suggest" role="listbox"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-3 js-sw-inhouse-free" style="display:none;">
                  @if($inhouseAlwaysFree)
                    <div class="alert alert-info py-2 mb-0 small"><i class="fa fa-gift"></i> In-house pool access is <strong>complimentary</strong> per your swimming settings.</div>
                  @elseif($allowDeskFree)
                    <label class="animated-checkbox mb-0">
                      <input type="checkbox" name="apply_complimentary" value="1" class="js-sw-complimentary"
                        {{ old('apply_complimentary') ? 'checked' : '' }}>
                      <span class="label-text">Complimentary for this in-house guest (no charge)</span>
                    </label>
                  @else
                    <p class="text-muted small mb-0">In-house guests are charged using the selected rate plan.</p>
                  @endif
                </div>
              </div>

              <div class="sw-panel">
                <p class="sw-panel__title"><i class="fa fa-ticket"></i> Rate &amp; party size</p>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="control-label">Rate plan</label>
                      <select class="form-control js-sw-rate" name="hotel_pms_swimming_rate_id" required>
                        <option value="">Select rate…</option>
                        @foreach($rates as $rate)
                          <option value="{{ $rate->id }}"
                            data-unit="{{ $rate->pricing_unit }}"
                            data-price="{{ $rate->unit_price }}"
                            data-currency="{{ $rate->currency }}"
                            data-audience="{{ $rate->audience }}"
                            data-category="{{ $rate->hotel_pms_service_category_id }}"
                            {{ (int) old('hotel_pms_swimming_rate_id') === (int) $rate->id ? 'selected' : '' }}>
                            {{ $rate->displayTitle() }} — {{ $rate->formattedUnitPrice($hotel) }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                </div>

                <div class="row js-sw-qty-row">
                  <div class="col-md-2 js-sw-adults-wrap">
                    <div class="form-group">
                      <label class="control-label">Adults</label>
                      <input type="number" class="form-control js-sw-adults" name="adults" min="0" max="{{ $maxPersons }}" value="{{ old('adults', 2) }}">
                    </div>
                  </div>
                  <div class="col-md-2 js-sw-children-wrap">
                    <div class="form-group">
                      <label class="control-label">Children</label>
                      <input type="number" class="form-control js-sw-children" name="children" min="0" max="{{ $maxPersons }}" value="{{ old('children', 0) }}">
                    </div>
                  </div>
                  <div class="col-md-2 js-sw-qty-other" style="display:none;">
                    <div class="form-group">
                      <label class="control-label js-sw-qty-label">Quantity</label>
                      <input type="number" class="form-control js-sw-billing" name="billing_quantity" min="0" step="0.5" value="{{ old('billing_quantity', 1) }}">
                    </div>
                  </div>
                  @if($service->amount_editable)
                    @php
                      $discountType = old('discount_type', 'percent');
                      $discountValue = old('discount_value', 0);
                    @endphp
                    <div class="col-md-4">
                      <div class="form-group mb-0">
                        <label class="control-label">Discount</label>
                        <div class="sw-discount-wrap">
                          <select class="form-control form-control-sm js-sw-discount-type" name="discount_type">
                            <option value="percent" {{ $discountType === 'percent' ? 'selected' : '' }}>By %</option>
                            <option value="amount" {{ $discountType === 'amount' ? 'selected' : '' }}>By amount</option>
                          </select>
                          <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" @if($discountType === 'percent') max="100" @endif
                              class="form-control js-sw-discount-value" name="discount_value" value="{{ $discountValue }}">
                            <div class="input-group-append">
                              <span class="input-group-text js-sw-discount-suffix">{{ $discountType === 'amount' ? ($defaultCurrency ?? 'TZS') : '%' }}</span>
                            </div>
                          </div>
                          <button type="button" class="btn btn-outline-secondary btn-sm js-sw-discount-undo">Undo</button>
                        </div>
                      </div>
                    </div>
                  @endif
                  <div class="col-md-{{ $service->amount_editable ? '4' : '5' }}">
                    <div class="form-group mb-0">
                      <label class="control-label">Estimated total</label>
                      <div class="sw-total-summary">
                        <span>Subtotal: <strong class="js-sw-subtotal">—</strong></span>
                        @if($service->amount_editable)
                          <span class="js-sw-discount-line">Discount: <strong class="js-sw-discount-amount">—</strong></span>
                        @endif
                        <span>Total: <strong class="js-sw-total" style="font-size:18px;">—</strong></span>
                      </div>
                      <p class="text-muted small mb-0 js-sw-breakdown"></p>
                    </div>
                  </div>
                </div>
                <p class="text-muted small mb-0">Example: 2 adults + 3 children uses adult and child rates for the same pass type when configured.</p>
              </div>

              @include('hotel.pms.partials._desk-payment-fields', ['paymentOptions' => $paymentOptions ?? []])

              <div class="form-group mt-2">
                <label class="control-label">Notes</label>
                <input type="text" class="form-control" name="notes" maxlength="500" value="{{ old('notes') }}">
              </div>

              <button type="submit" class="btn btn-sv js-sw-submit" style="width:auto;padding:8px 22px;">
                <i class="fa fa-check"></i> <span class="js-sw-submit-label">Register</span>
              </button>
              <a href="{{ route('hotel.swimming.index') }}" class="btn btn-light border ml-2">Cancel</a>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>

  <script type="application/json" id="sw-default-phone-code">@json($defaultPhoneCode ?? '+255')</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  @include('hotel.pms.partials._desk-payment-script', ['paymentOptions' => $paymentOptions ?? []])
  <script>
    (function () {
      var childRateMap = @json($childRateMap ?? []);
      var defaultPhoneCode = '+255';
      var CountryModule = null;
      var emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'live.com', 'aol.com', 'protonmail.com', 'mail.com', 'zoho.com'
      ];

      try {
        defaultPhoneCode = JSON.parse(document.getElementById('sw-default-phone-code').textContent || '"+255"');
      } catch (e) {}
      var inhouseAlwaysFree = @json($inhouseAlwaysFree);
      var resSelect = document.querySelector('.js-sw-reservation');
      var walkinBlock = document.querySelector('.js-sw-walkin-contact');
      var inhouseFree = document.querySelector('.js-sw-inhouse-free');
      var complimentaryCb = document.querySelector('.js-sw-complimentary');
      var dateInput = document.querySelector('.js-sw-date');
      var submitLabel = document.querySelector('.js-sw-submit-label');
      var roomInput = document.querySelector('.js-sw-room');
      var guestInput = document.querySelector('.js-sw-guest');
      var rateSelect = document.querySelector('.js-sw-rate');
      var adultsInput = document.querySelector('.js-sw-adults');
      var childrenInput = document.querySelector('.js-sw-children');
      var billingInput = document.querySelector('.js-sw-billing');
      var discountTypeSelect = document.querySelector('.js-sw-discount-type');
      var discountValueInput = document.querySelector('.js-sw-discount-value');
      var discountSuffix = document.querySelector('.js-sw-discount-suffix');
      var discountUndo = document.querySelector('.js-sw-discount-undo');
      var subtotalEl = document.querySelector('.js-sw-subtotal');
      var discountAmountEl = document.querySelector('.js-sw-discount-amount');
      var totalEl = document.querySelector('.js-sw-total');
      var breakdownEl = document.querySelector('.js-sw-breakdown');
      var defaultCurrency = @json($defaultCurrency ?? 'TZS');
      var adultsWrap = document.querySelector('.js-sw-adults-wrap');
      var childrenWrap = document.querySelector('.js-sw-children-wrap');
      var qtyOther = document.querySelector('.js-sw-qty-other');
      var qtyLabel = document.querySelector('.js-sw-qty-label');

      function isInHouse() {
        return resSelect && resSelect.value !== '';
      }

      function isFutureDate() {
        if (!dateInput || !dateInput.value) return false;
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var picked = new Date(dateInput.value + 'T00:00:00');
        return picked > today;
      }

      function syncGuestTypeUi() {
        var inHouse = isInHouse();
        if (walkinBlock) walkinBlock.style.display = inHouse ? 'none' : '';
        if (inhouseFree) inhouseFree.style.display = inHouse ? '' : 'none';
        if (roomInput) roomInput.readOnly = inHouse;
        syncSubmitLabel();
        updateTotal();
        if (typeof window.deskPaymentSetWalkIn === 'function') {
          window.deskPaymentSetWalkIn(!inHouse);
        }
      }

      function syncSubmitLabel() {
        if (!submitLabel) return;
        if (isFutureDate()) {
          submitLabel.textContent = 'Save scheduled visit';
        } else if (isInHouse()) {
          submitLabel.textContent = 'Register & enter pool';
        } else {
          submitLabel.textContent = 'Register walk-in';
        }
      }

      function selectedRate() {
        if (!rateSelect) return null;
        var opt = rateSelect.options[rateSelect.selectedIndex];
        if (!opt || !opt.value) return null;
        return {
          unit: opt.dataset.unit,
          price: parseFloat(opt.dataset.price || '0'),
          currency: opt.dataset.currency || 'TZS',
          audience: opt.dataset.audience || 'adult',
          categoryId: opt.dataset.category || ''
        };
      }

      function syncGuestFromReservation() {
        if (!resSelect) return;
        var opt = resSelect.options[resSelect.selectedIndex];
        if (opt && opt.value) {
          if (roomInput && opt.dataset.room) roomInput.value = opt.dataset.room;
          if (guestInput && opt.dataset.guest) guestInput.value = opt.dataset.guest;
        }
        syncGuestTypeUi();
      }

      function syncQuantityFields() {
        var rate = selectedRate();
        if (!rate) return;
        var isPerson = rate.unit === 'per_person';
        if (adultsWrap) adultsWrap.style.display = isPerson ? '' : 'none';
        if (childrenWrap) childrenWrap.style.display = isPerson ? '' : 'none';
        if (qtyOther) {
          qtyOther.style.display = isPerson ? 'none' : '';
          if (qtyLabel) {
            qtyLabel.textContent = rate.unit === 'per_hour' ? 'Hours' : (rate.unit === 'per_day' ? 'Days' : 'Quantity');
          }
        }
        updateTotal();
      }

      function computeSubtotal(rate) {
        var unit = rate.price;
        var adults = parseInt(adultsInput?.value || '0', 10) || 0;
        var children = parseInt(childrenInput?.value || '0', 10) || 0;
        var subtotal = 0;
        var parts = [];

        if (rate.unit === 'per_person') {
          if (rate.audience === 'child') {
            var cc = children > 0 ? children : 1;
            subtotal = unit * cc;
            parts.push(cc + ' child × ' + unit.toFixed(2));
          } else {
            var ac = adults > 0 ? adults : (children > 0 ? 0 : 1);
            if (ac > 0) {
              subtotal += unit * ac;
              parts.push(ac + ' adult × ' + unit.toFixed(2));
            }
            if (children > 0) {
              var childInfo = childRateMap[rate.categoryId];
              var childUnit = childInfo ? parseFloat(childInfo.price) : unit;
              subtotal += childUnit * children;
              parts.push(children + ' child × ' + childUnit.toFixed(2));
            }
          }
        } else if (rate.unit === 'fixed') {
          subtotal = unit;
          parts.push('Flat fee');
        } else {
          var qty = parseFloat(billingInput?.value || '1') || 1;
          subtotal = unit * qty;
          parts.push(qty + ' × ' + unit.toFixed(2));
        }

        return { subtotal: subtotal, parts: parts, currency: rate.currency };
      }

      function applyDiscount(subtotal) {
        if (!discountTypeSelect || !discountValueInput) {
          return { discountAmount: 0, total: subtotal };
        }
        var type = discountTypeSelect.value;
        var value = parseFloat(discountValueInput.value || '0') || 0;
        var discountAmount = 0;
        if (value > 0) {
          if (type === 'percent') {
            discountAmount = subtotal * (Math.min(100, Math.max(0, value)) / 100);
          } else if (type === 'amount') {
            discountAmount = Math.min(value, subtotal);
          }
        }
        discountAmount = Math.round(discountAmount * 100) / 100;
        return {
          discountAmount: discountAmount,
          total: Math.round(Math.max(0, subtotal - discountAmount) * 100) / 100
        };
      }

      function syncDiscountTypeUi() {
        if (!discountTypeSelect || !discountValueInput || !discountSuffix) return;
        var isPercent = discountTypeSelect.value === 'percent';
        discountSuffix.textContent = isPercent ? '%' : defaultCurrency;
        if (isPercent) {
          discountValueInput.setAttribute('max', '100');
        } else {
          discountValueInput.removeAttribute('max');
        }
      }

      function updateTotal() {
        if (!totalEl) return;
        var rate = selectedRate();
        if (!rate) {
          if (subtotalEl) subtotalEl.textContent = '—';
          if (discountAmountEl) discountAmountEl.textContent = '—';
          totalEl.textContent = '—';
          if (breakdownEl) breakdownEl.textContent = '';
          if (typeof window.deskPaymentUpdateTotal === 'function') {
            window.deskPaymentUpdateTotal(0, null);
          }
          return;
        }

        if ((isInHouse() && inhouseAlwaysFree) || (complimentaryCb && complimentaryCb.checked)) {
          if (subtotalEl) subtotalEl.textContent = 'Complimentary';
          if (discountAmountEl) discountAmountEl.textContent = '—';
          totalEl.textContent = 'Complimentary';
          if (breakdownEl) breakdownEl.textContent = '';
          if (typeof window.deskPaymentUpdateTotal === 'function') {
            window.deskPaymentUpdateTotal(0, null);
          }
          return;
        }

        var calc = computeSubtotal(rate);
        var subtotal = calc.subtotal;
        var discounted = applyDiscount(subtotal);
        var cur = calc.currency;

        if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2) + ' ' + cur;
        if (discountAmountEl) {
          discountAmountEl.textContent = discounted.discountAmount.toFixed(2) + ' ' + cur;
        }
        totalEl.textContent = discounted.total.toFixed(2) + ' ' + cur;
        if (breakdownEl) breakdownEl.textContent = calc.parts.join(' + ');
        if (typeof window.deskPaymentUpdateTotal === 'function') {
          window.deskPaymentUpdateTotal(discounted.total, cur);
        }
      }

      if (resSelect) resSelect.addEventListener('change', syncGuestFromReservation);
      if (rateSelect) rateSelect.addEventListener('change', syncQuantityFields);
      if (dateInput) dateInput.addEventListener('change', syncSubmitLabel);
      if (complimentaryCb) complimentaryCb.addEventListener('change', updateTotal);
      [adultsInput, childrenInput, billingInput, discountValueInput].forEach(function (el) {
        if (el) el.addEventListener('input', updateTotal);
      });
      if (discountTypeSelect) {
        discountTypeSelect.addEventListener('change', function () {
          syncDiscountTypeUi();
          updateTotal();
        });
      }
      if (discountUndo) {
        discountUndo.addEventListener('click', function () {
          if (discountValueInput) discountValueInput.value = '0';
          updateTotal();
        });
      }
      syncDiscountTypeUi();

      function bindEmailAutocomplete() {
        var emailInput = document.querySelector('.js-sw-email');
        var suggestBox = document.querySelector('.js-sw-email-suggest');
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
        var phoneSelect = document.querySelector('.js-sw-phone-code');
        var phoneHidden = document.querySelector('.js-sw-phone-country-code');
        if (!phoneSelect || typeof jQuery === 'undefined' || !CountryModule) return;

        var $phone = jQuery(phoneSelect);
        if ($phone.data('select2')) {
          $phone.select2('destroy');
        }

        $phone.empty();
        CountryModule.getAllCountries().sort(function (a, b) {
          return a.name.localeCompare(b.name);
        }).forEach(function (country) {
          var dial = '+' + country.phonecode;
          var isSelected = (selectedCode || defaultPhoneCode) === dial ? ' selected' : '';
          $phone.append('<option value="' + dial + '" data-flag="' + country.flag + '"' + isSelected + '>' + dial + '</option>');
        });

        $phone.select2({
          width: '108px',
          templateResult: function (option) {
            if (!option.id) return option.text;
            var flag = jQuery(option.element).data('flag') || '';
            return jQuery('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
          },
          templateSelection: function (option) {
            if (!option.id) return option.text;
            var flag = jQuery(option.element).data('flag') || '';
            return jQuery('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
          }
        });

        $phone.off('change.sw').on('change.sw', function () {
          if (phoneHidden) phoneHidden.value = $phone.val() || '';
        });

        var initial = selectedCode || phoneHidden?.value || defaultPhoneCode;
        if (initial) {
          $phone.val(initial).trigger('change.select2');
          if (phoneHidden) phoneHidden.value = initial;
        }
      }

      bindEmailAutocomplete();
      syncGuestFromReservation();
      syncQuantityFields();
      syncSubmitLabel();

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          CountryModule = module.Country;
          initPhoneCountryCode(document.querySelector('.js-sw-phone-country-code')?.value || defaultPhoneCode);
        })
        .catch(function () {});
    })();
  </script>
@endpush
