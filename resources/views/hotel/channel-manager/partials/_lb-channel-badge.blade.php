@php
  $brand = $brand ?? ['name' => $channel ?? 'OTA', 'label' => $channel ?? 'OTA', 'brand_color' => '#6b7280', 'logo_url' => null, 'initials' => 'OT'];
  $isLive = $isLive ?? false;
@endphp

<span class="lb-channel" style="--lb-brand: {{ $brand['brand_color'] }};" title="{{ $brand['label'] }}">
  @if($isLive)
    <span class="lb-channel__live" title="Received recently"></span>
  @endif
  <span class="lb-channel__chip">
    @if(!empty($brand['logo_url']))
      <img
        class="lb-channel__logo"
        src="{{ $brand['logo_url'] }}"
        alt="{{ $brand['name'] }}"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
      >
    @endif
    <span class="lb-channel__fallback" @if(!empty($brand['logo_url'])) style="display:none;" @endif>{{ $brand['initials'] }}</span>
    <span class="lb-channel__name">{{ $brand['name'] }}</span>
  </span>
</span>
