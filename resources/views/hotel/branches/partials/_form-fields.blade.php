@php
  $isEdit = isset($branch);
  $branchModel = $branch ?? null;
  $hotelDefaults = auth()->user()->hotel;
  $phoneCode = old('phone_country_code', $branchModel?->phone_country_code ?: ($hotelDefaults?->phone_country_code ?? '+255'));
  $phoneNumber = old('phone', $branchModel?->phone ?? '');
  if ($isEdit && $phoneCode && str_starts_with($phoneNumber, $phoneCode)) {
      $phoneNumber = ltrim(substr($phoneNumber, strlen($phoneCode)), '0');
  }
  $selectedCountry = old('country_code', $branchModel?->country_code ?: ($hotelDefaults?->country_code ?? ''));
  $selectedCity = old('city', $branchModel?->city ?: ($hotelDefaults?->city ?? ''));
  $selectedCountryName = old('country', $branchModel?->country ?: ($hotelDefaults?->country ?? ''));
@endphp

<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Branch Name <span class="text-danger">*</span></label>
      <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name', $branchModel?->name ?? '') }}" required>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Email</label>
      <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $branchModel?->email ?? '') }}">
      @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Country</label>
      <select class="form-control branch-select-country @error('country_code') is-invalid @enderror" name="country_code" id="branch_country_code">
        <option value="">Select country</option>
      </select>
      <input type="hidden" name="country" id="branch_country_name" value="{{ $selectedCountryName }}">
      @error('country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">City</label>
      <select class="form-control branch-select-city @error('city') is-invalid @enderror" name="city" id="branch_city">
        <option value="">Select city</option>
      </select>
      @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Phone</label>
      <div class="branch-phone-group">
        <select class="branch-select-phone-code" id="branch_phone_code" aria-label="Phone country code"></select>
        <input type="hidden" name="phone_country_code" id="branch_phone_country_code" value="{{ $phoneCode }}">
        <input class="form-control @error('phone') is-invalid @enderror" type="text" name="phone" id="branch_phone" value="{{ $phoneNumber }}" placeholder="Phone number">
      </div>
      @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      @error('phone_country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  @if($isEdit)
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Status <span class="text-danger">*</span></label>
        <select class="form-control @error('status') is-invalid @enderror" name="status" required>
          <option value="active" {{ old('status', $branchModel?->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ old('status', $branchModel?->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
  @endif
  <div class="col-md-4">
    <div class="form-group {{ $isEdit ? '' : 'pt-4' }}">
      <div class="animated-checkbox">
        <label>
          <input type="checkbox" name="is_headquarters" value="1" {{ old('is_headquarters', $branchModel?->is_headquarters ?? false) ? 'checked' : '' }}>
          <span class="label-text">Headquarters branch</span>
        </label>
      </div>
    </div>
  </div>
</div>

<div class="form-group">
  <label class="control-label">Address</label>
  <textarea class="form-control @error('address') is-invalid @enderror" name="address" rows="2">{{ old('address', $branchModel?->address ?? '') }}</textarea>
  @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
