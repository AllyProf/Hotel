@php
  $indentDepartments = $indentDepartments ?? collect();
  $indentCategories = $indentCategories ?? collect();
  $indentRoutes = $indentRoutes ?? ['store' => route('hotel.stores.indents.store')];
  $indentModalTitleCreate = $indentModalTitleCreate ?? 'Create Indent';
  $indentHideDepartmentSelect = $indentHideDepartmentSelect ?? false;
  $indentHideDepartmentDisplay = $indentHideDepartmentDisplay ?? false;
  $indentRequesterUsesStoreCategory = $indentRequesterUsesStoreCategory ?? false;
  $indentHideCategory = $indentHideCategory ?? false;
  $indentHideUnit = $indentHideUnit ?? false;
  $indentEntryRowCompact = $indentHideCategory && $indentHideUnit;
  $hkDefaultDepartmentId = $hkDefaultDepartmentId ?? null;
@endphp
<div class="modal fade" id="storesCreateIndentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg stores-po-modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ $indentRoutes['store'] }}" id="storesIndentForm" data-no-loader>
        @csrf
        <input type="hidden" name="_method" id="storesIndentMethod" value="">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesIndentModalTitle">{{ $indentModalTitleCreate }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0 stores-po-modal-body">
          <div class="stores-modal-divider"></div>

          @if($indentHideDepartmentSelect && $hkDefaultDepartmentId)
            <input type="hidden" name="hotel_department_id" id="storesIndentDepartment" value="{{ $hkDefaultDepartmentId }}">
            @if(! $indentHideDepartmentDisplay)
              @php $defaultDept = $indentDepartments->firstWhere('id', $hkDefaultDepartmentId); @endphp
              @if($defaultDept)
                <p class="text-muted small mb-3">Requesting for: <strong>{{ $defaultDept->name }}</strong></p>
              @endif
            @endif
          @else
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="control-label">Requester Department</label>
                @if($indentRequesterUsesStoreCategory)
                  <select name="hotel_store_category_id" id="storesIndentDepartment" class="form-control" required>
                    <option value="">Select department</option>
                    @foreach($indentCategories as $dept)
                      <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                  </select>
                @else
                  <select name="hotel_department_id" id="storesIndentDepartment" class="form-control" required>
                    <option value="">Select department</option>
                    @foreach($indentDepartments as $dept)
                      <option value="{{ $dept->id }}" @selected($hkDefaultDepartmentId && (int) $dept->id === (int) $hkDefaultDepartmentId)>{{ $dept->name }}</option>
                    @endforeach
                  </select>
                @endif
              </div>
            </div>
          @endif

          <div class="stores-indent-entry-row stores-po-entry-row @if($indentEntryRowCompact) stores-indent-entry-row--compact @endif">
            @if($indentHideCategory)
              <input type="hidden" id="storesIndentCategory" value="0">
            @else
              <div class="stores-po-entry-field">
                <label class="control-label">Category</label>
                <select id="storesIndentCategory" class="form-control js-stores-indent-category">
                  <option value="0">All</option>
                  @foreach($indentCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                  @endforeach
                </select>
              </div>
            @endif
            <div class="stores-po-entry-field stores-po-entry-field--item">
              <label class="control-label">Select Item</label>
              <select id="storesIndentItemSelect" class="form-control js-stores-indent-item-select">
                <option value="">Select item</option>
              </select>
            </div>
            @unless($indentHideUnit)
              <div class="stores-po-entry-field">
                <label class="control-label">Issue unit</label>
                <select class="form-control js-stores-indent-unit" disabled>
                  <option value="">Select item first</option>
                </select>
                <small id="storesIndentSellPriceHint" class="stores-indent-qty-hint text-muted d-block mt-1" aria-live="polite">&nbsp;</small>
              </div>
            @endunless
            <div class="stores-po-entry-field stores-po-entry-field--qty">
              <label class="control-label">Quantity</label>
              <input type="number" step="0.01" min="0" class="form-control js-stores-indent-qty" value="0">
              <small id="storesIndentQtyHint" class="stores-indent-qty-hint text-muted d-block mt-1" aria-live="polite">&nbsp;</small>
            </div>
            <div class="stores-po-entry-field">
              <label class="control-label">Comments</label>
              <input type="text" class="form-control js-stores-indent-line-comment" maxlength="500" value="">
            </div>
            <div class="stores-po-entry-field stores-po-entry-field--action">
              <label class="control-label">&nbsp;</label>
              <button type="button" class="btn btn-outline-primary btn-block js-stores-indent-add">Add item</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered stores-po-table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Qty</th>
                  <th>Sell price</th>
                  <th>Comments</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody id="storesIndentItemsBody"></tbody>
              <tfoot>
                <tr>
                  <td>Total</td>
                  <td id="storesIndentSumQty">0</td>
                  <td></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer stores-po-modal-footer">
          <button type="button" class="btn btn-outline-danger" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-stores-outline" id="storesIndentSubmit" disabled>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
