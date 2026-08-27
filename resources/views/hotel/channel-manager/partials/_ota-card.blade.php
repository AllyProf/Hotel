@php
  $initials = collect(explode(' ', $ota['name']))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->implode('');
  $connection = $connection ?? [];
  $isMapped = !empty($connection['enabled']) && trim((string) ($connection['hotel_code'] ?? '')) !== '';
@endphp

<div class="ota-card js-ota-card" data-slug="{{ $ota['slug'] }}" style="--ota-brand: {{ $ota['brand_color'] }}">
  <div class="ota-card__head">
    <div class="ota-card__brand">
      <img
        class="ota-card__logo"
        src="{{ $ota['logo_url'] ?? asset('panel-assets/img/otas/' . ($ota['logo'] ?? '')) }}"
        alt="{{ $ota['name'] }}"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
      >
      <span class="ota-card__logo-fallback">{{ $initials }}</span>
      <span class="ota-card__name">{{ $ota['name'] }}</span>
    </div>
    @if($isMapped)
      <span class="badge badge-success ota-card__badge">Connected</span>
    @else
      <span class="badge badge-secondary ota-card__badge">Not set up</span>
    @endif
  </div>

  <div class="ota-card__controls">
    <label class="toggle-switch mb-0" title="Enable {{ $ota['name'] }}">
      <input type="checkbox" class="js-ota-enabled" {{ !empty($connection['enabled']) ? 'checked' : '' }}>
      <span></span>
    </label>
  </div>

  <div class="ota-card__body">
    <button type="button" class="btn btn-secondary btn-sm btn-block mb-2">Get your hotel code</button>

    <input type="text"
      class="form-control form-control-sm mb-2 js-ota-hotel-code"
      placeholder="Hotel Code"
      value="{{ $connection['hotel_code'] ?? '' }}">

    <button type="button" class="btn btn-secondary btn-sm btn-block mb-3 js-ota-fetch">Fetch Hotel Info</button>

    <div class="ota-card__fetch-area js-ota-fetch-area">
      @if($isMapped)
        <i class="fa fa-check-circle text-success"></i>
        <div class="mt-2">Connected · {{ $connection['hotel_code'] }}</div>
      @else
        <i class="fa fa-cloud-download"></i>
        <div>Fetch to map</div>
      @endif
    </div>

    <div class="form-group mb-2">
      <label class="control-label small mb-1">Currency</label>
      <select class="form-control form-control-sm js-ota-currency">
        @foreach(['USD', 'INR', 'EUR', 'GBP', 'TZS'] as $currency)
          <option value="{{ $currency }}" {{ ($connection['currency'] ?? 'USD') === $currency ? 'selected' : '' }}>{{ $currency }}</option>
        @endforeach
      </select>
    </div>

    <div class="form-group mb-3">
      <label class="control-label small mb-1">Rate Multiplier</label>
      <div class="ota-stepper js-ota-stepper">
        <button type="button" class="ota-step-minus">−</button>
        <input type="text" class="js-ota-multiplier" value="{{ number_format((float) ($connection['rate_multiplier'] ?? 1), 1, '.', '') }}" readonly>
        <button type="button" class="ota-step-plus">+</button>
      </div>
    </div>

    <button type="button" class="btn btn-primary btn-block js-ota-submit">Submit Mapping</button>
  </div>
</div>
