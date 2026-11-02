@php
  $inputClass = $inputClass ?? 'form-control form-control-sm';
  $lineIndex = $lineIndex ?? '__INDEX__';
  $selectedUnit = trim((string) ($selectedUnit ?? ''));
  $knownValues = $packages->map(fn ($p) => trim((string) ($p->abbreviation ?: $p->name)))->filter()->values();
  $hasCustomUnit = $selectedUnit !== '' && ! $knownValues->contains(fn ($v) => strcasecmp($v, $selectedUnit) === 0);
@endphp
<select class="{{ $inputClass }}" name="lines[{{ $lineIndex }}][unit]">
  <option value="">— Unit —</option>
  @if($hasCustomUnit)
    <option value="{{ $selectedUnit }}" selected>{{ $selectedUnit }}</option>
  @endif
  @foreach($packages as $package)
    @php
      $unitValue = trim((string) ($package->abbreviation ?: $package->name));
    @endphp
    @if($unitValue !== '')
      <option value="{{ $unitValue }}" @selected(strcasecmp($selectedUnit, $unitValue) === 0)>
        {{ $package->name }}@if($package->abbreviation) ({{ $package->abbreviation }})@endif
      </option>
    @endif
  @endforeach
</select>
