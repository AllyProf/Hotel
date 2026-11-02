@php
  $itemVendors = $itemVendors ?? collect();
  $storeTaxGroups = $storeTaxGroups ?? collect();
  $storePackages = $storePackages ?? collect();
  $usageTypes = $usageTypes ?? config('hotel_stores.usage_types', []);
@endphp
<div class="modal fade" id="storesItemModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.stores.items.store') }}" id="storesItemForm" data-no-loader>
        @csrf
        <input type="hidden" name="_method" id="storesItemMethod" value="">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesItemModalTitle">{{ $ui['add_item'] ?? 'Add Item' }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0">
          <div class="stores-modal-divider"></div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="storesItemName" class="form-control" required maxlength="160">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">{{ $ui['department_store_label'] ?? 'Department' }} <span class="text-danger">*</span></label>
                <select name="hotel_store_category_id" id="storesItemCategory" class="form-control" required>
                  <option value=""></option>
                  @foreach($masterData['categories'] as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6 js-stores-item-category-wrap" style="display:none;">
              <div class="form-group">
                <label class="control-label">{{ $ui['item_service_category'] ?? 'Category' }} <span class="text-danger js-stores-item-category-required">*</span></label>
                <select name="hotel_pms_service_category_id" id="storesItemPmsCategory" class="form-control">
                  <option value="">Select category…</option>
                </select>
                <p class="text-muted small mb-0 mt-1 js-stores-item-category-hint" style="display:none;"></p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Vendor</label>
                <select name="hotel_vendor_id" id="storesItemVendor" class="form-control">
                  <option value=""></option>
                  @foreach($itemVendors as $vendor)
                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Item usage <span class="text-danger">*</span></label>
                <select name="usage_type" id="storesItemUsage" class="form-control" required>
                  @foreach($usageTypes as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">{{ $ui['item_price'] ?? 'Buying price' }}</label>
                <input type="number" step="0.01" min="0" name="rate" id="storesItemPrice" class="form-control" value="0" placeholder="Per receive unit">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Stock</label>
                <input type="number" step="0.01" min="0" name="stock" id="storesItemStock" class="form-control" value="0">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Tax Inclusive</label>
                <select name="tax_inclusive" id="storesItemTaxInclusive" class="form-control">
                  <option value="1" selected>Yes</option>
                  <option value="0">No</option>
                </select>
              </div>
            </div>
            <div class="col-md-6 js-stores-tax-group-wrap">
              <div class="form-group">
                <label class="control-label">Tax group</label>
                <select name="hotel_store_tax_group_id" id="storesItemTaxGroup" class="form-control">
                  <option value=""></option>
                  @foreach($storeTaxGroups as $tax)
                    <option value="{{ $tax->id }}">{{ $tax->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Inventoried</label>
                <select name="inventoried" id="storesItemInventoried" class="form-control">
                  <option value="1" selected>Yes</option>
                  <option value="0">No</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Capital Item</label>
                <select name="capital_item" id="storesItemCapital" class="form-control">
                  <option value="1">Yes</option>
                  <option value="0" selected>No</option>
                </select>
              </div>
            </div>
          </div>

          <div class="stores-packaging-card stores-packaging-card--modal">
            <div class="stores-packaging-card__title">Unit &amp; Packaging</div>
            <div class="row stores-packaging-row">
              <div class="col-md-6">
                <div class="form-group mb-md-0">
                  <label class="control-label">Receive unit <span class="text-danger">*</span></label>
                  <select name="hotel_store_package_id" id="storesItemPackage" class="form-control js-stores-receive-package" @if($storePackages->isNotEmpty()) required @endif>
                    <option value="">Select unit</option>
                    @foreach($storePackages as $package)
                      <option value="{{ $package->id }}" data-unit-label="{{ $package->unitLabel() }}">{{ $package->displayLabel() }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group mb-0">
                  <label class="control-label js-stores-receive-pieces-label">Pieces in 1 <span class="js-stores-unit-label">unit</span> <span class="text-danger">*</span></label>
                  <input type="number" class="form-control stores-no-spinners" name="pieces_per_receive_unit"
                    id="storesItemPiecesPerUnit" step="0.01" min="0.01" value="1" @if($storePackages->isNotEmpty()) required @endif>
                </div>
              </div>
            </div>

            @include('hotel.erp.stores.partials.item-sell-options')
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-stores-outline">{{ $ui['submit'] ?? 'Submit' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
