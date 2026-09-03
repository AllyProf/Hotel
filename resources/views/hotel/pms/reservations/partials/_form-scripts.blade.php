@php
  $selectedCountryCode = old('guest_country_code', $options['default_country_code'] ?? '');
  $selectedCity = old('guest_city', '');
@endphp

@push('styles')
  <style>
    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 6px; }
  </style>
@endpush

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      var oldCountryCode = @json($selectedCountryCode ?? '');
      var oldCity = @json($selectedCity ?? '');
      var hotelCountryCode = @json($options['default_country_code'] ?? '');

      function markSelect2Ready() {
        document.querySelectorAll('.res-select2-host').forEach(function (host) {
          host.classList.add('is-ready');
        });
      }

      function finishCreateInit() {
        markSelect2Ready();
        if (typeof window.resCreateSyncRates === 'function') {
          window.resCreateSyncRates();
        }
        if (typeof window.resCreateMarkReady === 'function') {
          window.resCreateMarkReady();
        }
      }

      function bootLocationSelects(Country, City) {
        jQuery(function ($) {
          var countries = Country.getAllCountries().sort(function (a, b) {
            return a.name.localeCompare(b.name);
          });
          var $country = $('#res_guest_country_code');
          var $city = $('#res_guest_city');
          var $countryName = $('#res_guest_country_name');
          var $guestType = $('.js-res-guest-type');

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

            if (!countryCode) {
              return;
            }

            var cities = City.getCitiesOfCountry(countryCode) || [];
            var unique = [];
            var seen = {};

            cities.forEach(function (city) {
              if (!seen[city.name]) {
                seen[city.name] = true;
                unique.push(city);
              }
            });

            unique.sort(function (a, b) { return a.name.localeCompare(b.name); }).forEach(function (city) {
              var selected = selectedCity === city.name ? ' selected' : '';
              $city.append('<option value="' + city.name + '"' + selected + '>' + city.name + '</option>');
            });
          }

          function updateCountryName() {
            $countryName.val($('option:selected', $country).data('name') || '');
          }

          function maybeSetLocalGuestType() {
            if (!$guestType.length) return;
            if ($country.val() && hotelCountryCode && $country.val() === hotelCountryCode) {
              $guestType.val('local');
              if (typeof window.resApplyGuestTypeRate === 'function') {
                window.resApplyGuestTypeRate();
              }
            }
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
            maybeSetLocalGuestType();
            if (typeof window.resCreateSyncRates === 'function') {
              window.resCreateSyncRates();
            }
          });

          if (oldCountryCode) {
            updateCountryName();
            loadCities(oldCountryCode, oldCity);
            maybeSetLocalGuestType();
          }

          finishCreateInit();
        });
      }

      import('https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm')
        .then(function (module) {
          bootLocationSelects(module.Country, module.City);
        })
        .catch(function () {
          finishCreateInit();
        });

      window.setTimeout(function () {
        var form = document.getElementById('resCreateForm');
        if (form && form.classList.contains('is-init')) {
          finishCreateInit();
        }
      }, 5000);
    })();
  </script>
@endpush
