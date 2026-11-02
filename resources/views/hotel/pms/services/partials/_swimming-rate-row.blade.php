@php
  $isTemplate = ! empty($isTemplate);
  $idx = $isTemplate ? '__INDEX__' : $index;
  $rowCurrency = $rate?->currency ?? ($defaultCurrency ?? 'TZS');
  if (! in_array($rowCurrency, $swimCurrencies, true)) {
      $rowCurrency = 'TZS';
  }
@endphp
<tr class="js-sv-swim-rate-row">
  <td>
    @if($rate && ! $isTemplate)
      <input type="hidden" data-field="id" name="rates[{{ $idx }}][id]" value="{{ $rate->id }}">
    @endif
    <select class="form-control form-control-sm" data-field="category_id" name="rates[{{ $idx }}][category_id]" required>
      <option value="">Pass type…</option>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}"
          {{ $rate && (int) $rate->hotel_pms_service_category_id === (int) $cat->id ? 'selected' : '' }}>
          {{ $cat->name }}
        </option>
      @endforeach
    </select>
  </td>
  <td>
    <select class="form-control form-control-sm" data-field="audience" name="rates[{{ $idx }}][audience]">
      @foreach($swimAudiences as $key => $label)
        <option value="{{ $key }}" {{ ($rate?->audience ?? 'adult') === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <select class="form-control form-control-sm" data-field="pricing_unit" name="rates[{{ $idx }}][pricing_unit]">
      @foreach($swimPricingUnits as $key => $label)
        <option value="{{ $key }}" {{ ($rate?->pricing_unit ?? 'per_person') === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <input type="text" class="form-control form-control-sm" data-field="label" name="rates[{{ $idx }}][label]"
      maxlength="120" value="{{ $rate?->label ?? '' }}" placeholder="Optional display name">
  </td>
  <td>
    <select class="form-control form-control-sm js-sv-swim-row-currency" data-field="currency" name="rates[{{ $idx }}][currency]">
      @foreach($swimCurrencies as $code)
        <option value="{{ $code }}" {{ $rowCurrency === $code ? 'selected' : '' }}>{{ $code }}</option>
      @endforeach
    </select>
  </td>
  <td>
    <input type="number" class="form-control form-control-sm" data-field="unit_price" name="rates[{{ $idx }}][unit_price]"
      step="0.01" min="0" value="{{ $rate ? $rate->unit_price : '0' }}">
  </td>
  <td class="text-center">
    <input type="checkbox" data-field="is_active" name="rates[{{ $idx }}][is_active]" value="1"
      {{ ! $rate || $rate->is_active ? 'checked' : '' }}>
  </td>
  <td>
    <button type="button" class="btn btn-sm btn-outline-danger js-sv-swim-remove-rate" title="Remove">
      <i class="fa fa-trash"></i>
    </button>
  </td>
</tr>
