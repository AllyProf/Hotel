@php
  $isEdit = isset($room);
  $units = old('units', $isEdit ? $room->units->map(fn ($u) => [
      'id' => $u->id,
      'room_number' => $u->room_number,
      'label' => $u->label,
  ])->all() : []);
  $selectedAmenities = old('amenities', $isEdit ? ($room->amenities ?? []) : []);
@endphp

<div class="row">
  <div class="col-md-8 col-lg-6">
    <div class="form-group">
      <label class="control-label">Room Type Name <span class="text-danger">*</span></label>
      <input class="form-control form-control-lg @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name', $room->name ?? '') }}" placeholder="e.g. Deluxe Room" required autofocus>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Number of Rooms <span class="text-danger">*</span></label>
      <input class="form-control @error('room_count') is-invalid @enderror" type="number" name="room_count" id="room_count" value="{{ old('room_count', $room->room_count ?? 1) }}" min="1" max="999" required>
      @error('room_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Max Guests <span class="text-danger">*</span></label>
      <input class="form-control @error('max_occupancy') is-invalid @enderror" type="number" name="max_occupancy" value="{{ old('max_occupancy', $room->max_occupancy ?? 2) }}" min="1" max="20" required>
      @error('max_occupancy')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="form-group">
  <label class="control-label">Description</label>
  <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="2" placeholder="Optional">{{ old('description', $room->description ?? '') }}</textarea>
  @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@include('hotel.rooms.partials._amenities-grid', [
  'amenities' => $amenities ?? config('hotel_amenities', []),
  'selectedAmenities' => $selectedAmenities,
])

@include('hotel.rooms.partials._photos-fields')

<h5 class="settings-section-title">Room numbers <small class="text-muted font-weight-normal">(optional)</small></h5>

<div id="roomUnitsList" class="mb-3">
  @foreach($units as $index => $unit)
    @include('hotel.rooms.partials._unit-row', ['index' => $index, 'unit' => $unit])
  @endforeach
</div>

<button type="button" class="btn btn-outline-secondary btn-sm mb-4" id="addRoomUnitBtn">
  <i class="fa fa-plus"></i> Add number
</button>

<template id="roomUnitRowTemplate">
  @include('hotel.rooms.partials._unit-row', ['index' => '__INDEX__', 'unit' => []])
</template>

<div class="alert alert-light border mb-0">
  <i class="fa fa-info-circle text-muted"></i>
  Set local and international prices under
  <a href="{{ route('hotel.settings.index', ['tab' => 'rateplan']) }}">Settings → Prices</a>.
</div>

@if($isEdit)
  <div class="form-group mt-4 mb-0">
    <div class="animated-checkbox">
      <label>
        <input type="checkbox" name="is_enabled" value="1" {{ old('is_enabled', $room->is_enabled ?? true) ? 'checked' : '' }}>
        <span class="label-text">Active</span>
      </label>
    </div>
  </div>
@endif

@push('scripts')
  <script>
    jQuery(function ($) {
      var unitIndex = {{ count($units) }};

      function maxUnits() {
        var count = parseInt($('#room_count').val(), 10);
        return isNaN(count) ? 999 : count;
      }

      function updateAddUnitBtn() {
        $('#addRoomUnitBtn').prop('disabled', $('#roomUnitsList .room-unit-row').length >= maxUnits());
      }

      $('#addRoomUnitBtn').on('click', function () {
        if ($('#roomUnitsList .room-unit-row').length >= maxUnits()) return;
        var html = $('#roomUnitRowTemplate').html().replace(/__INDEX__/g, String(unitIndex++));
        $('#roomUnitsList').append(html);
        updateAddUnitBtn();
        $('#roomUnitsList .room-unit-row').last().find('input[name*="room_number"]').focus();
      });

      $(document).on('click', '.room-unit-remove', function () {
        $(this).closest('.room-unit-row').remove();
        updateAddUnitBtn();
      });

      $('#room_count').on('input', updateAddUnitBtn);
      updateAddUnitBtn();
    });
  </script>
@endpush
