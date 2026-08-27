@php
  $name = $name ?? 'currency';
  $selected = old($name, $selected ?? ($hotel->currency ?? 'USD'));
  $id = $id ?? preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
  $class = trim('form-control select2-currency '.($class ?? ''));
@endphp

<select class="{{ $class }}" name="{{ $name }}" id="{{ $id }}" @if(!empty($required)) required @endif>
  @foreach(config('currencies', []) as $code => $label)
    <option value="{{ $code }}" {{ $selected === $code ? 'selected' : '' }}>{{ $label }}</option>
  @endforeach
</select>
