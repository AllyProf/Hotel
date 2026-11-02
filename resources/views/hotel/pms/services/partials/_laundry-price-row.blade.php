@php
  $isTemplate = ! empty($isTemplate);
  $idx = $isTemplate ? '__INDEX__' : $index;
  $rowCurrency = $price?->currency ?? ($defaultCurrency ?? 'TZS');
  $selectedPackageId = $price?->hotel_pms_service_package_id ?? ($defaultPackageId ?? null);
@endphp
<tr class="js-sv-laundry-price-row">
  <td>
    @if($price && ! $isTemplate)
      <input type="hidden" data-field="id" name="prices[{{ $idx }}][id]" value="{{ $price->id }}">
    @endif
    <input type="text" class="form-control form-control-sm" data-field="item_name"
      name="prices[{{ $idx }}][item_name]" list="svLaundryGarmentList" maxlength="120" required
      value="{{ $price?->item_name ?? '' }}" placeholder="e.g. Shirt">
  </td>
  <td>
    <select class="form-control form-control-sm" data-field="category_id" name="prices[{{ $idx }}][category_id]">
      <option value="">—</option>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}"
          {{ $price && (int) $price->hotel_pms_service_category_id === (int) $cat->id ? 'selected' : '' }}>
          {{ $cat->name }}
        </option>
      @endforeach
    </select>
  </td>
  <td>
    <select class="form-control form-control-sm" data-field="package_id" name="prices[{{ $idx }}][package_id]">
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
    <select class="form-control form-control-sm js-sv-laundry-row-currency" data-field="currency"
      name="prices[{{ $idx }}][currency]">
      @foreach($currencies as $code => $label)
        <option value="{{ $code }}" {{ $rowCurrency === $code ? 'selected' : '' }}>{{ $code }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <input type="number" class="form-control form-control-sm" data-field="unit_price"
      name="prices[{{ $idx }}][unit_price]" step="0.01" min="0"
      value="{{ $price ? $price->unit_price : '0' }}">
  </td>
  <td class="text-center">
    <input type="checkbox" data-field="is_active" name="prices[{{ $idx }}][is_active]" value="1"
      {{ ! $price || $price->is_active ? 'checked' : '' }}>
  </td>
  <td>
    <button type="button" class="btn btn-sm btn-outline-danger js-sv-laundry-remove-price" title="Remove">
      <i class="fa fa-trash"></i>
    </button>
  </td>
</tr>
