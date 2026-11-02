@php
  $lineIndex = $lineIndex ?? '__INDEX__';
  $selectedPackageId = (int) ($selectedPackageId ?? 0);
@endphp
<select class="form-control form-control-sm @if(is_numeric($lineIndex)) @error('lines.'.$lineIndex.'.package_id') is-invalid @enderror @endif"
  name="lines[{{ $lineIndex }}][package_id]" required data-placeholder="Select unit…">
  <option value=""></option>
  @foreach($packages as $package)
    <option value="{{ $package->id }}" @selected($selectedPackageId === (int) $package->id)>
      {{ $package->name }}@if($package->abbreviation) ({{ $package->abbreviation }})@endif
    </option>
  @endforeach
</select>
