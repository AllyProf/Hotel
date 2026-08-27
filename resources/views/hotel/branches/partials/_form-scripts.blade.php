@php
  $branchModel = $branch ?? null;
  $hotelDefaults = auth()->user()->hotel;
  $phoneCode = old('phone_country_code', $branchModel?->phone_country_code ?: ($hotelDefaults?->phone_country_code ?? '+255'));
  $selectedCountry = old('country_code', $branchModel?->country_code ?: ($hotelDefaults?->country_code ?? ''));
  $selectedCity = old('city', $branchModel?->city ?: ($hotelDefaults?->city ?? ''));
@endphp

@push('styles')
  <style>
    .branch-phone-group {
      display: flex;
      align-items: stretch;
      gap: 0;
    }
    .branch-phone-group .select2-container {
      width: 118px !important;
      flex: 0 0 118px;
    }
    .branch-phone-group .select2-container .select2-selection--single {
      height: 38px;
      border-radius: 4px 0 0 4px;
      border-right: 0;
      display: flex;
      align-items: center;
    }
    .branch-phone-group .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px;
      padding-left: 8px;
      padding-right: 24px;
    }
    .branch-phone-group .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
    }
    .branch-phone-group #branch_phone {
      border-radius: 0 4px 4px 0;
      flex: 1 1 auto;
    }
    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 6px; }
  </style>
@endpush

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script type="module">
    import { Country, City } from 'https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm';

    const oldCountryCode = @json($selectedCountry);
    const oldCity = @json($selectedCity);
    const oldPhoneCode = @json($phoneCode);

    jQuery(function ($) {
      const countries = Country.getAllCountries().sort((a, b) => a.name.localeCompare(b.name));
      const $country = $('#branch_country_code');
      const $city = $('#branch_city');
      const $countryName = $('#branch_country_name');
      const $phoneCodeSelect = $('#branch_phone_code');
      const $phoneCountryCode = $('#branch_phone_country_code');

      countries.forEach(function (country) {
        const phone = '+' + country.phonecode;
        const selected = oldCountryCode === country.isoCode ? ' selected' : '';
        $country.append(
          '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-phone="' + phone + '" data-flag="' + country.flag + '"' + selected + '>' + country.name + '</option>'
        );
        const phoneSelected = oldPhoneCode === phone ? ' selected' : '';
        $phoneCodeSelect.append(
          '<option value="' + phone + '" data-flag="' + country.flag + '" data-iso="' + country.isoCode + '"' + phoneSelected + '>' + country.flag + ' ' + phone + '</option>'
        );
      });

      function formatCountry(option) {
        if (!option.id) return option.text;
        const flag = $(option.element).data('flag') || '';
        return $('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
      }

      function formatPhoneCode(option) {
        if (!option.id) return option.text;
        const flag = $(option.element).data('flag') || '';
        return $('<span><span class="select2-flag">' + flag + '</span> ' + option.id + '</span>');
      }

      function loadCities(countryCode, selectedCity) {
        $city.empty().append('<option value="">Select city</option>');
        if (!countryCode) {
          $city.trigger('change');
          return;
        }

        const cities = City.getCitiesOfCountry(countryCode) || [];
        const unique = [...new Map(cities.map(c => [c.name, c])).values()].sort((a, b) => a.name.localeCompare(b.name));

        unique.forEach(function (city) {
          const selected = selectedCity === city.name ? ' selected' : '';
          $city.append('<option value="' + city.name + '"' + selected + '>' + city.name + '</option>');
        });

        $city.trigger('change');
      }

      function syncPhoneCodeFromCountry() {
        const phone = $('option:selected', $country).data('phone');
        if (!phone) return;
        $phoneCodeSelect.val(phone).trigger('change.select2');
        $phoneCountryCode.val(phone);
      }

      function updateCountryName() {
        $countryName.val($('option:selected', $country).data('name') || '');
      }

      $country.select2({ width: '100%', placeholder: 'Select country', templateResult: formatCountry, templateSelection: formatCountry });
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
      });

      $phoneCodeSelect.on('change', function () {
        $phoneCountryCode.val($(this).val() || '');
        const iso = $('option:selected', this).data('iso');
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
  </script>
@endpush
