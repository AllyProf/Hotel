@php
  $itemName = trim((string) ($itemName ?? ''));
  $selectedUnit = trim((string) ($selectedUnit ?? ''));
  $lineIndex = $lineIndex ?? 0;
  $catalogItem = is_array($catalogItem ?? null) ? $catalogItem : null;
  if ($catalogItem === null && $itemName !== '' && isset($deptCatalog) && $deptCatalog instanceof \Illuminate\Support\Collection) {
    $catalogItem = $deptCatalog->get(mb_strtolower($itemName));
  }
  $unitOptions = $catalogItem['unit_options'] ?? [];
  $selectedOptionValue = null;
  if ($selectedUnit !== '' && $unitOptions !== []) {
    foreach ($unitOptions as $opt) {
      if (($opt['unit'] ?? '') === $selectedUnit) {
        $selectedOptionValue = $opt['value'] ?? null;
        break;
      }
    }
  }
@endphp
<input type="hidden" class="js-manual-pl-unit-value" name="lines[{{ $lineIndex }}][unit]" value="{{ $selectedUnit }}">
<select class="form-control js-manual-pl-unit-select"
  @if($itemName === '' || $unitOptions === []) disabled @endif
  aria-label="Unit for {{ $itemName !== '' ? $itemName : 'line item' }}">
  @if($unitOptions === [])
    <option value="">{{ $itemName !== '' ? 'No receiving / usage units on this item' : 'Select item first' }}</option>
  @else
    <option value="">Select unit…</option>
    @foreach($unitOptions as $opt)
      <option value="{{ $opt['value'] ?? '' }}" data-unit="{{ $opt['unit'] ?? '' }}"
        @selected($selectedOptionValue !== null
          ? ($selectedOptionValue === ($opt['value'] ?? ''))
          : ($selectedUnit === ($opt['unit'] ?? '')))>{{ $opt['label'] ?? ($opt['unit'] ?? '') }}</option>
    @endforeach
  @endif
</select>
