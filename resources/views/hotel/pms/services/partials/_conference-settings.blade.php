@php
  $conf = $service->conferenceSettings();
@endphp

<div class="sv-conf-rules border-top pt-3 mt-1">
  <h5 class="sv-section-title mb-3" style="font-size:14px;"><i class="fa fa-users"></i> Conference desk rules</h5>

  <div class="row align-items-end">
    <div class="col-md-2">
      <div class="form-group mb-3">
        <label class="control-label">Hours from</label>
        <input type="time" class="form-control" name="business_hours_start"
          value="{{ old('business_hours_start', $conf['business_hours_start'] ?? '08:00') }}">
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group mb-3">
        <label class="control-label">Hours to</label>
        <input type="time" class="form-control" name="business_hours_end"
          value="{{ old('business_hours_end', $conf['business_hours_end'] ?? '22:00') }}">
      </div>
    </div>
    <div class="col-md-8">
      <div class="sv-settings-toggles mb-3">
        <label class="animated-checkbox">
          <input type="checkbox" name="allow_external_booking" value="1"
            {{ old('allow_external_booking', $conf['allow_external_booking'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Allow external / walk-in bookings</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="require_contact_for_external" value="1"
            {{ old('require_contact_for_external', $conf['require_contact_for_external'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">External booking requires phone</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="require_room_for_inhouse" value="1"
            {{ old('require_room_for_inhouse', $conf['require_room_for_inhouse'] ?? false) ? 'checked' : '' }}>
          <span class="label-text">Require room for in-house link</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="post_to_folio" value="1"
            {{ old('post_to_folio', $conf['post_to_folio'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Auto post to room folio (in-house)</span>
        </label>
      </div>
    </div>
  </div>
</div>
