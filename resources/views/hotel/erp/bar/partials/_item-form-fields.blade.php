@php
  $item = $item ?? null;
  $field = fn (string $name) => old($name, $item ? match ($name) {
    'item_name' => $item->item_name,
    'brand' => $item->brand,
    'category_id' => $item->hotel_pms_service_category_id,
    'package_id' => $item->hotel_pms_service_package_id,
    'pieces_per_receive_unit' => $item->pieces_per_receive_unit,
    'stock_on_hand' => $item->stock_on_hand,
    'reorder_level' => $item->reorder_level,
    'expected_unit_price' => $item->expected_unit_price,
    default => null,
  } : null);
  $isActive = old('is_active', $item ? $item->is_active : true);
  $selectedPackage = (int) ($field('package_id') ?? 0) ?: ($defaultPackageId ?? null);
  $piecesPerReceive = old('pieces_per_receive_unit', $field('pieces_per_receive_unit') ?? '1');
  $receiveUnitLabel = 'unit';
  if ($selectedPackage) {
    $selectedPkg = $packages->firstWhere('id', $selectedPackage);
    $receiveUnitLabel = $selectedPkg?->abbreviation ?: $selectedPkg?->name ?: 'unit';
  }
  $formScope = $formScope ?? ($item ? 'edit-'.$item->id : 'register');
@endphp

<div class="bar-item-details-form">
  <div class="row">
    <div class="col-lg-5">
      <div class="form-group">
        <label class="control-label">Item Name <span class="required-mark">*</span></label>
        <input type="text" class="form-control @error('item_name') is-invalid @enderror" name="item_name"
          value="{{ $field('item_name') }}" maxlength="120" required
          list="barItemNameSuggestions" placeholder="e.g. Local Beer">
        @error('item_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-group">
        <label class="control-label">Category</label>
        <select class="form-control @error('category_id') is-invalid @enderror" name="category_id">
          <option value="">Select Category</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ (string) $field('category_id') === (string) $category->id ? 'selected' : '' }}>
              {{ $category->name }}
            </option>
          @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-group mb-lg-0">
        <label class="control-label">Brand / Manufacturer</label>
        <input type="text" class="form-control @error('brand') is-invalid @enderror" name="brand"
          value="{{ $field('brand') }}" maxlength="120" placeholder="Optional">
        @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
              <select class="form-control js-bar-receive-package @error('package_id') is-invalid @enderror" name="package_id" required>
                <option value="">Select unit</option>
                @foreach($packages as $package)
                  @php $pkgLabel = $package->abbreviation ?: $package->name; @endphp
                  <option value="{{ $package->id }}" data-unit-label="{{ $pkgLabel }}"
                    {{ (int) $selectedPackage === (int) $package->id ? 'selected' : '' }}>{{ $pkgLabel }}</option>
                @endforeach
              </select>
              @error('package_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group mb-0">
              <label class="control-label js-bar-receive-pieces-label small mb-1">Pieces in 1 {{ $receiveUnitLabel }} <span class="required-mark">*</span></label>
              <input type="number" class="form-control bar-item-no-spinners @error('pieces_per_receive_unit') is-invalid @enderror"
                name="pieces_per_receive_unit" step="0.01" min="0.01" required value="{{ $piecesPerReceive }}">
              @error('pieces_per_receive_unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        @include('hotel.erp.bar.partials._item-sell-options', ['formScope' => $formScope, 'item' => $item])
      </div>
    </div>
  </div>

  <details class="mt-3">
    <summary class="text-muted small" style="cursor:pointer;">Inventory &amp; pricing (optional)</summary>
    <div class="row mt-3">
      <div class="col-md-6">
        <div class="form-group">
          <label class="control-label">Expected unit price (procurement)</label>
          <input type="number" class="form-control bar-item-no-spinners @error('expected_unit_price') is-invalid @enderror" name="expected_unit_price"
            step="0.01" min="0" value="{{ $field('expected_unit_price') }}" placeholder="Optional">
          @error('expected_unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label class="control-label">Stock on hand</label>
          <input type="number" class="form-control bar-item-no-spinners @error('stock_on_hand') is-invalid @enderror" name="stock_on_hand"
            step="0.01" min="0" value="{{ $field('stock_on_hand') }}" placeholder="Optional">
          @error('stock_on_hand')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label class="control-label">Reorder level</label>
          <input type="number" class="form-control bar-item-no-spinners @error('reorder_level') is-invalid @enderror" name="reorder_level"
            step="0.01" min="0" value="{{ $field('reorder_level') }}" placeholder="Optional">
          @error('reorder_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="col-md-12">
        <label class="animated-checkbox">
          <input type="checkbox" name="is_active" value="1" {{ $isActive ? 'checked' : '' }}>
          <span class="label-text">Active — available for sale</span>
        </label>
      </div>
    </div>
  </details>
</div>
