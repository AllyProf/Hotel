@php
  $consumptionDepartments = $consumptionDepartments ?? collect();
  $consumptionCategories = $consumptionCategories ?? collect();
  $consumptionFormRoute = $consumptionFormRoute ?? route('hotel.stores.consumptions.store');
  $consumptionModalTitle = $consumptionModalTitle ?? 'Create Consumption';
  $consumptionHideDepartmentSelect = $consumptionHideDepartmentSelect ?? false;
  $consumptionHideCategory = $consumptionHideCategory ?? false;
  $consumptionHideUnit = $consumptionHideUnit ?? false;
  $hkDefaultDepartmentId = $hkDefaultDepartmentId ?? null;
  $consumptionDeptPreset = $consumptionHideDepartmentSelect && $hkDefaultDepartmentId;
  $consumptionShowRoomDistribution = $consumptionShowRoomDistribution ?? false;
  $consumptionRoomOptions = $consumptionRoomOptions ?? [];
@endphp
<div class="modal fade" id="storesCreateConsumptionModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg stores-po-modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ $consumptionFormRoute }}" id="storesConsumptionForm" data-no-loader>
        @csrf
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title">{{ $consumptionModalTitle }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0 stores-po-modal-body">
          <div class="stores-modal-divider"></div>

          @if($consumptionHideDepartmentSelect && $hkDefaultDepartmentId)
            <input type="hidden" name="hotel_department_id" id="storesConsumptionDepartment" value="{{ $hkDefaultDepartmentId }}">
          @else
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="control-label">Department</label>
                <select name="hotel_department_id" id="storesConsumptionDepartment" class="form-control" required>
                  <option value="">Select department</option>
                  @foreach($consumptionDepartments as $dept)
                    <option value="{{ $dept->id }}" @selected($hkDefaultDepartmentId && (int) $dept->id === (int) $hkDefaultDepartmentId)>{{ $dept->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          @endif

          <div class="stores-indent-entry-row stores-po-entry-row @if($consumptionHideCategory && $consumptionDeptPreset) stores-indent-entry-row--compact @endif">
            @if($consumptionHideCategory)
              <input type="hidden" id="storesConsumptionCategory" value="0">
            @else
              <div class="stores-po-entry-field">
                <label class="control-label">Category</label>
                <select id="storesConsumptionCategory" class="form-control">
                  <option value="0">All</option>
                  @foreach($consumptionCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                  @endforeach
                </select>
              </div>
            @endif
            <div class="stores-po-entry-field stores-po-entry-field--item">
              <label class="control-label">Select Item</label>
              <select id="storesConsumptionItemSelect" class="form-control" @unless($consumptionDeptPreset) disabled @endunless>
                <option value="">{{ $consumptionDeptPreset ? 'Select item' : 'Select department first' }}</option>
              </select>
            </div>
            <div class="stores-po-entry-field stores-po-entry-field--qty">
              <label class="control-label">Quantity</label>
              <input type="number" step="0.01" min="0" class="form-control js-stores-consumption-qty" value="0">
              <small id="storesConsumptionQtyHint" class="stores-indent-qty-hint text-muted d-block mt-1" aria-live="polite">&nbsp;</small>
            </div>
            @unless($consumptionHideUnit)
              <div class="stores-po-entry-field">
                <label class="control-label">Unit</label>
                <input type="text" class="form-control js-stores-consumption-unit" readonly value="">
              </div>
            @endunless
            @if($consumptionShowRoomDistribution)
              <div class="stores-po-entry-field stores-po-entry-field--rooms">
                <label class="control-label d-block">Room(s) <span class="text-muted font-weight-normal">(optional)</span></label>
                <select id="storesConsumptionRoomSelect" class="form-control js-stores-consumption-room" multiple>
                  @foreach($consumptionRoomOptions as $room)
                    <option value="{{ $room['value'] }}">{{ $room['consumption_label'] ?? $room['label'] }}</option>
                  @endforeach
                </select>
                <label class="custom-control custom-checkbox mt-2 mb-0">
                  <input type="checkbox" class="custom-control-input" id="storesConsumptionAllRooms">
                  <span class="custom-control-label">All rooms</span>
                </label>
              </div>
            @endif
            <div class="stores-po-entry-field">
              <label class="control-label">Comments</label>
              <input type="text" class="form-control js-stores-consumption-line-comment" maxlength="500" value="">
            </div>
            <div class="stores-po-entry-field stores-po-entry-field--action">
              <label class="control-label">&nbsp;</label>
              <button type="button" class="btn btn-outline-primary btn-block js-stores-consumption-add">Add item</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered stores-po-table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Qty</th>
                  @if($consumptionShowRoomDistribution)
                    <th>Room</th>
                  @endif
                  <th>Comments</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              @if($consumptionShowRoomDistribution)
                @php $consumptionTableColspan = 5; @endphp
              @else
                @php $consumptionTableColspan = 4; @endphp
              @endif
              <tbody id="storesConsumptionItemsBody"></tbody>
              <tfoot>
                <tr>
                  <td>Total</td>
                  <td id="storesConsumptionSumQty">0</td>
                  <td colspan="{{ $consumptionTableColspan - 2 }}"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer stores-po-modal-footer">
          <button type="button" class="btn btn-outline-danger" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-stores-outline" id="storesConsumptionSubmit" disabled>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
