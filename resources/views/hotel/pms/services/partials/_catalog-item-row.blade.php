@php
  $isTemplate = ! empty($isTemplate);
  $idx = $isTemplate ? '__INDEX__' : $index;
  $rowCurrency = $item?->currency ?? ($defaultCurrency ?? 'TZS');
  $selectedPackageId = $item?->hotel_pms_service_package_id ?? ($defaultPackageId ?? null);
  $namePrefix = $formPrefix ?? 'items';
@endphp
<tr class="js-sv-catalog-item-row">
  <td>
    @if($item && ! $isTemplate)
      <input type="hidden" data-field="id" name="{{ $namePrefix }}[{{ $idx }}][id]" value="{{ $item->id }}">
    @endif
    <input type="text" class="form-control form-control-sm" data-field="item_name"
      name="{{ $namePrefix }}[{{ $idx }}][item_name]" list="{{ $datalistId ?? '' }}" maxlength="120" required
      value="{{ $item?->item_name ?? '' }}" placeholder="e.g. Local Beer">
  </td>
  <td>
    <select class="form-control form-control-sm" data-field="category_id" name="{{ $namePrefix }}[{{ $idx }}][category_id]">
      <option value="">—</option>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}"
          {{ $item && (int) $item->hotel_pms_service_category_id === (int) $cat->id ? 'selected' : '' }}>
          {{ $cat->name }}
        </option>
      @endforeach
    </select>
  </td>
  <td>
    <select class="form-control form-control-sm" data-field="package_id" name="{{ $namePrefix }}[{{ $idx }}][package_id]">
      <option value="">—</option>
      @foreach($packages as $pkg)
        <option value="{{ $pkg->id }}"
          {{ (int) $selectedPackageId === (int) $pkg->id ? 'selected' : '' }}>
          {{ $pkg->name }}@if($pkg->abbreviation) ({{ $pkg->abbreviation }})@endif
        </option>
      @endforeach
    </select>
  </td>
  <td>
    <select class="form-control form-control-sm js-sv-catalog-row-currency" data-field="currency"
      name="{{ $namePrefix }}[{{ $idx }}][currency]">
      @foreach($currencies as $code => $label)
        <option value="{{ $code }}" {{ $rowCurrency === $code ? 'selected' : '' }}>{{ $code }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <input type="number" class="form-control form-control-sm" data-field="unit_price"
      name="{{ $namePrefix }}[{{ $idx }}][unit_price]" step="0.01" min="0"
      value="{{ $item ? $item->unit_price : '0' }}">
  </td>
  <td class="text-center">
    <input type="checkbox" data-field="is_active" name="{{ $namePrefix }}[{{ $idx }}][is_active]" value="1"
      {{ ! $item || $item->is_active ? 'checked' : '' }}>
  </td>
  <td>
    <button type="button" class="btn btn-sm btn-outline-danger js-sv-catalog-remove-item" title="Remove">
      <i class="fa fa-trash"></i>
    </button>
  </td>
</tr>
