<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="hotel">

  <h4 class="settings-section-title">Hotel Details</h4>
  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Name</label>
        <input class="form-control" type="text" name="name" value="{{ old('name', $hotel->name) }}" required>
        <small class="text-muted">Display name of the hotel</small>
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Display Name</label>
        <input class="form-control" type="text" name="display_name" value="{{ old('display_name', $hotel->display_name ?? $hotel->name) }}">
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Country</label>
        <input class="form-control" type="text" name="country" value="{{ old('country', $hotel->country) }}" placeholder="Tanzania, United Republic of">
        <input type="hidden" name="country_code" value="{{ old('country_code', $hotel->country_code ?? 'TZ') }}">
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">State</label>
        <input class="form-control" type="text" name="state" value="{{ old('state', $hotel->state) }}">
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="form-group">
        <label class="control-label">Address</label>
        <input class="form-control" type="text" name="address" value="{{ old('address', $hotel->address) }}">
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group">
        <label class="control-label">Pin Code</label>
        <input class="form-control" type="text" name="pin_code" value="{{ old('pin_code', $hotel->pin_code) }}">
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group">
        <label class="control-label">City</label>
        <input class="form-control" type="text" name="city" value="{{ old('city', $hotel->city) }}">
      </div>
    </div>
  </div>

  <div class="form-group">
    <label class="control-label">Google Maps location</label>
    <input class="form-control" type="url" name="google_maps_url" value="{{ old('google_maps_url', $hotel->google_maps_url) }}" placeholder="https://maps.google.com/...">
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Default currency</label>
        @include('partials._currency-select', [
          'name' => 'currency',
          'selected' => old('currency', $hotel->currency ?? 'USD'),
        ])
      </div>
    </div>
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Timezone</label>
        <select class="form-control" name="timezone">
          @foreach(['Africa/Dar_es_Salaam', 'Africa/Nairobi', 'Asia/Kolkata', 'UTC'] as $tz)
            <option value="{{ $tz }}" {{ old('timezone', $hotel->timezone ?? 'Africa/Dar_es_Salaam') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group">
        <label class="control-label">Latitude</label>
        <input class="form-control" type="text" name="latitude" value="{{ old('latitude', $hotel->latitude) }}">
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group">
        <label class="control-label">Longitude</label>
        <input class="form-control" type="text" name="longitude" value="{{ old('longitude', $hotel->longitude) }}">
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Phone</label>
        <input class="form-control" type="text" name="phone" value="{{ old('phone', $hotel->phone) }}">
      </div>
    </div>
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Email</label>
        <input class="form-control" type="email" name="email" value="{{ old('email', $hotel->email) }}">
      </div>
    </div>
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Website</label>
        <input class="form-control" type="url" name="website" value="{{ old('website', $hotel->website) }}">
      </div>
    </div>
  </div>

  <div class="form-group">
    <label class="control-label">Google Review Link</label>
    <input class="form-control" type="url" name="google_review_link" value="{{ old('google_review_link', $hotel->google_review_link) }}">
  </div>

  <h4 class="settings-section-title">Bank Details</h4>
  <div class="row">
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">Bank Name</label>
        <input class="form-control" type="text" name="bank_name" value="{{ old('bank_name', $hotel->bank_name) }}">
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">Account Name</label>
        <input class="form-control" type="text" name="bank_account_name" value="{{ old('bank_account_name', $hotel->bank_account_name) }}">
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">Account No.</label>
        <input class="form-control" type="text" name="bank_account_no" value="{{ old('bank_account_no', $hotel->bank_account_no) }}">
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">IFSC Code</label>
        <input class="form-control" type="text" name="bank_ifsc" value="{{ old('bank_ifsc', $hotel->bank_ifsc) }}">
      </div>
    </div>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Hotel Details</button>
  </div>
</form>

@include('partials._select2-init')
