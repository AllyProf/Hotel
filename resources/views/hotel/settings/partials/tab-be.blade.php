@php
  $be = $settings->be ?? [];
  $selectedRoom = $beRoom ?? $hotel->rooms->first();
  $selectedPlan = $beRateplan ?? $hotel->ratePlans->first();
@endphp

@if($hotel->rooms->isNotEmpty())
  <form method="GET" action="{{ route('hotel.settings.index') }}" class="form-inline mb-3" id="beRoomPickerForm">
    <input type="hidden" name="tab" value="be">
    @if($selectedPlan)
      <input type="hidden" name="be_rateplan_id" value="{{ $selectedPlan->id }}">
    @endif
    <label class="mr-2 small text-muted mb-0">Room Amenities for</label>
    <select class="form-control form-control-sm mr-3" name="be_room_id" onchange="this.form.submit()">
      @foreach($hotel->rooms as $room)
        <option value="{{ $room->id }}" {{ (int) $selectedRoom?->id === (int) $room->id ? 'selected' : '' }}>{{ $room->name }}</option>
      @endforeach
    </select>
  </form>
@endif

@if($hotel->ratePlans->isNotEmpty())
  <form method="GET" action="{{ route('hotel.settings.index') }}" class="form-inline mb-4" id="beRateplanPickerForm">
    <input type="hidden" name="tab" value="be">
    @if($selectedRoom)
      <input type="hidden" name="be_room_id" value="{{ $selectedRoom->id }}">
    @endif
    <label class="mr-2 small text-muted mb-0">Rateplan Amenities for</label>
    <select class="form-control form-control-sm" name="be_rateplan_id" onchange="this.form.submit()">
      @foreach($hotel->ratePlans as $plan)
        <option value="{{ $plan->id }}" {{ (int) $selectedPlan?->id === (int) $plan->id ? 'selected' : '' }}>{{ $plan->displayLabel() }}</option>
      @endforeach
    </select>
  </form>
@endif

<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="be">

  <h4 class="settings-section-title">Room Types</h4>
  @foreach($hotel->rooms as $room)
    <div class="border rounded p-3 mb-3">
      <div class="row">
        <div class="col-md-4">
          <label class="control-label text-muted small mb-1">ID</label>
          <div class="font-weight-bold">{{ $room->name }}</div>
        </div>
        <div class="col-md-8">
          <label class="control-label text-muted small mb-1">Description</label>
          <input class="form-control form-control-sm" name="room_descriptions[{{ $room->id }}]" value="{{ old("room_descriptions.{$room->id}", $room->description) }}" placeholder="Shown on booking page">
        </div>
      </div>
    </div>
  @endforeach

  <div class="row mb-3">
    <div class="col-md-3">
      <label class="control-label">Default Occupancy</label>
      <input class="form-control" type="number" name="default_occupancy" value="{{ $be['default_occupancy'] ?? 2 }}">
    </div>
    <div class="col-md-9 pt-4">
      <label class="animated-checkbox mr-3"><input type="checkbox" name="add_children" value="1" {{ ($be['add_children'] ?? false) ? 'checked' : '' }}><span class="label-text">Add Children</span></label>
      <label class="animated-checkbox mr-3"><input type="checkbox" name="hide_unavailable_room" value="1" {{ ($be['hide_unavailable_room'] ?? false) ? 'checked' : '' }}><span class="label-text">Hide Unavailable Room</span></label>
      <label class="animated-checkbox"><input type="checkbox" name="show_arrival_departure_time" value="1" {{ ($be['show_arrival_departure_time'] ?? false) ? 'checked' : '' }}><span class="label-text">Show Arrival Departure Time</span></label>
    </div>
  </div>

  <h4 class="settings-section-title">Rateplans</h4>
  <div class="table-responsive">
    <table class="table table-bordered settings-table">
      <thead>
        <tr>
          <th>Rateplan</th>
          <th>Display Name</th>
          <th>BE Ratio</th>
          <th>Extra Adult</th>
          <th>Extra Child</th>
          <th>Policy</th>
        </tr>
      </thead>
      <tbody>
        @foreach($hotel->ratePlans as $plan)
          <tr>
            <td><code class="small">{{ $plan->displayLabel() }}</code></td>
            <td><input class="form-control form-control-sm" name="be_rateplans[{{ $plan->id }}][description]" value="{{ $plan->description }}" placeholder="e.g. Rooms + Breakfast"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" name="be_rateplans[{{ $plan->id }}][be_ratio]" value="{{ $plan->be_ratio }}"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" name="be_rateplans[{{ $plan->id }}][extra_adult]" value="{{ $plan->extra_adult }}"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" name="be_rateplans[{{ $plan->id }}][extra_child]" value="{{ $plan->extra_child }}"></td>
            <td><input class="form-control form-control-sm" name="be_rateplans[{{ $plan->id }}][policy]" value="{{ $plan->policy }}"></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($selectedRoom)
    <h4 class="settings-section-title">Room Amenities — {{ $selectedRoom->name }}</h4>
    <input type="hidden" name="room_amenities_room_id" value="{{ $selectedRoom->id }}">
    <p class="text-muted small">Each room type can have a maximum of 9.</p>
    <div class="amenity-grid mb-4">
      @foreach($amenities as $key => $amenity)
        <label>
          <input type="checkbox" name="room_amenities[]" value="{{ $key }}" {{ in_array($key, $selectedRoom->amenities ?? [], true) ? 'checked' : '' }}>
          <i class="{{ $amenity['icon'] }}"></i> {{ $amenity['label'] }}
        </label>
      @endforeach
    </div>
  @endif

  @if($selectedPlan)
    <h4 class="settings-section-title">Rateplan Amenities — {{ $selectedPlan->displayLabel() }}</h4>
    <input type="hidden" name="rateplan_amenities_plan_id" value="{{ $selectedPlan->id }}">
    <p class="text-muted small">Each rateplan can have a maximum of 3. Tick <strong>Free breakfast</strong> here to show it on your booking page for this rate.</p>
    <div class="amenity-grid mb-4">
      @foreach($amenities as $key => $amenity)
        <label>
          <input type="checkbox" name="rateplan_amenities[]" value="{{ $key }}" {{ in_array($key, $selectedPlan->amenities ?? [], true) ? 'checked' : '' }}>
          <i class="{{ $amenity['icon'] }}"></i> {{ $amenity['label'] }}
        </label>
      @endforeach
    </div>
  @endif

  <h4 class="settings-section-title">Others</h4>
  <div class="row">
    <div class="col-md-6"><div class="form-group"><label class="control-label">Google Tag Manager Id</label><input class="form-control" name="gtm_id" value="{{ $be['gtm_id'] ?? '' }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label class="control-label">Facebook Pixel Id</label><input class="form-control" name="facebook_pixel_id" value="{{ $be['facebook_pixel_id'] ?? '' }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label class="control-label">Gtag</label><input class="form-control" name="gtag" value="{{ $be['gtag'] ?? '' }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label class="control-label">GHA Conversion Tag</label><input class="form-control" name="gha_conversion_tag" value="{{ $be['gha_conversion_tag'] ?? '' }}"></div></div>
    <div class="col-md-6">
      <label class="animated-checkbox d-block"><input type="checkbox" name="tripadvisor_connect" value="1" {{ ($be['tripadvisor_connect'] ?? false) ? 'checked' : '' }}><span class="label-text">Tripadvisor Connect</span></label>
      <div class="form-group"><label class="control-label">Tripadvisor hotel Id</label><input class="form-control" name="tripadvisor_hotel_id" value="{{ $be['tripadvisor_hotel_id'] ?? '' }}"></div>
    </div>
  </div>

  <h4 class="settings-section-title">Cancellation Policies</h4>
  <div class="row">
    <div class="col-md-3"><div class="form-group"><label class="control-label">Checkin</label><input class="form-control" name="checkin_time" value="{{ $be['checkin_time'] ?? '1PM' }}"></div></div>
    <div class="col-md-3"><div class="form-group"><label class="control-label">Checkout</label><input class="form-control" name="checkout_time" value="{{ $be['checkout_time'] ?? '11AM' }}"></div></div>
  </div>
  <div class="form-group"><label class="control-label">Early / Late policy</label><textarea class="form-control" rows="2" name="early_checkin_policy">{{ $be['early_checkin_policy'] ?? '' }}</textarea></div>
  <div class="form-group"><label class="control-label">Cancellation policy</label><textarea class="form-control" rows="3" name="cancellation_policy">{{ $be['cancellation_policy'] ?? '' }}</textarea></div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save BE Settings</button>
  </div>
</form>
