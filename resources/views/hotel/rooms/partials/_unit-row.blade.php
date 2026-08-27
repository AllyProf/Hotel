<div class="room-unit-row row align-items-end mb-2">
  <div class="col-md-3">
    @if(!empty($unit['id']))
      <input type="hidden" name="units[{{ $index }}][id]" value="{{ $unit['id'] }}">
    @endif
    <label class="control-label small">Room Number</label>
    <input class="form-control form-control-sm" type="text" name="units[{{ $index }}][room_number]" value="{{ $unit['room_number'] ?? '' }}" placeholder="e.g. 101">
  </div>
  <div class="col-md-4">
    <label class="control-label small">Label <span class="text-muted">(optional)</span></label>
    <input class="form-control form-control-sm" type="text" name="units[{{ $index }}][label]" value="{{ $unit['label'] ?? '' }}" placeholder="e.g. Sea view">
  </div>
  <div class="col-md-2 pb-1">
    <button type="button" class="btn btn-sm btn-outline-danger room-unit-remove" title="Remove">
      <i class="fa fa-times"></i>
    </button>
  </div>
</div>
