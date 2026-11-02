@php
  $name = $name ?? 'currency';
  $allowEmpty = ! empty($allowEmpty);
  $defaultSelected = $allowEmpty ? '' : ($hotel->currency ?? 'TZS');
  $selected = strtoupper(trim((string) old($name, $selected ?? $defaultSelected)));
  $id = $id ?? preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
  $class = trim('form-control select2-currency '.($class ?? ''));
@endphp

<select class="{{ $class }}" name="{{ $name }}" id="{{ $id }}" @if(!empty($required)) required @endif>
  @if($allowEmpty)
    <option value="" @selected($selected === '')>{{ $emptyLabel ?? '— Automatic —' }}</option>
  @endif
  @foreach(config('currencies', []) as $code => $label)
    <option value="{{ $code }}" {{ $selected === $code ? 'selected' : '' }}>{{ $code }} — {{ $label }}</option>
  @endforeach
</select>
