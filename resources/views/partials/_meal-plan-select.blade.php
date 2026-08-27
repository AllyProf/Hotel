@php
  $mealPlans = config('channel_manager_integration.overview.meal_plans', []);
  $selected = strtoupper(old($name ?? 'meal_plan', $selected ?? 'EP'));
  $inputName = $name ?? 'meal_plan';
  $inputId = $id ?? $inputName;
  $selectClass = trim('form-control '.($class ?? ''));
  $labels = [
    'EP' => 'Room only',
    'CP' => 'With breakfast',
    'MAP' => 'Half board',
    'AP' => 'Full board',
  ];
@endphp

<select class="{{ $selectClass }}" name="{{ $inputName }}" id="{{ $inputId }}">
  @foreach($mealPlans as $plan)
    <option value="{{ $plan['code'] }}" {{ $selected === strtoupper($plan['code']) ? 'selected' : '' }}>
      {{ $labels[$plan['code']] ?? $plan['name'] }}
    </option>
  @endforeach
</select>
