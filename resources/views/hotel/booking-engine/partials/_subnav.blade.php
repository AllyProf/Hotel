@php
  $navItems = $ui['nav'] ?? config('hotel_booking_engine.nav', []);
@endphp
<div class="be-subnav mb-4">
  @foreach($navItems as $item)
    @php
      $active = ! empty($item['route_is']) && request()->routeIs($item['route_is']);
    @endphp
    <a href="{{ route($item['route']) }}" class="be-subnav__link {{ $active ? 'is-active' : '' }}">
      {{ $item['label'] }}
    </a>
  @endforeach
</div>
