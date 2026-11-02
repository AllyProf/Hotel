@php
  $isLive = $isLive ?? false;
  $label = $label ?? '—';
  $badgeClass = $badgeClass ?? 'lb-source-neutral';
@endphp

<span class="lb-channel-cell">
  @if($isLive)
    <span class="lb-live-dot" title="Received recently"></span>
  @endif
  <span class="lb-source {{ $badgeClass }}">{{ $label }}</span>
</span>
