@php
  $selected = old('features', $selectedFeatures ?? []);
@endphp

<div class="form-group">
  <label class="control-label d-block mb-2">Plan Features</label>
  <div class="row">
    @foreach($featureOptions as $key => $label)
      <div class="col-md-6 col-lg-4">
        <div class="animated-checkbox mb-2">
          <label>
            <input type="checkbox" name="features[]" value="{{ $key }}" {{ in_array($key, $selected, true) ? 'checked' : '' }}>
            <span class="label-text">{{ $label }}</span>
          </label>
        </div>
      </div>
    @endforeach
  </div>
  @error('features')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
  @error('features.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
