@php
  $poVendors = $poVendors ?? collect();
  $poStoreDepartments = $poStoreDepartments ?? collect();
@endphp
<div class="modal fade" id="storesCreatePoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg stores-po-modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.stores.purchase-orders.store') }}" id="storesPoForm" data-no-loader>
        @csrf
        <input type="hidden" name="_method" id="storesPoMethod" value="">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesPoModalTitle">Create PO</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0 stores-po-modal-body">
          <div class="stores-modal-divider"></div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="control-label">Vendor</label>
              <select name="hotel_vendor_id" id="storesPoVendor" class="form-control" required>
                <option value="">Select vendor</option>
                @foreach($poVendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="control-label">{{ config('hotel_stores.ui.department_store_label', 'Department') }}</label>
              <select name="hotel_store_category_id" id="storesPoDepartment" class="form-control" required
                @if($poStoreDepartments->isEmpty()) disabled title="Add departments under Settings → Department first" @endif>
                <option value="">Select department</option>
                @foreach($poStoreDepartments as $dept)
                  <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="stores-po-entry-row">
            <div class="stores-po-entry-field stores-po-entry-field--item">
              <label class="control-label">Select Item</label>
              <select id="storesPoItemSelect" class="form-control js-stores-po-item-select">
                <option value="">Select item</option>
              </select>
            </div>
            <div class="stores-po-entry-field">
              <label class="control-label">Quantity</label>
              <input type="number" step="0.01" min="0" class="form-control js-stores-po-qty" value="1">
            </div>
            <div class="stores-po-entry-field">
              <label class="control-label">Buying price</label>
              <input type="number" step="0.01" min="0.01" class="form-control js-stores-po-rate" value="" placeholder="Unit cost">
            </div>
            <div class="stores-po-entry-field">
              <label class="control-label">Tax</label>
              <input type="number" step="0.01" min="0" class="form-control js-stores-po-tax" value="0">
            </div>
            <div class="stores-po-entry-field">
              <label class="control-label">Total</label>
              <input type="text" class="form-control stores-po-calc js-stores-po-line-total" readonly value="0">
            </div>
            <div class="stores-po-entry-field stores-po-entry-field--action">
              <label class="control-label">&nbsp;</label>
              <button type="button" class="btn btn-outline-primary btn-block js-stores-po-add">Add item</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered stores-po-table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Qty</th>
                  <th>Buying price</th>
                  <th>Tax</th>
                  <th>Total</th>
                  <th style="width:72px"></th>
                </tr>
              </thead>
              <tbody id="storesPoItemsBody"></tbody>
              <tfoot>
                <tr>
                  <td>Total</td>
                  <td id="storesPoSumQty">0</td>
                  <td></td>
                  <td id="storesPoSumTax">0</td>
                  <td id="storesPoSumTotal">0</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer stores-po-modal-footer">
          <button type="button" class="btn btn-outline-danger" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-stores-outline" id="storesPoSubmit" disabled>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
