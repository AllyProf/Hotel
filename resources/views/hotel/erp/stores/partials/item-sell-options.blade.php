@php
  $storePackages = $storePackages ?? collect();
  $sellOptionsRows = [['package_id' => '', 'pieces_per_sell_unit' => '1', 'unit_price' => '']];
  $packagesRequired = $storePackages->isNotEmpty();
@endphp

<div class="stores-sell-options js-stores-sell-options">
  <div class="js-stores-sell-options-body">
    @foreach($sellOptionsRows as $sIndex => $sellRow)
      <div class="row js-stores-sell-option-row stores-packaging-row @if(!$loop->first) mt-2 @endif">
        <div class="col-md-4">
          <div class="form-group mb-md-0">
            <label class="control-label js-stores-unit-options-package-label">Usage unit @if($packagesRequired)<span class="text-danger">*</span>@endif</label>
            <select class="form-control js-stores-sell-package"
              name="selling_options[{{ $sIndex }}][package_id]" @if($packagesRequired) required @endif>
              <option value="">Select unit</option>
              @foreach($storePackages as $package)
                <option value="{{ $package->id }}" data-unit-label="{{ $package->unitLabel() }}"
                  @selected((string) ($sellRow['package_id'] ?? '') === (string) $package->id)>{{ $package->displayLabel() }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group mb-md-0 stores-packaging-pieces-field">
            <label class="control-label js-stores-unit-options-pieces-label">Pieces per usage unit @if($packagesRequired)<span class="text-danger">*</span>@endif</label>
            <input type="number" class="form-control stores-no-spinners"
              name="selling_options[{{ $sIndex }}][pieces_per_sell_unit]"
              step="0.01" min="0.01" value="{{ $sellRow['pieces_per_sell_unit'] ?? 1 }}" @if($packagesRequired) required @endif>
          </div>
        </div>
        <div class="col-md-3 js-stores-sell-price-wrap">
          <div class="form-group mb-md-0">
            <label class="control-label js-stores-unit-options-price-label">Sell price</label>
            <input type="number" class="form-control stores-no-spinners js-stores-sell-unit-price"
              name="selling_options[{{ $sIndex }}][unit_price]"
              step="0.01" min="0" value="{{ $sellRow['unit_price'] ?? '' }}" placeholder="0">
            <p class="text-muted small mb-0 mt-1 js-stores-sell-price-hint"></p>
          </div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-link text-danger p-0 mb-3 js-stores-remove-sell-option stores-packaging-remove-btn" title="Remove"
            @if(count($sellOptionsRows) <= 1) disabled @endif>
            <i class="fa fa-times fa-lg"></i>
          </button>
        </div>
      </div>
    @endforeach
  </div>

  <div class="text-right mt-2 js-stores-sell-add-wrap" style="display: none;">
    <button type="button" class="btn btn-sm btn-outline-secondary js-stores-add-sell-option">
      <i class="fa fa-plus"></i> Add sale unit
    </button>
  </div>

  <template class="js-stores-sell-option-template">
    <div class="row js-stores-sell-option-row stores-packaging-row mt-2">
      <div class="col-md-4">
        <div class="form-group mb-md-0">
          <label class="control-label js-stores-unit-options-package-label">Usage unit</label>
          <select class="form-control js-stores-sell-package" data-field="package_id">
            <option value="">Select unit</option>
            @foreach($storePackages as $package)
              <option value="{{ $package->id }}" data-unit-label="{{ $package->unitLabel() }}">{{ $package->displayLabel() }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group mb-md-0 stores-packaging-pieces-field">
          <label class="control-label js-stores-unit-options-pieces-label">Pieces per usage unit</label>
          <input type="number" class="form-control stores-no-spinners" data-field="pieces_per_sell_unit"
            step="0.01" min="0.01" value="1">
        </div>
      </div>
      <div class="col-md-3 js-stores-sell-price-wrap">
        <div class="form-group mb-md-0">
          <label class="control-label js-stores-unit-options-price-label">Sell price</label>
          <input type="number" class="form-control stores-no-spinners js-stores-sell-unit-price" data-field="unit_price"
            step="0.01" min="0" value="" placeholder="0">
          <p class="text-muted small mb-0 mt-1 js-stores-sell-price-hint"></p>
        </div>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="button" class="btn btn-link text-danger p-0 mb-3 js-stores-remove-sell-option stores-packaging-remove-btn" title="Remove">
          <i class="fa fa-times fa-lg"></i>
        </button>
      </div>
    </div>
  </template>
</div>
