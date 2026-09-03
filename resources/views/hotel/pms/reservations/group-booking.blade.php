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
      background: #009688;
      color: #fff;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
    }

    .gb-add-row:hover { background: #00796b; }

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
      color: #009688;
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

    .gb-discount-wrap .input-group { max-width: 120px; }

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
  @endphp

  <div class="gb-page-head">
    <h3>Group Bookings</h3>
    <a href="{{ route('hotel.reservations.index') }}" class="btn btn-primary btn-sm">Back</a>
  </div>

  <div class="tile">
    <div class="tile-body">
      <form action="{{ route('hotel.reservations.group-booking.store') }}" method="POST" enctype="multipart/form-data" id="gbForm">
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

        @error('lines')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Payment Mode</label>
              <select class="form-control @error('payment_mode') is-invalid @enderror" name="payment_mode">
                @foreach($options['payment_modes'] as $mode)
                  <option value="{{ $mode }}" {{ old('payment_mode', $options['default_payment_mode'] ?? '') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Bill To</label>
              <div class="input-group">
                <input type="text" class="form-control js-res-bill-to @error('bill_to') is-invalid @enderror" name="bill_to"
                  value="{{ old('bill_to') }}" readonly>
                <input type="hidden" name="bill_to_company_id" class="js-res-bill-to-company-id" value="{{ old('bill_to_company_id') }}">
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-res-bill-to-search" title="Select company">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="control-label">Channel</label>
              <input type="text" class="form-control" value="PMS" readonly>
              <small class="text-muted">Bookings created in the hotel are sourced from PMS.</small>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">Booked By</label>
              <input type="text" class="form-control" name="booked_by" value="{{ old('booked_by') }}">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">Guest Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control js-gb-guest-name @error('guest_name') is-invalid @enderror" name="guest_name"
                  value="{{ old('guest_name') }}" placeholder="Enter guest name" required>
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary js-gb-guest-search" title="Search guest">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
              @error('guest_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">Email</label>
              <input type="email" class="form-control js-gb-guest-email" name="guest_email" value="{{ old('guest_email') }}" placeholder="Enter email">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label class="control-label">Phone</label>
              <input type="text" class="form-control js-gb-guest-phone" name="guest_phone" value="{{ old('guest_phone') }}" placeholder="Enter phone no.">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group gb-select2-host">
              <label class="control-label">City</label>
              <select class="form-control" id="gb_guest_city" name="guest_city">
                <option value="">Select city</option>
              </select>
            </div>
          </div>
          <div class="col-md-2">
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
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Discount Value</label>
              <div class="gb-discount-wrap">
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" class="form-control js-gb-discount" name="discount_percent"
                    value="{{ old('discount_percent', 0) }}">
                  <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
                <button type="button" class="btn btn-primary btn-sm js-gb-discount-apply">Apply</button>
                <button type="button" class="btn btn-primary btn-sm js-gb-discount-undo">Undo</button>
              </div>
            </div>
          </div>
        </div>

        <div class="gb-section-title">Advance Payment Info</div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Amount Paid</label>
              <input type="number" step="0.01" min="0" class="form-control" name="advance_amount" value="{{ old('advance_amount', 0) }}">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Payment Mode</label>
              <select class="form-control" name="advance_payment_mode">
                @foreach($options['payment_modes'] as $mode)
                  <option value="{{ $mode }}" {{ old('advance_payment_mode', $options['default_payment_mode'] ?? '') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Comments</label>
              <input type="text" class="form-control" name="advance_comments" value="{{ old('advance_comments') }}">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="control-label">Attachment</label>
              <div class="input-group">
                <input type="file" class="form-control" name="advance_attachment" id="gbAdvanceAttachment">
                <div class="input-group-append">
                  <label for="gbAdvanceAttachment" class="btn btn-secondary mb-0">BROWSE</label>
                </div>
              </div>
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
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var roomData = [];
      try {
        roomData = JSON.parse(document.getElementById('gb-room-data').textContent || '[]');
      } catch (e) {
        roomData = [];
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
          if (!roomSelect || !countInput) return;
          others.push({
            hotel_room_id: parseInt(roomSelect.value || '0', 10),
            room_count: parseInt(countInput.value || '0', 10) || 0
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
          scheduleAvailability(wrap);
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

        if (countInput) {
          countInput.addEventListener('input', function () { scheduleAvailability(wrap); });
          countInput.addEventListener('change', function () { scheduleAvailability(wrap); });
        }

        if (removeBtn) {
          removeBtn.addEventListener('click', function () {
            wrap.remove();
            reindexRows();
            refreshAllAvailability();
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
      });

      if (checkin) checkin.addEventListener('change', refreshAllAvailability);
      if (checkout) checkout.addEventListener('change', refreshAllAvailability);

      document.querySelector('.js-gb-discount-apply')?.addEventListener('click', function () {});
      document.querySelector('.js-gb-discount-undo')?.addEventListener('click', function () {
        var discountInput = document.querySelector('.js-gb-discount');
        if (discountInput) discountInput.value = '0';
      });

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

          countries.forEach(function (country) {
            var selected = oldCountryCode === country.isoCode ? ' selected' : '';
            $country.append(
              '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-flag="' + country.flag + '"' + selected + '>' + country.name + '</option>'
            );
          });

          function formatCountry(option) {
            if (!option.id) return option.text;
            var flag = $(option.element).data('flag') || '';
            return $('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
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

          $country.select2({
            width: '100%',
            placeholder: 'Select country',
            templateResult: formatCountry,
            templateSelection: formatCountry,
          });
          $city.select2({ width: '100%', placeholder: 'Select city' });

          $country.on('change', function () {
            updateCountryName();
            loadCities($(this).val(), null);
            $city.val('').trigger('change.select2');
          });

          if (oldCountryCode) {
            updateCountryName();
            loadCities(oldCountryCode, oldCity);
          }
        });
      }

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          bootLocationSelects(module.Country, module.City);
        })
        .catch(function () {});

      refreshAllAvailability();
    })();
  </script>
@endpush
