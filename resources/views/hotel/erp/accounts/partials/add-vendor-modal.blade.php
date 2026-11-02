@php
  $vendorPhoneCode = old('phone_country_code', $hotel->phone_country_code ?? '+255');
  $tanzaniaRegions = config('tanzania_regions', []);
@endphp

<div class="modal fade" id="acAddVendorModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.accounts.vendors.store') }}">
        @csrf
        <div class="modal-header ac-modal-header">
          <h5 class="modal-title">Add Vendor</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="ac-modal-divider"></div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Vendor Name</label>
                <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
              </div>
              <div class="form-group">
                <label class="control-label">Address</label>
                <textarea class="form-control" name="address" rows="3">{{ old('address') }}</textarea>
              </div>
              <div class="form-group">
                <label class="control-label">Email</label>
                <input type="email" class="form-control" name="email" value="{{ old('email') }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person" value="{{ old('contact_person') }}">
              </div>
              <div class="form-group">
                <label class="control-label">Phone</label>
                <div class="ac-vendor-phone-group">
                  <select id="acVendorPhoneCode" class="form-control" aria-label="Country calling code"></select>
                  <input type="hidden" name="phone_country_code" id="acVendorPhoneCountryCode" value="{{ $vendorPhoneCode }}">
                  <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone"
                    value="{{ old('phone') }}" inputmode="tel" placeholder="Local number">
                </div>
                @error('phone')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group mb-0">
                <label class="control-label">Region</label>
                <select class="form-control" name="state">
                  <option value="">— Select region —</option>
                  @foreach($tanzaniaRegions as $region)
                    <option value="{{ $region }}" @selected(old('state') === $region)>{{ $region }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" style="background:#940000;border-color:#940000;">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

@once
  @push('styles')
    <link rel="stylesheet" href="{{ asset('panel-assets/css/select2.min.css') }}">
    <style>
      .ac-vendor-phone-group {
        display: flex;
        align-items: stretch;
        gap: 0;
      }
      .ac-vendor-phone-group .select2-container {
        width: 118px !important;
        flex: 0 0 118px;
      }
      .ac-vendor-phone-group .select2-container .select2-selection--single {
        height: 38px;
        border-radius: 4px 0 0 4px;
        border-right: 0;
        display: flex;
        align-items: center;
      }
      .ac-vendor-phone-group .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-left: 8px;
        padding-right: 24px;
      }
      .ac-vendor-phone-group .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
      }
      .ac-vendor-phone-group input[name="phone"] {
        border-radius: 0 4px 4px 0;
        flex: 1 1 auto;
      }
      .select2-flag {
        margin-right: 6px;
      }
    </style>
  @endpush
  @push('scripts')
    <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
    <script type="module">
      import { Country } from 'https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm';

      const oldPhoneCode = @json($vendorPhoneCode);

      jQuery(function ($) {
        const countries = Country.getAllCountries().sort((a, b) => a.name.localeCompare(b.name));
        const $phoneCodeSelect = $('#acVendorPhoneCode');
        const $phoneCountryCode = $('#acVendorPhoneCountryCode');

        countries.forEach(function (country) {
          const phone = '+' + country.phonecode;
          const phoneSelected = oldPhoneCode === phone ? ' selected' : '';
          $phoneCodeSelect.append(
            '<option value="' + phone + '" data-flag="' + country.flag + '" data-iso="' + country.isoCode + '"' + phoneSelected + '>' + country.flag + ' ' + phone + '</option>'
          );
        });

        function formatPhoneCode(option) {
          if (!option.id) {
            return option.text;
          }
          const flag = $(option.element).data('flag') || '';
          return $('<span><span class="select2-flag">' + flag + '</span> ' + option.id + '</span>');
        }

        $phoneCodeSelect.select2({
          width: '118px',
          minimumResultsForSearch: 6,
          dropdownParent: $('#acAddVendorModal'),
          templateResult: formatPhoneCode,
          templateSelection: formatPhoneCode,
        });

        $phoneCodeSelect.on('change', function () {
          $phoneCountryCode.val($(this).val() || '');
        });
        $phoneCountryCode.val($phoneCodeSelect.val() || oldPhoneCode);

        @if($errors->any() && old('name'))
          $('#acAddVendorModal').modal('show');
        @endif
      });
    </script>
  @endpush
@endonce
