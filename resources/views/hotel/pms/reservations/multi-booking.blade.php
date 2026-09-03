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

    .mb-booking-block__row--bottom {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid #e0e0e0;
    }

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
      display: flex;
      justify-content: flex-end;
      margin-top: 8px;
    }

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
      'daily_rate' => $options['default_rate'] ?? 0,
      'tax_inclusive' => false,
      'payment_mode' => $options['default_payment_mode'] ?? 'Cash',
      'guest_country_code' => $options['default_country_code'] ?? '',
      'guest_country' => $options['default_country'] ?? '',
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

        <div class="mb-footer">
          <button type="submit" class="btn btn-info btn-lg js-mb-submit">Submit</button>
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
        'daily_rate' => $options['default_rate'] ?? 0,
        'tax_inclusive' => false,
        'payment_mode' => $options['default_payment_mode'] ?? 'Cash',
        'guest_country_code' => $options['default_country_code'] ?? '',
        'guest_country' => $options['default_country'] ?? '',
      ],
      'options' => $options,
      'showRemove' => true,
    ])
  </template>

  <script type="application/json" id="mb-room-data">@json($options['rooms'])</script>
  <script type="application/json" id="mb-default-country">@json($options['default_country_code'] ?? '')</script>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var roomData = [];
      var defaultCountryCode = '';
      var rowsWrap = document.getElementById('mbBookingRows');
      var rowIndex = rowsWrap ? rowsWrap.querySelectorAll('.mb-booking-block').length : 0;
      var availabilityUrl = @json(route('hotel.reservations.multi-booking.check-availability'));
      var csrf = @json(csrf_token());
      var availabilityTimers = new WeakMap();
      var CountryModule = null;
      var CityModule = null;
      var activeGuestBlock = null;

      try {
        roomData = JSON.parse(document.getElementById('mb-room-data').textContent || '[]');
        defaultCountryCode = JSON.parse(document.getElementById('mb-default-country').textContent || '""');
      } catch (e) {}

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
          $country.append(
            '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-flag="' + country.flag + '"' + selected + '>' + country.name + '</option>'
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
        var rateInput = block.querySelector('.js-mb-daily-rate');

        function syncPlans() {
          if (!roomSelect || !planSelect) return;
          var selected = planSelect.value;
          planSelect.innerHTML = planOptionsForRoom(roomSelect.value, selected);
          var option = planSelect.options[planSelect.selectedIndex];
          if (option && rateInput) {
            rateInput.value = option.getAttribute('data-rate') || rateInput.value;
          }
          scheduleAvailability(block);
        }

        if (roomSelect) roomSelect.addEventListener('change', syncPlans);
        if (planSelect) {
          planSelect.addEventListener('change', function () {
            var option = planSelect.options[planSelect.selectedIndex];
            if (option && rateInput) {
              rateInput.value = option.getAttribute('data-rate') || '0';
            }
          });
        }

        block.querySelectorAll('.js-mb-check-availability').forEach(function (input) {
          input.addEventListener('input', function () { scheduleAvailability(block); });
          input.addEventListener('change', function () { scheduleAvailability(block); });
        });

        block.querySelector('.js-mb-add-row')?.addEventListener('click', addRow);
        block.querySelector('.js-mb-remove-row')?.addEventListener('click', function () {
          block.remove();
          reindexRows();
          refreshAllAvailability();
        });

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
        initCountryCity(
          block,
          countrySelect?.value || defaultCountryCode,
          citySelect?.value || ''
        );

        syncPlans();
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
          if (country) country.id = 'mb_country_' + index;
          if (city) city.id = 'mb_city_' + index;
        });

        rowIndex = rowsWrap.querySelectorAll('.mb-booking-block').length;
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
        if (nameInput) nameInput.value = guest.name || '';
        if (emailInput) emailInput.value = guest.email || '';
        if (phoneInput) phoneInput.value = guest.phone || '';
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
            btn.textContent = 'Submitting...';
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
              initCountryCity(block, country?.value || defaultCountryCode, city?.value || '');
            });
          }
        })
        .catch(function () {});
    })();
  </script>
@endpush
