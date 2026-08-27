@php
  $cm = config('hotel_dashboard.channel_manager');
  $otas = config('otas', []);
  $statusKey = $cmStatus['status'] ?? 'platform_pending';
  $badgeClass = match ($statusKey) {
    'active' => 'badge-success',
    'pending' => 'badge-warning',
    default => 'badge-secondary',
  };
@endphp

<div class="tile hotel-dash-card" id="cm-integration">
  <div class="tile-title-w-btn">
    <h3 class="tile-title"><i class="fa fa-exchange"></i> {{ $cm['title'] }}</h3>
    <span class="badge {{ $badgeClass }}">{{ $cmStatus['label'] ?? 'Pending' }}</span>
  </div>
  <div class="tile-body">
    <p class="text-muted mb-3">{{ $cm['intro'] }}</p>
    <p class="mb-4">{{ $cm['status'][$statusKey] ?? '' }}</p>

    <div class="row mb-4">
      @foreach($cm['quick_links'] as $link)
        @if(Route::has($link['route']))
          <div class="col-md-4 mb-2">
            <a href="{{ route($link['route']) }}" class="btn btn-outline-primary btn-block">
              <i class="{{ $link['icon'] }}"></i> {{ $link['label'] }}
            </a>
          </div>
        @endif
      @endforeach
    </div>

    <h5 class="font-weight-bold mb-3">Get started in 3 steps</h5>
    @foreach($cm['steps'] as $index => $step)
      <div class="hotel-start-step">
        <span class="hotel-start-step__num">{{ $index + 1 }}</span>
        <div class="flex-grow-1">
          <h5 class="mb-1">{{ $step['title'] }}</h5>
          <p class="text-muted mb-2">{{ $step['body'] }}</p>
          @if(!empty($step['route']) && Route::has($step['route']))
            <a class="btn btn-primary btn-sm" href="{{ route($step['route'], $step['params'] ?? []) }}">{{ $step['label'] }}</a>
          @endif
        </div>
      </div>
    @endforeach

    <div class="mt-4">
      <h5 class="font-weight-bold mb-3"><i class="fa fa-globe"></i> Your connected channels</h5>
      <div class="row">
        @foreach($otas as $ota)
          <div class="col-6 col-md-4 col-lg-3 mb-2">
            <div class="integration-ota-chip">
              <img src="{{ asset('panel-assets/img/otas/'.$ota['logo']) }}" alt="{{ $ota['name'] }}">
              <span>{{ $ota['name'] }}</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
