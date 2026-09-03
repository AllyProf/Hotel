<div class="rv-stats-row {{ !empty($compact) ? 'rv-stats-row--compact' : '' }}">
  @foreach($statLabels as $key => $label)
    <div class="rv-stat-item">
      <span class="rv-stat-item__label">{{ $label }}</span>
      <span class="rv-stat-item__badge">{{ $stats[$key] ?? 0 }}</span>
    </div>
  @endforeach
</div>
