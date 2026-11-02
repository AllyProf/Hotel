@extends('layouts.app')

@section('title', 'Create Multi Booking')

@push('styles')
  <style>
    .mb-page-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
    }

    .mb-page-head h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 500;
      color: #333;
    }

    .mb-booking-block {
      background: #f5f5f5;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      padding: 12px;
      margin-bottom: 12px;
    }

    .mb-booking-block__row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: flex-end;
    }

    .mb-booking-block__row--bottom,
    .mb-booking-block__row--payment {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid #e0e0e0;
    }

    .mb-field--summary {
      flex: 2 1 280px;
      min-width: 240px;
    }

    .mb-row-total-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 16px;
      justify-content: flex-end;
      font-size: 12px;
      color: #444;
      padding-top: 22px;
    }

    .mb-row-total-summary strong { color: var(--brand, #940000); }
    .mb-row-total-summary .js-mb-remaining-wrap.is-hidden { display: none; }
    .mb-row-total-summary .js-mb-remaining-wrap.is-due strong { color: #c0392b; }

    .mb-field {
      flex: 1 1 110px;
      min-width: 100px;
    }

    .mb-field--wide { flex: 1 1 160px; }
    .mb-field--narrow { flex: 0 1 80px; max-width: 90px; }
    .mb-field--check { flex: 0 1 120px; }
    .mb-field--actions {
      flex: 0 0 auto;
      display: flex;
      gap: 6px;
      align-items: center;
      padding-bottom: 2px;
    }

    .mb-action-btn {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      padding: 0;
      line-height: 1;
      font-size: 18px;
    }

    .mb-availability-msg {
      min-height: 18px;
      padding: 4px 2px 0;
    }

    .mb-availability-msg.is-ok { color: #28a745; }
    .mb-availability-msg.is-bad { color: #dc3545; }

    .mb-footer {
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid #e0e0e0;
    }

    .mb-section-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--brand, #940000);
      border-bottom: 1px solid #ddd;
      padding-bottom: 6px;
      margin: 0 0 12px;
    }

    .mb-total-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 16px 24px;
      justify-content: flex-end;
      margin-bottom: 16px;
      font-size: 14px;
      color: #444;
    }

    .mb-total-summary strong { color: var(--brand, #940000); font-size: 16px; }

    .mb-total-summary .js-mb-remaining-wrap.is-hidden { display: none; }
    .mb-total-summary .js-mb-remaining-wrap.is-due strong { color: #c0392b; }

    .mb-discount-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .mb-discount-wrap .input-group { max-width: 150px; }
    .mb-discount-wrap .js-mb-discount-type { max-width: 120px; flex: 0 0 auto; }

    .mb-phone-group {
      display: flex;
      align-items: stretch;
    }

    .mb-phone-group .select2-container {
      width: 108px !important;
      flex: 0 0 108px;
    }

    .mb-phone-group .select2-container .select2-selection--single {
      height: 31px;
      border-radius: 4px 0 0 4px;
      border-right: 0;
      display: flex;
      align-items: center;
    }

    .mb-phone-group .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 29px;
      padding-left: 6px;
      padding-right: 20px;
      font-size: 12px;
    }

    .mb-phone-group .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 29px;
    }

    .mb-phone-group .js-mb-guest-phone {
      border-radius: 0 4px 4px 0;
      flex: 1 1 auto;
    }

    .mb-email-wrap { position: relative; }

    .mb-email-suggest {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 30;
      background: #fff;
      border: 1px solid #ddd;
      border-top: 0;
      border-radius: 0 0 4px 4px;
      max-height: 160px;
      overflow-y: auto;
      display: none;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }

    .mb-email-suggest.is-open { display: block; }

    .mb-email-suggest button {
      display: block;
      width: 100%;
      border: 0;
      background: #fff;
      text-align: left;
      padding: 7px 10px;
      font-size: 12px;
      cursor: pointer;
    }

    .mb-email-suggest button:hover { background: #f5f5f5; }

    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 6px; }
  </style>
@endpush

@section('content')
  @php
    $oldBookings = old('bookings', [[
      'checkin' => $options['default_checkin'] ?? now()->format('Y-m-d'),
      'checkout' => $options['default_checkout'] ?? now()->addDay()->format('Y-m-d'),
      'hotel_room_id' => $options['default_room_id'] ?? '',
      'hotel_rate_plan_id' => $options['default_rate_plan_id'] ?? '',
      'room_count' => 1,
      'guest_count' => 1,
      'tax_inclusive' => false,
      'payment_mode' => $options['default_payment_mode'] ?? 'Cash',
      'guest_country_code' => $options['default_country_code'] ?? '',
      'guest_country' => $options['default_country'] ?? '',
      'guest_phone_country_code' => $options['default_phone_code'] ?? '+255',
      'advance_amount' => 0,
      'discount_type' => 'percent',
      'discount_value' => 0,
    ]]);
  @endphp

  <div class="mb-page-head">
    <h3>Create Multi Booking</h3>
    <a href="{{ route('hotel.reservations.index') }}" class="btn btn-primary btn-sm">Back</a>
  </div>

  <div class="tile">
    <div class="tile-body">
      <form action="{{ route('hotel.reservations.multi-booking.store') }}" method="POST" id="mbForm">
        @csrf

        <div id="mbBookingRows">
          @foreach($oldBookings as $index => $booking)
            @include('hotel.pms.reservations.partials._multi-booking-row', [
              'index' => $index,
              'booking' => $booking,
              'options' => $options,
              'showRemove' => $index > 0,
            ])
          @endforeach
        </div>

        @error('bookings')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div class="mb-footer text-right">
          <button type="submit" class="btn btn-primary btn-lg js-mb-submit" style="background:#940000;border-color:#940000;">
            <i class="fa fa-check-circle"></i> Submit
          </button>
        </div>
      </form>
    </div>
  </div>

  @include('hotel.pms.reservations.partials._guest-search-modal')

  <template id="mbBookingRowTemplate">
    @include('hotel.pms.reservations.partials._multi-booking-row', [
      'index' => '__INDEX__',
      'booking' => [
        'checkin' => $options['default_checkin'] ?? now()->format('Y-m-d'),
        'checkout' => $options['default_checkout'] ?? now()->addDay()->format('Y-m-d'),
        'hotel_room_id' => $options['default_room_id'] ?? '',
        'hotel_rate_plan_id' => $options['default_rate_plan_id'] ?? '',
        'room_count' => 1,
        'guest_count' => 1,
        'tax_inclusive' => false,
        'payment_mode' => $options['default_payment_mode'] ?? 'Cash',
        'guest_country_code' => $options['default_country_code'] ?? '',
        'guest_country' => $options['default_country'] ?? '',
        'guest_phone_country_code' => $options['default_phone_code'] ?? '+255',
        'advance_amount' => 0,
        'discount_type' => 'percent',
        'discount_value' => 0,
      ],
      'options' => $options,
      'showRemove' => true,
    ])
  </template>

  <script type="application/json" id="mb-room-data">@json($options['rooms'])</script>
  <script type="application/json" id="mb-default-country">@json($options['default_country_code'] ?? '')</script>
  <script type="application/json" id="mb-default-phone-code">@json($options['default_phone_code'] ?? '+255')</script>
  <script type="application/json" id="mb-payment-provider-config">@json($options['payment_provider_config'] ?? ['bank_providers' => [], 'mobile_payment_providers' => []])</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var roomData = [];
      var defaultCountryCode = '';
      var defaultPhoneCode = '+255';
      var discountCurrency = @json($options['currency'] ?? 'USD');
      var emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'live.com', 'aol.com', 'protonmail.com', 'mail.com', 'zoho.com',
        'yandex.com', 'gmx.com', 'msn.com'
      ];
      var rowsWrap = document.getElementById('mbBookingRows');
      var rowIndex = rowsWrap ? rowsWrap.querySelectorAll('.mb-booking-block').length : 0;
      var availabilityUrl = @json(route('hotel.reservations.multi-booking.check-availability'));
      var csrf = @json(csrf_token());
      var paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
      var availabilityTimers = new WeakMap();
      var CountryModule = null;
      var CityModule = null;
      var activeGuestBlock = null;

      try {
        roomData = JSON.parse(document.getElementById('mb-room-data').textContent || '[]');
        defaultCountryCode = JSON.parse(document.getElementById('mb-default-country').textContent || '""');
        defaultPhoneCode = JSON.parse(document.getElementById('mb-default-phone-code').textContent || '"+255"');
        paymentProviderConfig = JSON.parse(document.getElementById('mb-payment-provider-config').textContent || '{}');
      } catch (e) {}

      function formatMoney(value) {
        return (Math.round(value * 100) / 100).toFixed(2);
      }

      function blockNights(block) {
        var checkin = block.querySelector('.js-mb-checkin');
        var checkout = block.querySelector('.js-mb-checkout');
        if (!checkin || !checkout || !checkin.value || !checkout.value) return 1;
        var start = new Date(checkin.value + 'T00:00:00');
        var end = new Date(checkout.value + 'T00:00:00');
        var nights = Math.round((end - start) / 86400000);
        return Math.max(1, nights || 1);
      }

      function selectedPlanRate(block) {
        var planSelect = block.querySelector('.js-mb-rate-plan');
        if (!planSelect) return 0;
        var option = planSelect.options[planSelect.selectedIndex];
        return parseFloat(option?.getAttribute('data-rate') || '0') || 0;
      }

      function blockSubtotal(block) {
        var rate = selectedPlanRate(block);
        var count = parseInt(block.querySelector('.js-mb-room-count')?.value || '0', 10) || 0;
        return rate * blockNights(block) * count;
      }

      function updateBlockTotals(block) {
        if (!block) return;

        var subtotal = blockSubtotal(block);
        var typeSelect = block.querySelector('.js-mb-discount-type');
        var discountInput = block.querySelector('.js-mb-discount');
        var discountValue = parseFloat(discountInput?.value || '0') || 0;
        var discountAmount = 0;

        if (typeSelect?.value === 'percent') {
          discountAmount = subtotal * (Math.min(100, discountValue) / 100);
        } else {
          discountAmount = Math.min(discountValue, subtotal);
        }

        var total = Math.max(0, subtotal - discountAmount);
        var amountPaid = parseFloat(block.querySelector('.js-mb-advance-amount')?.value || '0') || 0;
        var remaining = Math.max(0, total - amountPaid);

        var subtotalEl = block.querySelector('.js-mb-subtotal');
        var discountEl = block.querySelector('.js-mb-discount-amount');
        var totalEl = block.querySelector('.js-mb-total');
        var remainingWrap = block.querySelector('.js-mb-remaining-wrap');
        var remainingEl = block.querySelector('.js-mb-remaining');

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

      function updateBlockDiscountField(block) {
        if (!block) return;

        var typeSelect = block.querySelector('.js-mb-discount-type');
        var discountInput = block.querySelector('.js-mb-discount');
        var suffix = block.querySelector('.js-mb-discount-suffix');
        if (!typeSelect || !discountInput || !suffix) return;

        var isPercent = typeSelect.value === 'percent';
        suffix.textContent = isPercent ? '%' : discountCurrency;
        if (isPercent) {
          discountInput.max = '100';
        } else {
          discountInput.removeAttribute('max');
        }
        updateBlockTotals(block);
      }

      function bindEmailAutocomplete(block) {
        var emailInput = block.querySelector('.js-mb-guest-email');
        var suggestBox = block.querySelector('.js-mb-email-suggest');
        if (!emailInput || !suggestBox || emailInput.dataset.bound === '1') return;

        emailInput.dataset.bound = '1';

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

      function initPhoneForBlock(block, selectedPhoneCode) {
        if (!CountryModule || typeof jQuery === 'undefined') return;

        var $ = jQuery;
        var index = block.getAttribute('data-index');
        var $phoneCode = $('#mb_phone_code_' + index);
        var phoneHidden = block.querySelector('.js-mb-phone-country-code');
        var $country = $('#mb_country_' + index);

        if (!$phoneCode.length) return;

        if ($phoneCode.hasClass('select2-hidden-accessible')) {
          $phoneCode.select2('destroy');
        }

        $phoneCode.empty();
        var countries = CountryModule.getAllCountries().sort(function (a, b) {
          return a.name.localeCompare(b.name);
        });

        countries.forEach(function (country) {
          var phone = '+' + country.phonecode;
          var selected = (selectedPhoneCode || defaultPhoneCode) === phone ? ' selected' : '';
          $phoneCode.append(
            '<option value="' + phone + '" data-flag="' + country.flag + '" data-iso="' + country.isoCode + '"' + selected + '>' + country.flag + ' ' + phone + '</option>'
          );
        });

        function formatPhoneCode(option) {
          if (!option.id) return option.text;
          var flag = $(option.element).data('flag') || '';
          return $('<span><span class="select2-flag">' + flag + '</span> ' + option.id + '</span>');
        }

        $phoneCode.select2({
          width: '108px',
          templateResult: formatPhoneCode,
          templateSelection: formatPhoneCode,
        });

        function syncPhoneHidden() {
          if (phoneHidden) {
            phoneHidden.value = $phoneCode.val() || '';
          }
        }

        $phoneCode.off('change.mbphone').on('change.mbphone', syncPhoneHidden);
        syncPhoneHidden();

        if ($country.length) {
          $country.off('change.mbphone').on('change.mbphone', function () {
            var phone = $('option:selected', $country).data('phone');
            if (!phone) return;
            $phoneCode.val(phone).trigger('change.select2');
            syncPhoneHidden();
          });
        }
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

      function updatePaymentProviderField(block) {
        if (!block) return;

        var paymentMode = block.querySelector('.js-mb-payment-mode');
        var providerWrap = block.querySelector('.js-mb-payment-provider-wrap');
        var providerLabel = block.querySelector('.js-mb-payment-provider-label');
        var providerSelect = block.querySelector('.js-mb-payment-provider');

        if (!paymentMode || !providerWrap || !providerSelect) return;

        var options = providerOptionsForMode(paymentMode.value);
        var modeValue = (paymentMode.value || '').toLowerCase();
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
        providerWrap.classList.toggle('d-none', !show);
        providerSelect.required = show;

        if (providerLabel) {
          var label = modeValue.indexOf('bank') !== -1 ? 'Bank' : 'Mobile Payment Provider';
          providerLabel.innerHTML = label + (show ? ' <span class="text-danger">*</span>' : '');
        }

        if (!show) {
          providerSelect.value = '';
        }
      }

      function planOptionsForRoom(roomId, selectedPlanId) {
        var room = roomData.find(function (item) { return String(item.id) === String(roomId); });
        if (!room) return '';
        return (room.rate_plans || []).map(function (plan) {
          var selected = String(plan.id) === String(selectedPlanId) ? ' selected' : '';
          return '<option value="' + plan.id + '" data-rate="' + plan.base_rate + '"' + selected + '>' + plan.label + '</option>';
        }).join('');
      }

      function otherBookings(currentBlock) {
        var others = [];
        if (!rowsWrap) return others;

        rowsWrap.querySelectorAll('.mb-booking-block').forEach(function (block) {
          if (block === currentBlock) return;
          others.push({
            checkin: block.querySelector('.js-mb-checkin')?.value || '',
            checkout: block.querySelector('.js-mb-checkout')?.value || '',
            hotel_room_id: parseInt(block.querySelector('.js-mb-room')?.value || '0', 10),
            room_count: parseInt(block.querySelector('.js-mb-room-count')?.value || '0', 10) || 0
          });
        });

        return others;
      }

      function checkAvailability(block) {
        if (!block) return;

        var msg = block.querySelector('.js-mb-availability-msg');
        var checkin = block.querySelector('.js-mb-checkin');
        var checkout = block.querySelector('.js-mb-checkout');
        var roomSelect = block.querySelector('.js-mb-room');
        var countInput = block.querySelector('.js-mb-room-count');

        if (!msg || !checkin || !checkout || !roomSelect || !countInput) return;

        var roomCount = parseInt(countInput.value || '0', 10);
        if (!checkin.value || !checkout.value || !roomSelect.value || roomCount < 1) {
          msg.textContent = '';
          msg.className = 'mb-availability-msg js-mb-availability-msg small';
          return;
        }

        msg.textContent = 'Checking availability...';
        msg.className = 'mb-availability-msg js-mb-availability-msg small';

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
            other_bookings: otherBookings(block)
          })
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            msg.textContent = data.message || '';
            msg.className = 'mb-availability-msg js-mb-availability-msg small ' + (data.ok ? 'is-ok' : 'is-bad');
          })
          .catch(function () {
            msg.textContent = 'Could not check availability.';
            msg.className = 'mb-availability-msg js-mb-availability-msg small is-bad';
          });
      }

      function scheduleAvailability(block) {
        clearTimeout(availabilityTimers.get(block));
        availabilityTimers.set(block, setTimeout(function () {
          checkAvailability(block);
        }, 350));
      }

      function initCountryCity(block, selectedCountryCode, selectedCity) {
        if (!CountryModule || !CityModule || typeof jQuery === 'undefined') return;

        var $ = jQuery;
        var index = block.getAttribute('data-index');
        var $country = $('#mb_country_' + index);
        var $city = $('#mb_city_' + index);
        var $countryName = block.querySelector('.js-mb-country-name');

        if (!$country.length) return;

        if ($country.hasClass('select2-hidden-accessible')) {
          $country.select2('destroy');
          $city.select2('destroy');
        }

        $country.empty().append('<option value="">Select country</option>');
        var countries = CountryModule.getAllCountries().sort(function (a, b) {
          return a.name.localeCompare(b.name);
        });

        countries.forEach(function (country) {
          var selected = (selectedCountryCode || defaultCountryCode) === country.isoCode ? ' selected' : '';
          var phone = '+' + country.phonecode;
          $country.append(
            '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-flag="' + country.flag + '" data-phone="' + phone + '"' + selected + '>' + country.name + '</option>'
          );
        });

        function formatCountry(option) {
          if (!option.id) return option.text;
          var flag = $(option.element).data('flag') || '';
          return $('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
        }

        function loadCities(countryCode, cityName) {
          $city.empty().append('<option value="">Select city</option>');
          if (!countryCode) return;

          var cities = CityModule.getCitiesOfCountry(countryCode) || [];
          var seen = {};
          cities.forEach(function (city) {
            if (seen[city.name]) return;
            seen[city.name] = true;
            var selected = cityName === city.name ? ' selected' : '';
            $city.append('<option value="' + city.name + '"' + selected + '>' + city.name + '</option>');
          });
        }

        function updateCountryName() {
          if ($countryName) {
            $countryName.value = $('option:selected', $country).data('name') || '';
          }
        }

        $country.select2({
          width: '100%',
          placeholder: 'Select country',
          templateResult: formatCountry,
          templateSelection: formatCountry,
        });
        $city.select2({ width: '100%', placeholder: 'Select city' });

        $country.off('change.mb').on('change.mb', function () {
          updateCountryName();
          loadCities($(this).val(), null);
          $city.val('').trigger('change.select2');
        });

        var initialCountry = selectedCountryCode || defaultCountryCode || $country.val();
        if (initialCountry) {
          $country.val(initialCountry).trigger('change.select2');
          updateCountryName();
          loadCities(initialCountry, selectedCity || '');
        }
      }

      function bindBlock(block) {
        var roomSelect = block.querySelector('.js-mb-room');
        var planSelect = block.querySelector('.js-mb-rate-plan');

        function syncPlans() {
          if (!roomSelect || !planSelect) return;
          var selected = planSelect.value;
          planSelect.innerHTML = planOptionsForRoom(roomSelect.value, selected);
          scheduleAvailability(block);
          updateBlockTotals(block);
        }

        if (roomSelect) roomSelect.addEventListener('change', syncPlans);
        if (planSelect) {
          planSelect.addEventListener('change', function () {
            updateBlockTotals(block);
          });
        }

        block.querySelectorAll('.js-mb-check-availability').forEach(function (input) {
          input.addEventListener('input', function () {
            scheduleAvailability(block);
            updateBlockTotals(block);
          });
          input.addEventListener('change', function () {
            scheduleAvailability(block);
            updateBlockTotals(block);
          });
        });

        block.querySelector('.js-mb-tax-inclusive')?.addEventListener('change', function () {
          updateBlockTotals(block);
        });

        block.querySelector('.js-mb-discount-type')?.addEventListener('change', function () {
          updateBlockDiscountField(block);
        });
        block.querySelector('.js-mb-discount')?.addEventListener('input', function () {
          updateBlockTotals(block);
        });
        block.querySelector('.js-mb-discount')?.addEventListener('change', function () {
          updateBlockTotals(block);
        });
        block.querySelector('.js-mb-advance-amount')?.addEventListener('input', function () {
          updateBlockTotals(block);
        });
        block.querySelector('.js-mb-advance-amount')?.addEventListener('change', function () {
          updateBlockTotals(block);
        });
        block.querySelector('.js-mb-discount-undo')?.addEventListener('click', function () {
          var discountInput = block.querySelector('.js-mb-discount');
          if (discountInput) discountInput.value = '0';
          updateBlockTotals(block);
        });

        block.querySelector('.js-mb-add-row')?.addEventListener('click', addRow);
        block.querySelector('.js-mb-remove-row')?.addEventListener('click', function () {
          block.remove();
          reindexRows();
          refreshAllAvailability();
        });

        var paymentMode = block.querySelector('.js-mb-payment-mode');
        if (paymentMode) {
          paymentMode.addEventListener('change', function () {
            updatePaymentProviderField(block);
          });
        }

        block.querySelector('.js-mb-guest-search')?.addEventListener('click', function () {
          activeGuestBlock = block;
          if (typeof jQuery !== 'undefined') {
            jQuery('#gbSelectGuestModal').modal('show');
          }
          var searchInput = document.getElementById('gbGuestSearch');
          if (searchInput) {
            searchInput.value = block.querySelector('.js-mb-guest-name')?.value.trim() || '';
            searchInput.dispatchEvent(new Event('input'));
          }
        });

        var countrySelect = block.querySelector('.js-mb-country');
        var citySelect = block.querySelector('.js-mb-city');
        var phoneHidden = block.querySelector('.js-mb-phone-country-code');
        initCountryCity(
          block,
          countrySelect?.value || defaultCountryCode,
          citySelect?.value || ''
        );
        initPhoneForBlock(block, phoneHidden?.value || defaultPhoneCode);
        bindEmailAutocomplete(block);

        syncPlans();
        updatePaymentProviderField(block);
        updateBlockDiscountField(block);
        updateBlockTotals(block);
      }

      function reindexRows() {
        if (!rowsWrap) return;

        rowsWrap.querySelectorAll('.mb-booking-block').forEach(function (block, index) {
          block.setAttribute('data-index', String(index));

          block.querySelectorAll('[name]').forEach(function (input) {
            input.name = input.name.replace(/bookings\[\d+\]/, 'bookings[' + index + ']');
          });

          var country = block.querySelector('.js-mb-country');
          var city = block.querySelector('.js-mb-city');
          var phoneCode = block.querySelector('.js-mb-phone-code');
          if (country) country.id = 'mb_country_' + index;
          if (city) city.id = 'mb_city_' + index;
          if (phoneCode) phoneCode.id = 'mb_phone_code_' + index;
        });

        rowIndex = rowsWrap.querySelectorAll('.mb-booking-block').length;

        if (CountryModule) {
          rowsWrap.querySelectorAll('.mb-booking-block').forEach(function (block) {
            var country = block.querySelector('.js-mb-country');
            var city = block.querySelector('.js-mb-city');
            var phoneHidden = block.querySelector('.js-mb-phone-country-code');
            initCountryCity(block, country?.value || defaultCountryCode, city?.value || '');
            initPhoneForBlock(block, phoneHidden?.value || defaultPhoneCode);
          });
        }
      }

      function refreshAllAvailability() {
        if (!rowsWrap) return;
        rowsWrap.querySelectorAll('.mb-booking-block').forEach(scheduleAvailability);
      }

      function addRow() {
        var template = document.getElementById('mbBookingRowTemplate');
        if (!template || !rowsWrap) return;

        var html = template.innerHTML.replace(/__INDEX__/g, String(rowIndex));
        var container = document.createElement('div');
        container.innerHTML = html.trim();
        var block = container.firstElementChild;
        rowsWrap.appendChild(block);
        bindBlock(block);
        rowIndex += 1;
      }

      window.fillGbGuest = function (guest) {
        if (!activeGuestBlock) return;
        var nameInput = activeGuestBlock.querySelector('.js-mb-guest-name');
        var emailInput = activeGuestBlock.querySelector('.js-mb-guest-email');
        var phoneInput = activeGuestBlock.querySelector('.js-mb-guest-phone');
        var phoneCodeSelect = activeGuestBlock.querySelector('.js-mb-phone-code');
        var phoneCodeHidden = activeGuestBlock.querySelector('.js-mb-phone-country-code');

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

        activeGuestBlock = null;
      };

      if (rowsWrap) {
        rowsWrap.querySelectorAll('.mb-booking-block').forEach(bindBlock);
      }

      var form = document.getElementById('mbForm');

      if (form) {
        form.addEventListener('submit', function () {
          var btn = form.querySelector('.js-mb-submit');
          if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';
          }
        });
      }

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          CountryModule = module.Country;
          CityModule = module.City;
          if (rowsWrap) {
            rowsWrap.querySelectorAll('.mb-booking-block').forEach(function (block) {
              var country = block.querySelector('.js-mb-country');
              var city = block.querySelector('.js-mb-city');
              var phoneHidden = block.querySelector('.js-mb-phone-country-code');
              initCountryCity(block, country?.value || defaultCountryCode, city?.value || '');
              initPhoneForBlock(block, phoneHidden?.value || defaultPhoneCode);
            });
          }
        })
        .catch(function () {});
    })();
  </script>
@endpush
