@php
  $namePrefix = $namePrefix ?? '';
  $itemIndex = $itemIndex ?? null;
  $sellPrefix = $namePrefix !== '' ? $namePrefix.'[selling_options]' : 'selling_options';
  $sellOptionsRows = $sellOptionsRows ?? null;

  if ($sellOptionsRows === null) {
    $sellOptionsRows = old('selling_options');
    if ($sellOptionsRows === null && isset($item) && $item) {
      $item->loadMissing('sellOptions');
      $sellOptionsRows = $item->sellOptions->map(fn ($option) => [
        'package_id' => $option->hotel_pms_service_package_id,
        'pieces_per_sell_unit' => $option->pieces_per_sell_unit ?? 1,
        'unit_price' => $option->unit_price,
      ])->values()->all();
      if ($sellOptionsRows === [] && $item->selling_hotel_pms_service_package_id) {
        $sellOptionsRows = [[
          'package_id' => $item->selling_hotel_pms_service_package_id,
          'pieces_per_sell_unit' => 1,
          'unit_price' => $item->unit_price,
        ]];
      }
    }
  }
  if (! is_array($sellOptionsRows) || $sellOptionsRows === []) {
    $sellOptionsRows = [['package_id' => '', 'pieces_per_sell_unit' => '1', 'unit_price' => '']];
  }
  $formScope = $formScope ?? 'default';
  $sellError = function (int $sIndex, string $field) use ($itemIndex, $namePrefix): string {
    if ($itemIndex !== null && $namePrefix !== '') {
      return 'items.'.$itemIndex.'.selling_options.'.$sIndex.'.'.$field;
    }

    return 'selling_options.'.$sIndex.'.'.$field;
  };
@endphp

<div class="bar-item-sell-options mt-3" data-form-scope="{{ $formScope }}" data-sell-prefix="{{ $sellPrefix }}">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="bar-item-packaging-subtitle mb-0">Selling Package (Sold as)</div>
    <button type="button" class="btn btn-sm btn-outline-danger js-bar-add-sell-option">
      <i class="fa fa-plus"></i> Add
    </button>
  </div>

  <div class="js-bar-sell-options-body">
    @foreach($sellOptionsRows as $sIndex => $sellRow)
      @php
        $sellPkgId = (int) ($sellRow['package_id'] ?? 0);
        $sellPkg = $sellPkgId ? $packages->firstWhere('id', $sellPkgId) : null;
        $sellUnitLabel = $sellPkg?->abbreviation ?: $sellPkg?->name ?: 'unit';
      @endphp
      <div class="row js-bar-sell-option-row align-items-end">
        <div class="col-sm-4">
          <label class="control-label small mb-1">Sale unit</label>
          <select class="form-control form-control-sm js-bar-sell-package @error($sellError($sIndex, 'package_id')) is-invalid @enderror"
            name="{{ $sellPrefix }}[{{ $sIndex }}][package_id]" data-field="package_id">
            <option value="">Select unit</option>
            @foreach($packages as $package)
              @php $pkgLabel = $package->abbreviation ?: $package->name; @endphp
              <option value="{{ $package->id }}" data-unit-label="{{ $pkgLabel }}"
                @selected((string) ($sellRow['package_id'] ?? '') === (string) $package->id)>{{ $pkgLabel }}</option>
            @endforeach
          </select>
          @error($sellError($sIndex, 'package_id'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-3">
          <label class="control-label small mb-1">Pieces per sale unit</label>
          <input type="number" class="form-control form-control-sm bar-item-no-spinners @error($sellError($sIndex, 'pieces_per_sell_unit')) is-invalid @enderror"
            name="{{ $sellPrefix }}[{{ $sIndex }}][pieces_per_sell_unit]" data-field="pieces_per_sell_unit"
            step="0.01" min="0.01" value="{{ $sellRow['pieces_per_sell_unit'] ?? 1 }}">
          <span class="js-bar-sell-unit-hint">piece in 1 {{ $sellUnitLabel }}</span>
          @error($sellError($sIndex, 'pieces_per_sell_unit'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-3">
          <label class="control-label small mb-1">Sell price</label>
          <input type="number" class="form-control form-control-sm bar-item-no-spinners @error($sellError($sIndex, 'unit_price')) is-invalid @enderror"
            name="{{ $sellPrefix }}[{{ $sIndex }}][unit_price]" data-field="unit_price"
            step="0.01" min="0" value="{{ $sellRow['unit_price'] ?? '' }}" placeholder="0">
          @error($sellError($sIndex, 'unit_price'))<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-2 text-right pb-1">
          <button type="button" class="btn btn-sm btn-link text-danger p-0 js-bar-remove-sell-option" title="Remove"
            @if(count($sellOptionsRows) <= 1) disabled @endif>
            <i class="fa fa-times fa-lg"></i>
          </button>
        </div>
      </div>
    @endforeach
  </div>

  @if($itemIndex === null)
    @error('selling_options')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
  @endif

  <template class="js-bar-sell-option-template">
    <div class="row js-bar-sell-option-row align-items-end">
      <div class="col-sm-4">
        <label class="control-label small mb-1">Sale unit</label>
        <select class="form-control form-control-sm js-bar-sell-package" data-field="package_id">
          <option value="">Select unit</option>
          @foreach($packages as $package)
            @php $pkgLabel = $package->abbreviation ?: $package->name; @endphp
            <option value="{{ $package->id }}" data-unit-label="{{ $pkgLabel }}">{{ $pkgLabel }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-3">
        <label class="control-label small mb-1">Pieces per sale unit</label>
        <input type="number" class="form-control form-control-sm bar-item-no-spinners" data-field="pieces_per_sell_unit"
          step="0.01" min="0.01" value="1">
        <span class="js-bar-sell-unit-hint">piece in 1 unit</span>
      </div>
      <div class="col-sm-3">
        <label class="control-label small mb-1">Sell price</label>
        <input type="number" class="form-control form-control-sm bar-item-no-spinners" data-field="unit_price"
          step="0.01" min="0" value="" placeholder="0">
      </div>
      <div class="col-sm-2 text-right pb-1">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 js-bar-remove-sell-option" title="Remove">
          <i class="fa fa-times fa-lg"></i>
        </button>
      </div>
    </div>
  </template>
</div>
