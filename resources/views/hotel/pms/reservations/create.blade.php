@extends('layouts.app')

@section('title', 'Create Reservation')

@push('styles')
  <style>
    .res-create-form.is-init {
      visibility: hidden;
    }

    .res-create-form.is-ready {
      visibility: visible;
    }

    .res-select2-host {
      min-height: 38px;
    }

    .res-select2-host:not(.is-ready) select {
      opacity: 0;
      pointer-events: none;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-plus"></i> Create Reservation</h1>
      <p>New PMS booking — guest and stay details</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.reservations.index') }}">Reservation Data</a></li>
      <li class="breadcrumb-item">Create</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Booking</h3>
        <div class="tile-body">
          <form action="{{ route('hotel.reservations.store') }}" method="POST" enctype="multipart/form-data"
            id="resCreateForm" class="res-create-form is-init">
            @csrf
            @include('hotel.pms.reservations.partials._form-fields')

            <div class="tile-footer mt-4 pt-3 border-top">
              <button class="btn btn-primary btn-lg js-res-create-submit" type="submit">
                <i class="fa fa-check-circle"></i> Create Reservation
              </button>
              <a class="btn btn-secondary" href="{{ route('hotel.reservations.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @include('hotel.pms.reservations.partials._company-modals')
@endsection

@include('hotel.pms.reservations.partials._form-scripts')

@push('scripts')
  <script>
    window.resCreateMarkReady = function () {
      var form = document.getElementById('resCreateForm');
      if (!form) return;
      form.classList.remove('is-init');
      form.classList.add('is-ready');
    };
  </script>
@endpush

@push('scripts')
  <script>
    (function () {
      var checkin = document.querySelector('.js-res-checkin');
      var checkout = document.querySelector('.js-res-checkout');
      var nightsInput = document.querySelector('.js-res-nights');
      var roomSelect = document.querySelector('.js-res-room');
      var planSelect = document.querySelector('.js-res-rate-plan');
      var unitSelect = document.querySelector('.js-res-room-unit');
      var dailyRate = document.querySelector('.js-res-daily-rate');
      var dailyTax = document.querySelector('.js-res-daily-tax');
      var taxInclusive = document.querySelector('.js-res-tax-inclusive');
      var roomCountSelect = document.querySelector('.js-res-room-count');
      var guestTypeSelect = document.querySelector('.js-res-guest-type');
      var currencyLabels = document.querySelectorAll('.js-res-currency-label');
      var paymentMode = document.querySelector('.js-res-payment-mode');
      var paymentProviderWrap = document.querySelector('.js-res-payment-provider-wrap');
      var paymentProviderLabel = document.querySelector('.js-res-payment-provider-label');
      var paymentProvider = document.querySelector('.js-res-payment-provider');
      var pricingConfig = { vat_rate: 18 };
      var paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
      var oldPaymentProvider = '';

      try {
        var pricingEl = document.getElementById('res-pricing-config');
        if (pricingEl) {
          pricingConfig = JSON.parse(pricingEl.textContent);
        }
      } catch (e) {}

      try {
        var providerConfigEl = document.getElementById('res-payment-provider-config');
        if (providerConfigEl) {
          paymentProviderConfig = JSON.parse(providerConfigEl.textContent);
        }
      } catch (e) {}

      try {
        var oldProviderEl = document.getElementById('res-old-payment-provider');
        if (oldProviderEl) {
          oldPaymentProvider = JSON.parse(oldProviderEl.textContent) || '';
        }
      } catch (e) {}

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

      function updatePaymentProviderField() {
        if (!paymentMode || !paymentProviderWrap || !paymentProvider) {
          return;
        }

        var options = providerOptionsForMode(paymentMode.value);
        var modeValue = (paymentMode.value || '').toLowerCase();
        var previous = paymentProvider.value || oldPaymentProvider;

        paymentProvider.innerHTML = '<option value="">Select provider</option>';
        options.forEach(function (name) {
          var option = document.createElement('option');
          option.value = name;
          option.textContent = name;
          if (previous && previous === name) {
            option.selected = true;
          }
          paymentProvider.appendChild(option);
        });

        oldPaymentProvider = '';

        var show = options.length > 0;
        paymentProviderWrap.classList.toggle('d-none', !show);
        paymentProvider.required = show;

        if (paymentProviderLabel) {
          var label = modeValue.indexOf('bank') !== -1 ? 'Bank' : 'Mobile Payment Provider';
          paymentProviderLabel.innerHTML = label + (show ? ' <span class="text-danger">*</span>' : '');
        }

        if (!show) {
          paymentProvider.value = '';
        }
      }

      function formatMoney(value) {
        var amount = Number(value || 0);
        return amount.toLocaleString(undefined, {
          minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
          maximumFractionDigits: 2,
        });
      }

      function calculateCharges() {
        var rate = parseFloat(dailyRate && dailyRate.value ? dailyRate.value : '0') || 0;
        var nights = parseInt(nightsInput && nightsInput.value ? nightsInput.value : '1', 10) || 1;
        var rooms = parseInt(roomCountSelect && roomCountSelect.value ? roomCountSelect.value : '1', 10) || 1;
        var inclusive = !!(taxInclusive && taxInclusive.checked);
        var vatRate = Number(pricingConfig.vat_rate || 0);
        var multiplier = Math.max(1, nights) * Math.max(1, rooms);
        var taxPerNight = 0;
        var netPerNight = rate;

        if (inclusive) {
          taxPerNight = vatRate > 0 ? Math.round((rate * (vatRate / (100 + vatRate))) * 100) / 100 : 0;
          netPerNight = Math.round((rate - taxPerNight) * 100) / 100;
        } else {
          var manualTax = dailyTax && dailyTax.dataset.manualOverride === '1'
            ? (parseFloat(dailyTax.value || '0') || 0)
            : null;
          taxPerNight = manualTax !== null
            ? manualTax
            : (vatRate > 0 ? Math.round((rate * vatRate / 100) * 100) / 100 : 0);
          if (dailyTax && dailyTax.dataset.manualOverride !== '1') {
            dailyTax.value = taxPerNight.toFixed(2);
          }
          netPerNight = rate;
        }

        if (dailyTax) {
          dailyTax.readOnly = inclusive;
          if (inclusive) {
            dailyTax.value = taxPerNight.toFixed(2);
          }
        }

        var totalNet = Math.round(netPerNight * multiplier * 100) / 100;
        var totalVat = Math.round(taxPerNight * multiplier * 100) / 100;
        var totalGross = Math.round((totalNet + totalVat) * 100) / 100;

        document.querySelectorAll('.js-res-total-gross').forEach(function (el) {
          el.textContent = formatMoney(totalGross);
        });
        document.querySelectorAll('.js-res-total-net').forEach(function (el) {
          el.textContent = formatMoney(totalNet);
        });
        document.querySelectorAll('.js-res-total-vat').forEach(function (el) {
          el.textContent = formatMoney(totalVat);
        });
        document.querySelectorAll('.js-res-vat-rate').forEach(function (el) {
          el.textContent = Number(vatRate).toFixed(2);
        });

        var hint = document.querySelector('.js-res-daily-tax-hint');
        if (hint) {
          hint.textContent = inclusive
            ? 'VAT portion of the inclusive nightly rate.'
            : 'Auto from settings VAT rate. Edit to override.';
        }
      }

      function parseDate(value) {
        if (!value) return null;
        var parts = value.split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
      }

      function updateNights() {
        if (!checkin || !checkout || !nightsInput) return;
        var start = parseDate(checkin.value);
        var end = parseDate(checkout.value);
        if (!start || !end || end <= start) {
          nightsInput.value = '0';
          return;
        }
        var diff = Math.round((end - start) / (1000 * 60 * 60 * 24));
        nightsInput.value = String(Math.max(1, diff));
      }

      function filterSelect(select, roomId, keepValue) {
        if (!select) return;
        var hasVisibleSelected = false;
        Array.prototype.forEach.call(select.options, function (option) {
          var match = !option.dataset.roomId || String(option.dataset.roomId) === String(roomId);
          option.hidden = !match;
          option.disabled = !match;
          if (match && option.value === keepValue) {
            hasVisibleSelected = true;
          }
        });
        if (!hasVisibleSelected) {
          var first = Array.prototype.find.call(select.options, function (option) {
            return !option.hidden && option.value;
          });
          if (first) select.value = first.value;
        }
      }

      function buildRatePlanMap() {
        var el = document.getElementById('res-room-data');
        if (!el) return {};
        try {
          var rooms = JSON.parse(el.textContent);
          var map = {};
          rooms.forEach(function (room) {
            (room.rate_plans || []).forEach(function (plan) {
              map[String(plan.id)] = {
                international: {
                  rate: plan.base_rate,
                  currency: plan.international_currency,
                },
                local: {
                  rate: plan.local_rate,
                  currency: plan.local_currency,
                },
              };
            });
          });
          return map;
        } catch (e) {
          return {};
        }
      }

      var ratePlanMap = buildRatePlanMap();

      function applyRateForGuestType() {
        if (!planSelect || !dailyRate) return;
        var planId = planSelect.value;
        if (!planId) return;

        var guestType = guestTypeSelect && guestTypeSelect.value === 'local' ? 'local' : 'international';
        var planRates = ratePlanMap[planId];
        var rate;
        var currency;

        if (planRates && planRates[guestType]) {
          rate = planRates[guestType].rate;
          currency = planRates[guestType].currency;
        } else {
          var selectedPlan = planSelect.options[planSelect.selectedIndex];
          if (!selectedPlan) return;
          var isLocal = guestType === 'local';
          rate = isLocal
            ? selectedPlan.getAttribute('data-local-rate')
            : selectedPlan.getAttribute('data-rate');
          currency = isLocal
            ? selectedPlan.getAttribute('data-local-currency')
            : selectedPlan.getAttribute('data-intl-currency');
        }

        if (rate !== null && rate !== undefined && rate !== '') {
          dailyRate.value = rate;
        }
        currencyLabels.forEach(function (label) {
          if (currency) {
            label.textContent = currency;
          }
        });
        calculateCharges();
      }

      window.resApplyGuestTypeRate = applyRateForGuestType;

      function syncRoomDependents() {
        if (!roomSelect) return;
        var roomId = roomSelect.value;
        filterSelect(planSelect, roomId, planSelect ? planSelect.value : '');
        filterSelect(unitSelect, roomId, unitSelect ? unitSelect.value : '');
        applyRateForGuestType();
      }

      if (checkin) checkin.addEventListener('change', function () { updateNights(); calculateCharges(); });
      if (checkout) checkout.addEventListener('change', function () { updateNights(); calculateCharges(); });
      if (roomSelect) roomSelect.addEventListener('change', syncRoomDependents);
      if (planSelect) planSelect.addEventListener('change', applyRateForGuestType);
      if (guestTypeSelect) guestTypeSelect.addEventListener('change', applyRateForGuestType);
      if (dailyRate) dailyRate.addEventListener('input', calculateCharges);
      if (roomCountSelect) roomCountSelect.addEventListener('change', calculateCharges);
      if (taxInclusive) taxInclusive.addEventListener('change', function () {
        if (dailyTax) {
          dailyTax.dataset.manualOverride = '0';
        }
        calculateCharges();
      });
      if (dailyTax) {
        dailyTax.addEventListener('input', function () {
          if (!(taxInclusive && taxInclusive.checked)) {
            dailyTax.dataset.manualOverride = '1';
          }
          calculateCharges();
        });
      }
      if (paymentMode) {
        paymentMode.addEventListener('change', updatePaymentProviderField);
      }

      var photoInput = document.getElementById('photo_id');
      if (photoInput) {
        photoInput.addEventListener('change', function () {
          var label = photoInput.nextElementSibling;
          if (label) {
            label.textContent = photoInput.files && photoInput.files[0] ? photoInput.files[0].name : 'Upload';
          }
        });
      }

      window.resCreateSyncRates = function () {
        syncRoomDependents();
        updateNights();
        calculateCharges();
      };

      calculateCharges();
      updatePaymentProviderField();

      var createForm = document.getElementById('resCreateForm');
      if (createForm) {
        createForm.addEventListener('submit', function () {
          var submitBtn = createForm.querySelector('.js-res-create-submit');
          if (!submitBtn || submitBtn.disabled) {
            return;
          }
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';
        });
      }
    })();
  </script>
@endpush
