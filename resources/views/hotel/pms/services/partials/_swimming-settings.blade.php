@php
  $swim = $service->swimmingSettings();
@endphp

<div class="sv-swim-rules border-top pt-3 mt-1">
  <h5 class="sv-section-title mb-3" style="font-size:14px;"><i class="fa fa-tint"></i> Pool Rules</h5>

  <div class="row align-items-end">
    <div class="col-md-2">
      <div class="form-group mb-3">
        <label class="control-label">Opens</label>
        <input type="time" class="form-control" name="open_time"
          value="{{ old('open_time', $swim['open_time'] ?? '07:00') }}">
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group mb-3">
        <label class="control-label">Closes</label>
        <input type="time" class="form-control" name="close_time"
          value="{{ old('close_time', $swim['close_time'] ?? '19:00') }}">
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-group mb-3">
        <label class="control-label">Max persons / entry</label>
        <input type="number" class="form-control" name="max_persons_per_entry" min="1" max="99"
          value="{{ old('max_persons_per_entry', $swim['max_persons_per_entry'] ?? 10) }}">
      </div>
    </div>
    <div class="col-md-6">
      <div class="sv-settings-toggles mb-3">
        <label class="animated-checkbox">
          <input type="checkbox" name="complimentary_for_inhouse" value="1"
            {{ old('complimentary_for_inhouse', $swim['complimentary_for_inhouse'] ?? false) ? 'checked' : '' }}>
          <span class="label-text">Always complimentary for in-house guests</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="allow_desk_complimentary_override" value="1"
            {{ old('allow_desk_complimentary_override', $swim['allow_desk_complimentary_override'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Reception can mark in-house visit free (when not always free)</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="require_contact_for_walkin" value="1"
            {{ old('require_contact_for_walkin', $swim['require_contact_for_walkin'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Walk-in requires phone number</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="require_room_for_inhouse" value="1"
            {{ old('require_room_for_inhouse', $swim['require_room_for_inhouse'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Require room for in-house guests</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="allow_walk_in" value="1"
            {{ old('allow_walk_in', $swim['allow_walk_in'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Allow walk-in (non guest)</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="post_to_folio" value="1"
            {{ old('post_to_folio', $swim['post_to_folio'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Auto post to room folio</span>
        </label>
      </div>
    </div>
  </div>
</div>
