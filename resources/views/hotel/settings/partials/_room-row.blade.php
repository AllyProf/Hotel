@php
  $roomModel = $room ?? null;
  $isNew = $isNew ?? empty($roomModel?->id);
@endphp
<tbody class="room-type-group" data-room-group="{{ $index }}">
  <tr class="room-type-row {{ $isNew ? 'room-type-row--new' : '' }}">
    <td>
      @if($roomModel?->id)
        <input type="hidden" name="rooms[{{ $index }}][id]" value="{{ $roomModel->id }}">
      @endif
      <input type="hidden" name="rooms[{{ $index }}][_delete]" value="0" class="room-delete-flag">
      <input type="checkbox" name="rooms[{{ $index }}][is_enabled]" value="1" {{ old("rooms.$index.is_enabled", $roomModel?->is_enabled ?? true) ? 'checked' : '' }}>
    </td>
    <td><input class="form-control form-control-sm" type="number" name="rooms[{{ $index }}][rank]" value="{{ old("rooms.$index.rank", $roomModel?->rank ?? 0) }}" min="0"></td>
    <td><input class="form-control form-control-sm room-name-input" type="text" name="rooms[{{ $index }}][name]" value="{{ old("rooms.$index.name", $roomModel?->name ?? '') }}" required placeholder="e.g. Deluxe Room"></td>
    <td><input class="form-control form-control-sm" type="text" name="rooms[{{ $index }}][description]" value="{{ old("rooms.$index.description", $roomModel?->description ?? '') }}" placeholder="Short description"></td>
    <td><input class="form-control form-control-sm" type="number" name="rooms[{{ $index }}][room_count]" value="{{ old("rooms.$index.room_count", $roomModel?->room_count ?? 1) }}" min="1"></td>
    <td class="text-nowrap">
      Min <input class="form-control form-control-sm d-inline-block" style="width:55px" type="number" name="rooms[{{ $index }}][min_occupancy]" value="{{ old("rooms.$index.min_occupancy", $roomModel?->min_occupancy ?? 1) }}" min="1">
      Max <input class="form-control form-control-sm d-inline-block" style="width:55px" type="number" name="rooms[{{ $index }}][max_occupancy]" value="{{ old("rooms.$index.max_occupancy", $roomModel?->max_occupancy ?? 2) }}" min="1">
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-danger room-remove-btn" title="Remove room type">
        <i class="fa fa-trash"></i>
      </button>
    </td>
  </tr>
  <tr class="room-type-details {{ $isNew ? 'room-type-row--new' : '' }}">
    <td colspan="7" class="bg-light">
      <div class="row align-items-end">
        <div class="col-md-4">
          <label class="control-label small">Display Name</label>
          <input class="form-control form-control-sm room-display-name-input" type="text" name="rooms[{{ $index }}][display_name]" value="{{ old("rooms.$index.display_name", $roomModel?->display_name ?? '') }}" placeholder="Shown to guests">
        </div>
        <div class="col-md-4">
          <label class="control-label small d-block">Bookings</label>
          <label class="animated-checkbox mb-0">
            <input type="checkbox" name="rooms[{{ $index }}][show_ota_breakup]" value="1" {{ old("rooms.$index.show_ota_breakup", $roomModel?->show_ota_breakup ?? false) ? 'checked' : '' }}>
            <span class="label-text">Show OTA Breakup</span>
          </label>
        </div>
        @if($isNew)
          <div class="col-md-4">
            <span class="badge badge-info">New — click Save Rooms to create</span>
          </div>
        @endif
      </div>
    </td>
  </tr>
</tbody>
