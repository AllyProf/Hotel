@php
  $idx = $index ?? 0;
  $isTemplate = ! empty($isTemplate);
  $namePrefix = $isTemplate ? 'rates[__INDEX__]' : 'rates['.$idx.']';
  $rateId = $rate->id ?? null;
  $categoryId = old($namePrefix.'.category_id', $rate->hotel_pms_service_category_id ?? ($categories->first()?->id));
  $pricingUnit = old($namePrefix.'.pricing_unit', $rate->pricing_unit ?? 'per_day');
  $currency = old($namePrefix.'.currency', $rate->currency ?? $defaultCurrency);
@endphp

<tr class="js-sv-conf-rate-row">
  @if($rateId)
    <input type="hidden" name="{{ $namePrefix }}[id]" value="{{ $rateId }}">
  @endif
  <td>
    <select class="form-control form-control-sm" name="{{ $namePrefix }}[category_id]" required>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}" {{ (int) $categoryId === (int) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <input type="text" class="form-control form-control-sm" name="{{ $namePrefix }}[label]" maxlength="120"
      value="{{ old($namePrefix.'.label', $rate->label ?? '') }}" placeholder="Room / package name">
  </td>
  <td>
    <input type="number" class="form-control form-control-sm" name="{{ $namePrefix }}[capacity]" min="0" max="9999"
      value="{{ old($namePrefix.'.capacity', $rate->capacity ?? 0) }}">
  </td>
  <td>
    <select class="form-control form-control-sm" name="{{ $namePrefix }}[pricing_unit]">
      @foreach($confPricingUnits as $key => $label)
        <option value="{{ $key }}" {{ $pricingUnit === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <select class="form-control form-control-sm" name="{{ $namePrefix }}[currency]">
      @foreach($confCurrencies as $code)
        <option value="{{ $code }}" {{ $currency === $code ? 'selected' : '' }}>{{ $code }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="{{ $namePrefix }}[unit_price]"
      value="{{ old($namePrefix.'.unit_price', $rate->unit_price ?? 0) }}">
  </td>
  <td class="text-center">
    <input type="checkbox" name="{{ $namePrefix }}[is_active]" value="1"
      {{ old($namePrefix.'.is_active', $rate->is_active ?? true) ? 'checked' : '' }}>
  </td>
  <td>
    <button type="button" class="btn btn-sm btn-outline-danger js-sv-conf-remove-rate"><i class="fa fa-trash"></i></button>
  </td>
</tr>
