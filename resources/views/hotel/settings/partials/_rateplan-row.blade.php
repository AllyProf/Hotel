@php
  $planId = data_get($plan, 'id');
  $formatPrice = function ($value) {
      if ($value === null || $value === '') {
          return '';
      }
      $amount = (float) $value;

      return fmod($amount, 1.0) === 0.0 ? (string) (int) $amount : rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
  };
  $localPrice = old("rateplans.{$index}.local_base_rate", data_get($plan, 'local_base_rate', ''));
  $intlPrice = old("rateplans.{$index}.base_rate", data_get($plan, 'base_rate', ''));
@endphp
<tr class="rateplan-row {{ empty($planId) ? 'rateplan-row--new' : '' }}" data-index="{{ $index }}">
  <td class="text-center align-middle">
    <input type="hidden" name="rateplans[{{ $index }}][_delete]" value="0" class="rateplan-delete-flag">
    @if(!empty($planId))
      <input type="hidden" name="rateplans[{{ $index }}][id]" value="{{ $planId }}">
    @endif
    <button type="button" class="btn btn-outline-danger btn-sm rateplan-remove-row" title="Remove"><i class="fa fa-minus"></i></button>
  </td>
  <td>
    <select class="form-control form-control-sm" name="rateplans[{{ $index }}][hotel_room_id]" required>
      @foreach($rooms as $roomOption)
        <option value="{{ $roomOption->id }}" {{ (int) old("rateplans.{$index}.hotel_room_id", data_get($plan, 'hotel_room_id', 0)) === (int) $roomOption->id ? 'selected' : '' }}>
          {{ $roomOption->name }}
        </option>
      @endforeach
    </select>
  </td>
  <td>
    @include('partials._meal-plan-select', [
      'name' => "rateplans[{$index}][meal_plan]",
      'selected' => data_get($plan, 'meal_plan', 'EP'),
      'class' => 'form-control-sm js-meal-plan-select',
    ])
  </td>
  <td>
    <input class="form-control form-control-sm" type="number" step="1" min="0" name="rateplans[{{ $index }}][local_base_rate]" value="{{ $formatPrice($localPrice) }}" placeholder="0">
  </td>
  <td>
    @include('partials._currency-select', [
      'name' => "rateplans[{$index}][local_currency]",
      'selected' => data_get($plan, 'local_currency', $hotel->currency ?? 'TZS'),
      'class' => 'form-control-sm',
    ])
  </td>
  <td>
    <input class="form-control form-control-sm" type="number" step="1" min="0" name="rateplans[{{ $index }}][base_rate]" value="{{ $formatPrice($intlPrice) }}" placeholder="0">
  </td>
  <td>
    @include('partials._currency-select', [
      'name' => "rateplans[{$index}][international_currency]",
      'selected' => data_get($plan, 'international_currency', 'USD'),
      'class' => 'form-control-sm',
    ])
  </td>
</tr>
