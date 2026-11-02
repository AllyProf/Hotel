@php
  $parking = $service->parkingSettings();
  $vehicleTypes = config('hotel_pms_service_templates.vehicle_types', []);
@endphp

<div class="sv-parking-rules border-top pt-3 mt-1">
  <h5 class="sv-section-title mb-3" style="font-size:14px;"><i class="fa fa-car"></i> Parking Rules</h5>

  <div class="row align-items-end">
    <div class="col-md-3">
      <div class="form-group mb-3">
        <label class="control-label">Grace period</label>
        <div class="input-group">
          <input type="number" class="form-control" name="grace_period_minutes" min="0" max="1440" step="1"
            value="{{ old('grace_period_minutes', $parking['grace_period_minutes'] ?? 0) }}">
          <span class="input-group-text">min</span>
        </div>
        @error('grace_period_minutes')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
    </div>
    <div class="col-md-9">
      <div class="sv-settings-toggles mb-3">
        <label class="animated-checkbox">
          <input type="checkbox" name="complimentary_for_inhouse" value="1"
            {{ old('complimentary_for_inhouse', $parking['complimentary_for_inhouse'] ?? false) ? 'checked' : '' }}>
          <span class="label-text">Complimentary for in-house guests</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="require_plate_number" value="1"
            {{ old('require_plate_number', $parking['require_plate_number'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Require plate number</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="require_vehicle_type" value="1"
            {{ old('require_vehicle_type', $parking['require_vehicle_type'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Require vehicle type</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="collect_vehicle_notes" value="1"
            {{ old('collect_vehicle_notes', $parking['collect_vehicle_notes'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Optional notes (color, make, ticket)</span>
        </label>
      </div>
    </div>
  </div>

  @if($vehicleTypes)
    <p class="text-muted small mb-0">Vehicle types when posting: {{ implode(' · ', $vehicleTypes) }}</p>
  @endif
</div>
