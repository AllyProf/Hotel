<nav class="hotel-settings-tabs">
  @foreach($tabs as $key => $label)
    <a href="{{ route('hotel.settings.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
  @endforeach
</nav>
