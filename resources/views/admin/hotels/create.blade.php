@extends('layouts.app')

@section('title', 'Create Hotel')

@push('styles')
  <style>
    .phone-input-group { display: flex; align-items: stretch; }
    .phone-prefix {
      display: flex; align-items: center; padding: 0 14px;
      background: #fff; border: 1px solid rgba(0,0,0,.2); border-right: 0;
      border-radius: 4px 0 0 4px; font-weight: 700; white-space: nowrap; min-width: 72px; justify-content: center;
    }
    .phone-input-group .form-control { border-radius: 0 4px 4px 0; }
    .password-toggle-wrap { position: relative; }
    .password-toggle-wrap .form-control { padding-right: 88px; }
    .password-actions {
      position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
      display: flex; gap: 4px;
    }
    .password-actions .btn { padding: 4px 8px; font-size: 12px; line-height: 1.2; }
    .select2-container { width: 100% !important; }
    .select2-flag { margin-right: 8px; }
    .plan-option-price { color: #666; font-size: 12px; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-plus"></i> Create Hotel Account</h1>
      <p>Register a new hotel on the platform</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="{{ route('admin.hotels.index') }}">Hotels</a></li>
      <li class="breadcrumb-item"><a href="#">Create</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Hotel Details</h3>
        <div class="tile-body">
          <form action="{{ route('admin.hotels.store') }}" method="POST" id="hotelCreateForm">
            @csrf

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Hotel Name <span class="text-danger">*</span></label>
                  <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Grand Palace Hotel" required>
                  @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Hotel Contact Email <span class="text-danger">*</span></label>
                  <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" placeholder="info@hotel.com" required>
                  @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">TIN Number</label>
                  <input class="form-control @error('tin') is-invalid @enderror" type="text" name="tin" value="{{ old('tin') }}" placeholder="Tax Identification Number">
                  @error('tin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Subscription Plan <span class="text-danger">*</span></label>
                  <select class="form-control select2-plan @error('plan_id') is-invalid @enderror" name="plan_id" id="plan_id" required>
                    <option value="">Select plan</option>
                    @foreach($plans as $plan)
                      <option value="{{ $plan->id }}" {{ (string) old('plan_id') === (string) $plan->id ? 'selected' : '' }}
                        data-price="{{ $plan->billingLabel() }}"
                        data-rooms="{{ $plan->roomsLimitLabel() }}"
                        data-users="{{ $plan->usersLimitLabel() }}"
                        data-branches="{{ $plan->branchesLimitLabel() }}"
                        data-features="{{ implode('|', $plan->enabledFeatureLabels()) }}">
                        {{ $plan->name }} — {{ $plan->billingLabel() }}
                      </option>
                    @endforeach
                  </select>
                  @error('plan_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                  <small class="text-muted d-block mt-1" id="planSummary"></small>
                  <ul class="small text-muted mb-0 pl-3" id="planFeatures"></ul>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Country <span class="text-danger">*</span></label>
                  <select class="form-control select2-country @error('country_code') is-invalid @enderror" name="country_code" id="country_code" required>
                    <option value="">Select country</option>
                  </select>
                  <input type="hidden" name="country" id="country_name" value="{{ old('country') }}">
                  @error('country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                  @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">City <span class="text-danger">*</span></label>
                  <select class="form-control select2-city @error('city') is-invalid @enderror" name="city" id="city" required>
                    <option value="">Select city</option>
                  </select>
                  @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Phone</label>
                  <div class="phone-input-group">
                    <span class="phone-prefix" id="phonePrefix">{{ old('phone_country_code', '+255') }}</span>
                    <input type="hidden" name="phone_country_code" id="phone_country_code" value="{{ old('phone_country_code', '+255') }}">
                    <input class="form-control @error('phone') is-invalid @enderror" type="text" name="phone" id="phone" value="{{ old('phone') }}" placeholder="Phone number">
                  </div>
                  @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Address</label>
                  <input class="form-control @error('address') is-invalid @enderror" type="text" name="address" value="{{ old('address') }}">
                  @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>

            <hr>
            <h5 class="mb-3">Hotel Admin Login</h5>
            <p class="text-muted mb-3">This user will manage the hotel after you create the account.</p>

            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Admin Name <span class="text-danger">*</span></label>
                  <input class="form-control @error('admin_name') is-invalid @enderror" type="text" name="admin_name" value="{{ old('admin_name') }}" required>
                  @error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Admin Email <span class="text-danger">*</span></label>
                  <input class="form-control @error('admin_email') is-invalid @enderror" type="email" name="admin_email" value="{{ old('admin_email') }}" required>
                  @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Admin Password <span class="text-danger">*</span></label>
                  <div class="password-toggle-wrap">
                    <input class="form-control @error('admin_password') is-invalid @enderror" type="password" name="admin_password" id="admin_password" required>
                    <div class="password-actions">
                      <button type="button" class="btn btn-sm btn-outline-secondary password-toggle-btn" data-target="admin_password" title="Show password"><i class="fa fa-eye"></i></button>
                      <button type="button" class="btn btn-sm btn-primary" id="generatePasswordBtn" title="Generate password"><i class="fa fa-refresh"></i></button>
                    </div>
                  </div>
                  @error('admin_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">Confirm Password <span class="text-danger">*</span></label>
                  <div class="password-toggle-wrap">
                    <input class="form-control" type="password" name="admin_password_confirmation" id="admin_password_confirmation" required>
                    <div class="password-actions">
                      <button type="button" class="btn btn-sm btn-outline-secondary password-toggle-btn" data-target="admin_password_confirmation" title="Show password"><i class="fa fa-eye"></i></button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="tile-footer">
              <button class="btn btn-primary" type="submit">
                <i class="fa fa-fw fa-lg fa-check-circle"></i> Create Hotel Account
              </button>
              <a class="btn btn-secondary" href="{{ route('admin.hotels.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script type="module">
    import { Country, City } from 'https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm';

    const oldCountryCode = @json(old('country_code'));
    const oldCity = @json(old('city'));

    jQuery(function ($) {
      const countries = Country.getAllCountries().sort((a, b) => a.name.localeCompare(b.name));
      const $country = $('#country_code');
      const $city = $('#city');
      const $countryName = $('#country_name');
      const $phonePrefix = $('#phonePrefix');
      const $phoneCountryCode = $('#phone_country_code');

      countries.forEach(function (country) {
        const selected = oldCountryCode === country.isoCode ? ' selected' : '';
        $country.append(
          '<option value="' + country.isoCode + '" data-name="' + country.name + '" data-phone="+' + country.phonecode + '" data-flag="' + country.flag + '"' + selected + '>' + country.name + '</option>'
        );
      });

      function formatCountry(option) {
        if (!option.id) return option.text;
        const flag = $(option.element).data('flag') || '';
        return $('<span><span class="select2-flag">' + flag + '</span> ' + option.text + '</span>');
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

      function updatePhonePrefix() {
        const phone = $('option:selected', $country).data('phone') || '+255';
        $phonePrefix.text(phone);
        $phoneCountryCode.val(phone);
      }

      function updateCountryName() {
        $countryName.val($('option:selected', $country).data('name') || '');
      }

      $country.select2({ width: '100%', placeholder: 'Select country', templateResult: formatCountry, templateSelection: formatCountry });
      $city.select2({ width: '100%', placeholder: 'Select city' });
      $('.select2-plan').select2({ width: '100%', placeholder: 'Select plan' });

      function updatePlanSummary() {
        const $opt = $('#plan_id option:selected');
        const $features = $('#planFeatures');
        if (!$opt.val()) {
          $('#planSummary').text('');
          $features.empty();
          return;
        }
        $('#planSummary').text($opt.data('rooms') + ' · ' + $opt.data('users') + ' · ' + $opt.data('branches'));
        $features.empty();
        const features = String($opt.data('features') || '').split('|').filter(Boolean);
        features.forEach(function (feature) {
          $features.append($('<li>').text(feature));
        });
      }

      $('#plan_id').on('change', updatePlanSummary);
      updatePlanSummary();

      $country.on('change', function () {
        updateCountryName();
        updatePhonePrefix();
        loadCities($(this).val(), null);
      });

      if (oldCountryCode) {
        updateCountryName();
        updatePhonePrefix();
        loadCities(oldCountryCode, oldCity);
      }

      function generatePassword(length) {
        length = length || 12;
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower = 'abcdefghjkmnpqrstuvwxyz';
        const numbers = '23456789';
        const symbols = '!@#$%&*';
        const all = upper + lower + numbers + symbols;
        const chars = [
          upper.charAt(Math.floor(Math.random() * upper.length)),
          lower.charAt(Math.floor(Math.random() * lower.length)),
          numbers.charAt(Math.floor(Math.random() * numbers.length)),
          symbols.charAt(Math.floor(Math.random() * symbols.length)),
        ];
        for (let i = chars.length; i < length; i++) {
          chars.push(all.charAt(Math.floor(Math.random() * all.length)));
        }
        return chars.sort(() => Math.random() - 0.5).join('');
      }

      $('.password-toggle-btn').on('click', function () {
        const $input = $('#' + $(this).data('target'));
        const isHidden = $input.attr('type') === 'password';
        $input.attr('type', isHidden ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
      });

      $('#generatePasswordBtn').on('click', function () {
        const generated = generatePassword(12);
        $('#admin_password, #admin_password_confirmation').val(generated).attr('type', 'text');
        $('.password-toggle-btn i').removeClass('fa-eye').addClass('fa-eye-slash');
      });
    });
  </script>
@endpush
