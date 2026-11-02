@extends('layouts.app')

@section('title', 'Complimentary Booking')

@push('styles')
  <style>
    .cp-page-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
    }

    .cp-page-head h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 500;
      color: #333;
    }

    .cp-section-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--brand, #940000);
      border-bottom: 1px solid #ddd;
      padding-bottom: 6px;
      margin: 0 0 14px;
    }

    .cp-info-banner {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      margin-bottom: 18px;
      background: #fef9c3;
      border: 1px solid #fde047;
      border-radius: 4px;
      color: #713f12;
      font-size: 13px;
    }

    .cp-info-banner i { color: #ca8a04; font-size: 18px; }

    .cp-availability-msg {
      min-height: 18px;
      padding-top: 4px;
      font-size: 12px;
    }

    .cp-availability-msg.is-ok { color: #28a745; }
    .cp-availability-msg.is-bad { color: #dc3545; }

    .cp-phone-group {
      display: flex;
      align-items: stretch;
    }

    .cp-phone-group .select2-container {
      width: 108px !important;
      flex: 0 0 108px;
    }

    .cp-phone-group .select2-container .select2-selection--single {
      height: 38px;
      border-radius: 4px 0 0 4px;
      border-right: 0;
      display: flex;
      align-items: center;
    }

    .cp-phone-group .js-cp-guest-phone {
      border-radius: 0 4px 4px 0;
      flex: 1 1 auto;
    }

    .cp-email-wrap { position: relative; }

    .cp-email-suggest {
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

    .cp-email-suggest.is-open { display: block; }

    .cp-email-suggest button {
      display: block;
      width: 100%;
      border: 0;
      background: #fff;
      text-align: left;
      padding: 7px 10px;
      font-size: 12px;
      cursor: pointer;
    }

    .cp-email-suggest button:hover { background: #f5f5f5; }

    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 6px; }
  </style>
@endpush

@section('content')
  @php
    $checkin = old('checkin', $options['default_checkin'] ?? now()->format('Y-m-d'));
    $checkout = old('checkout', $options['default_checkout'] ?? now()->addDay()->format('Y-m-d'));
    $roomId = (int) old('hotel_room_id', $options['default_room_id'] ?? 0);
    $planId = (int) old('hotel_rate_plan_id', $options['default_rate_plan_id'] ?? 0);
  @endphp

  <div class="cp-page-head">
    <h3><i class="fa fa-gift"></i> Complimentary Booking</h3>
    <a href="{{ route('hotel.reservations.index') }}" class="btn btn-primary btn-sm">Back</a>
  </div>

  <div class="tile">
    <div class="tile-body">
      <div class="cp-info-banner">
        <i class="fa fa-gift"></i>
        <span>Complimentary stays are recorded at <strong>zero charge</strong>. Room rate is kept internally for reference only.</span>
      </div>

      <form action="{{ route('hotel.reservations.complimentary.store') }}" method="POST" id="cpForm">
        @csrf

        <div class="cp-section-title">Stay details</div>
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Check-in <span class="text-danger">*</span></label>
              <input type="date" class="form-control js-cp-check-availability" name="checkin"
                value="{{ $checkin }}" required>
              @error('checkin')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Check-out <span class="text-danger">*</span></label>
              <input type="date" class="form-control js-cp-check-availability" name="checkout"
                value="{{ $checkout }}" required>
              @error('checkout')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Room Type <span class="text-danger">*</span></label>
              <select class="form-control js-cp-room js-cp-check-availability" name="hotel_room_id" required>
                @foreach($options['rooms'] as $room)
                  <option value="{{ $room['id'] }}" {{ (int) $room['id'] === $roomId ? 'selected' : '' }}>
                    {{ $room['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Rate Plan <span class="text-danger">*</span></label>
              <select class="form-control js-cp-rate-plan" name="hotel_rate_plan_id" required>
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
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label"># Rooms <span class="text-danger">*</span></label>
              <input type="number" min="1" max="50" step="1" class="form-control js-cp-room-count js-cp-check-availability"
                name="room_count" value="{{ old('room_count', 1) }}" required>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label"># Guests <span class="text-danger">*</span></label>
              <select class="form-control" name="guest_count" required>
                @for($i = 1; $i <= 8; $i++)
                  <option value="{{ $i }}" {{ (int) old('guest_count', 1) === $i ? 'selected' : '' }}>{{ $i }}</option>
                @endfor
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="cp-availability-msg js-cp-availability-msg"></div>
          </div>
        </div>

        <div class="cp-section-title">Guest details</div>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control js-cp-guest-name" name="guest_name"
                  value="{{ old('guest_name') }}" required>
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-cp-guest-search" title="Search guest">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
              @error('guest_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Email</label>
              <div class="cp-email-wrap">
                <input type="email" class="form-control js-cp-guest-email" name="guest_email"
                  value="{{ old('guest_email') }}" placeholder="Enter email" autocomplete="off">
                <div class="cp-email-suggest js-cp-email-suggest" role="listbox"></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Phone</label>
              <div class="cp-phone-group">
                <select class="js-cp-phone-code" id="cp_phone_code" aria-label="Phone country code"></select>
                <input type="hidden" class="js-cp-phone-country-code" name="guest_phone_country_code"
                  value="{{ old('guest_phone_country_code', $options['default_phone_code'] ?? '+255') }}">
                <input type="text" class="form-control js-cp-guest-phone" name="guest_phone"
                  value="{{ old('guest_phone') }}" placeholder="Phone number">
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Country</label>
              <select class="form-control js-cp-country" id="cp_country" name="guest_country_code">
                <option value="">Select country</option>
              </select>
              <input type="hidden" class="js-cp-country-name" name="guest_country"
                value="{{ old('guest_country', $options['default_country'] ?? '') }}">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">City</label>
              <select class="form-control js-cp-city" id="cp_city" name="guest_city">
                <option value="">Select city</option>
                @if(old('guest_city'))
                  <option value="{{ old('guest_city') }}" selected>{{ old('guest_city') }}</option>
                @endif
              </select>
            </div>
          </div>
        </div>

        <div class="cp-section-title">Complimentary details</div>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Reason <span class="text-danger">*</span></label>
              <select class="form-control" name="complimentary_reason" required>
                <option value="">Select reason</option>
                @foreach($reasons as $key => $label)
                  <option value="{{ $key }}" {{ old('complimentary_reason') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              @error('complimentary_reason')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-8">
            <div class="form-group">
              <label class="control-label">Notes</label>
              <input type="text" class="form-control" name="special_request" value="{{ old('special_request') }}"
                placeholder="Optional notes for audit trail">
            </div>
          </div>
        </div>

        <div class="text-right mt-3 pt-3 border-top">
          <button type="submit" class="btn btn-primary btn-lg js-cp-submit" style="background:#940000;border-color:#940000;">
            <i class="fa fa-gift"></i> Create Complimentary Booking
          </button>
        </div>
      </form>
    </div>
  </div>

  @include('hotel.pms.reservations.partials._guest-search-modal')

  <script type="application/json" id="cp-room-data">@json($options['rooms'])</script>
  <script type="application/json" id="cp-default-country">@json($options['default_country_code'] ?? '')</script>
  <script type="application/json" id="cp-default-phone-code">@json($options['default_phone_code'] ?? '+255')</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var roomData = [];
      var defaultCountryCode = '';
      var defaultPhoneCode = '+255';
      var availabilityUrl = @json(route('hotel.reservations.complimentary.check-availability'));
      var csrf = @json(csrf_token());
      var availabilityTimer = null;
      var CountryModule = null;
      var CityModule = null;
      var emailDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'live.com', 'aol.com', 'protonmail.com', 'mail.com', 'zoho.com'
      ];

      try {
        roomData = JSON.parse(document.getElementById('cp-room-data').textContent || '[]');
        defaultCountryCode = JSON.parse(document.getElementById('cp-default-country').textContent || '""');
        defaultPhoneCode = JSON.parse(document.getElementById('cp-default-phone-code').textContent || '"+255"');
      } catch (e) {}

      function planOptionsForRoom(roomId, selectedId) {
        var room = roomData.find(function (item) { return String(item.id) === String(roomId); });
        if (!room) return '<option value="">Select rate plan</option>';

        return room.rate_plans.map(function (plan) {
          var selected = String(plan.id) === String(selectedId) ? ' selected' : '';
          return '<option value="' + plan.id + '" data-rate="' + plan.base_rate + '"' + selected + '>' + plan.label + '</option>';
        }).join('');
      }

      function scheduleAvailability() {
        clearTimeout(availabilityTimer);
        availabilityTimer = setTimeout(checkAvailability, 350);
      }

      function checkAvailability() {
        var msg = document.querySelector('.js-cp-availability-msg');
        var checkin = document.querySelector('[name="checkin"]');
        var checkout = document.querySelector('[name="checkout"]');
        var roomSelect = document.querySelector('.js-cp-room');
        var countInput = document.querySelector('.js-cp-room-count');

        if (!msg || !checkin || !checkout || !roomSelect || !countInput) return;

        var roomCount = parseInt(countInput.value || '0', 10);
        if (!checkin.value || !checkout.value || !roomSelect.value || roomCount < 1) {
          msg.textContent = '';
          msg.className = 'cp-availability-msg js-cp-availability-msg';
          return;
        }

        msg.textContent = 'Checking availability...';
        msg.className = 'cp-availability-msg js-cp-availability-msg';

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
            room_count: roomCount
          })
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            msg.textContent = data.message || '';
            msg.className = 'cp-availability-msg js-cp-availability-msg ' + (data.ok ? 'is-ok' : 'is-bad');
          })
          .catch(function () {
            msg.textContent = 'Could not check availability.';
            msg.className = 'cp-availability-msg js-cp-availability-msg is-bad';
          });
      }

      function bindEmailAutocomplete() {
        var emailInput = document.querySelector('.js-cp-guest-email');
        var suggestBox = document.querySelector('.js-cp-email-suggest');
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

      function initPhoneForBlock(selectedCode) {
        var phoneSelect = document.querySelector('.js-cp-phone-code');
        var phoneHidden = document.querySelector('.js-cp-phone-country-code');
        if (!phoneSelect || typeof jQuery === 'undefined' || !CountryModule) return;

        var $phone = jQuery(phoneSelect);
        if ($phone.data('select2')) {
          $phone.select2('destroy');
        }

        $phone.empty();
        CountryModule.getAllCountries().sort(function (a, b) {
          return a.name.localeCompare(b.name);
        }).forEach(function (country) {
          var phone = '+' + country.phonecode;
          var selected = (selectedCode || defaultPhoneCode) === phone ? ' selected' : '';
          $phone.append('<option value="' + phone + '" data-flag="' + country.flag + '"' + selected + '>' + phone + '</option>');
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

        $phone.off('change.cp').on('change.cp', function () {
          if (phoneHidden) phoneHidden.value = $phone.val() || '';
        });

        var initial = selectedCode || defaultPhoneCode;
        if (initial) {
          $phone.val(initial).trigger('change.select2');
          if (phoneHidden) phoneHidden.value = initial;
        }
      }

      function initCountryCity(selectedCountryCode, selectedCity) {
        var countrySelect = document.querySelector('.js-cp-country');
        var citySelect = document.querySelector('.js-cp-city');
        var countryNameInput = document.querySelector('.js-cp-country-name');
        if (!countrySelect || !citySelect || typeof jQuery === 'undefined' || !CountryModule || !CityModule) return;

        var $country = jQuery(countrySelect);
        var $city = jQuery(citySelect);

        if ($country.data('select2')) $country.select2('destroy');
        if ($city.data('select2')) $city.select2('destroy');

        $country.empty().append('<option value="">Select country</option>');
        CountryModule.getAllCountries().sort(function (a, b) {
          return a.name.localeCompare(b.name);
        }).forEach(function (country) {
          var selected = (selectedCountryCode || defaultCountryCode) === country.isoCode ? ' selected' : '';
          $country.append(
            '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-flag="' + country.flag + '"' + selected + '>' + country.name + '</option>'
          );
        });

        function formatCountry(option) {
          if (!option.id) return option.text;
          var flag = jQuery(option.element).data('flag') || '';
          return jQuery('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
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

        $country.off('change.cp').on('change.cp', function () {
          if (countryNameInput) {
            countryNameInput.value = jQuery('option:selected', $country).data('name') || '';
          }
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

      function syncPlans() {
        var roomSelect = document.querySelector('.js-cp-room');
        var planSelect = document.querySelector('.js-cp-rate-plan');
        if (!roomSelect || !planSelect) return;

        var selected = planSelect.value;
        planSelect.innerHTML = planOptionsForRoom(roomSelect.value, selected);
        scheduleAvailability();
      }

      document.querySelector('.js-cp-room')?.addEventListener('change', syncPlans);
      document.querySelectorAll('.js-cp-check-availability').forEach(function (input) {
        input.addEventListener('input', scheduleAvailability);
        input.addEventListener('change', scheduleAvailability);
      });

      document.querySelector('.js-cp-guest-search')?.addEventListener('click', function () {
        if (typeof jQuery !== 'undefined') {
          jQuery('#gbSelectGuestModal').modal('show');
        }
        var searchInput = document.getElementById('gbGuestSearch');
        if (searchInput) {
          searchInput.value = document.querySelector('.js-cp-guest-name')?.value.trim() || '';
          searchInput.dispatchEvent(new Event('input'));
        }
      });

      window.fillGbGuest = function (guest) {
        var nameInput = document.querySelector('.js-cp-guest-name');
        var emailInput = document.querySelector('.js-cp-guest-email');
        var phoneInput = document.querySelector('.js-cp-guest-phone');
        var phoneCodeSelect = document.querySelector('.js-cp-phone-code');
        var phoneCodeHidden = document.querySelector('.js-cp-phone-country-code');

        if (nameInput) nameInput.value = guest.name || '';
        if (emailInput) emailInput.value = guest.email || '';

        if (phoneInput && phoneCodeSelect) {
          var phone = String(guest.phone || '').trim();
          var matchedCode = '';
          if (phone.indexOf('+') === 0) {
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
      };

      bindEmailAutocomplete();
      syncPlans();
      scheduleAvailability();

      document.getElementById('cpForm')?.addEventListener('submit', function () {
        var btn = document.querySelector('.js-cp-submit');
        if (btn && !btn.disabled) {
          btn.disabled = true;
          btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';
        }
      });

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          CountryModule = module.Country;
          CityModule = module.City;
          initCountryCity(
            document.querySelector('.js-cp-country')?.value || defaultCountryCode,
            document.querySelector('.js-cp-city')?.value || ''
          );
          initPhoneForBlock(document.querySelector('.js-cp-phone-country-code')?.value || defaultPhoneCode);
        })
        .catch(function () {});
    })();
  </script>
@endpush
