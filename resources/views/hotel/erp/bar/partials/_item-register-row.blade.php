@php
  $itemIndex = (int) ($itemIndex ?? 0);
  $itemRow = $itemRow ?? [];
  $prefix = 'items['.$itemIndex.']';
  $err = fn (string $field) => 'items.'.$itemIndex.'.'.$field;
  $val = fn (string $field, mixed $default = '') => old($err($field), $itemRow[$field] ?? $default);
  $selectedPackage = (int) $val('package_id', $defaultPackageId ?? 0);
  $receiveUnitLabel = 'unit';
  if ($selectedPackage) {
    $selectedPkg = $packages->firstWhere('id', $selectedPackage);
    $receiveUnitLabel = $selectedPkg?->abbreviation ?: $selectedPkg?->name ?: 'unit';
  }
  $sellRows = old($err('selling_options'), $itemRow['selling_options'] ?? [['package_id' => '', 'pieces_per_sell_unit' => '1']]);
  if (! is_array($sellRows) || $sellRows === []) {
    $sellRows = [['package_id' => '', 'pieces_per_sell_unit' => '1']];
  }
  $canRemove = ($canRemove ?? true);
@endphp

<div class="bar-item-register-row bar-item-details-form" data-item-index="{{ $itemIndex }}">
  <div class="bar-item-register-row__header">
    <span class="bar-item-register-row__title">Item <span class="js-bar-item-row-number">{{ $itemIndex + 1 }}</span></span>
    @if($canRemove)
      <button type="button" class="btn btn-sm btn-link text-danger js-bar-remove-register-item" title="Remove item">
        <i class="fa fa-times"></i> Remove
      </button>
    @endif
  </div>

  <div class="row">
    <div class="col-lg-5">
      <div class="form-group">
        <label class="control-label">Item Name <span class="required-mark">*</span></label>
        <input type="text" class="form-control @error($err('item_name')) is-invalid @enderror" name="{{ $prefix }}[item_name]"
          data-field="item_name" value="{{ $val('item_name') }}" maxlength="120" required
          list="barItemNameSuggestions" placeholder="e.g. Local Beer">
        @error($err('item_name'))<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-group">
        <label class="control-label">Category</label>
        <select class="form-control @error($err('category_id')) is-invalid @enderror" name="{{ $prefix }}[category_id]" data-field="category_id">
          <option value="">Select Category</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((string) $val('category_id') === (string) $category->id)>
              {{ $category->name }}
            </option>
          @endforeach
        </select>
        @error($err('category_id'))<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-group mb-lg-0">
        <label class="control-label">Brand / Manufacturer</label>
        <input type="text" class="form-control @error($err('brand')) is-invalid @enderror" name="{{ $prefix }}[brand]"
          data-field="brand" value="{{ $val('brand') }}" maxlength="120" placeholder="Optional">
        @error($err('brand'))<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="col-lg-7">
      <div class="bar-item-packaging-card">
        <div class="bar-item-packaging-card__title"><i class="fa fa-cubes"></i> Unit &amp; Packaging</div>

        <div class="bar-item-packaging-subtitle">Receiving Package (Purchased as)</div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group mb-md-0">
              <label class="control-label small text-muted mb-1">Unit</label>
              <select class="form-control js-bar-receive-package @error($err('package_id')) is-invalid @enderror"
                name="{{ $prefix }}[package_id]" data-field="package_id" required>
                <option value="">Select unit</option>
                @foreach($packages as $package)
                  @php $pkgLabel = $package->abbreviation ?: $package->name; @endphp
                  <option value="{{ $package->id }}" data-unit-label="{{ $pkgLabel }}"
                    @selected((int) $selectedPackage === (int) $package->id)>{{ $pkgLabel }}</option>
                @endforeach
              </select>
              @error($err('package_id'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group mb-0">
              <label class="control-label js-bar-receive-pieces-label small mb-1">Pieces in 1 {{ $receiveUnitLabel }} <span class="required-mark">*</span></label>
              <input type="number" class="form-control bar-item-no-spinners @error($err('pieces_per_receive_unit')) is-invalid @enderror"
                name="{{ $prefix }}[pieces_per_receive_unit]" data-field="pieces_per_receive_unit"
                step="0.01" min="0.01" required value="{{ $val('pieces_per_receive_unit', '1') }}">
              @error($err('pieces_per_receive_unit'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        @include('hotel.erp.bar.partials._item-sell-options', [
          'namePrefix' => $prefix,
          'itemIndex' => $itemIndex,
          'sellOptionsRows' => $sellRows,
          'formScope' => 'register-'.$itemIndex,
        ])
      </div>
    </div>
  </div>
</div>
