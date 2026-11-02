<div class="modal fade" id="mhNewOrderModal" tabindex="-1" role="dialog" aria-labelledby="mhNewOrderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="POST" action="{{ route('hotel.maintenance-hk.maintenance.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="mhNewOrderModalLabel">New maintenance work order</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="mh_title">Title <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="mh_title" name="title" required maxlength="255" placeholder="e.g. AC not cooling">
        </div>
        <div class="form-group">
          <label for="mh_description">Description</label>
          <textarea class="form-control" id="mh_description" name="description" rows="3" maxlength="2000"></textarea>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="mh_category">Category</label>
              <select class="form-control" id="mh_category" name="category">
                @foreach($ui['maintenance']['categories'] ?? [] as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="mh_priority">Priority</label>
              <select class="form-control" id="mh_priority" name="priority">
                @foreach($ui['maintenance']['priorities'] ?? [] as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="mh_unit">Room (optional)</label>
              <select class="form-control" id="mh_unit" name="hotel_room_unit_id">
                <option value="">— General / public area —</option>
                @foreach($roomUnits as $unit)
                  <option value="{{ $unit['value'] }}">{{ $unit['label'] }} · {{ $unit['room_type'] }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="mh_assigned">Assign to</label>
              <select class="form-control" id="mh_assigned" name="assigned_to">
                <option value="">Unassigned</option>
                @foreach($staffOptions as $staff)
                  <option value="{{ $staff['value'] }}">{{ $staff['label'] }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="form-group mb-0">
          <div class="form-check">
            <label class="form-check-label">
              <input class="form-check-input" type="checkbox" name="blocks_room" value="1" id="mh_blocks_room">
              Block room (set unit to maintenance / out of order)
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create work order</button>
      </div>
    </form>
  </div>
</div>
