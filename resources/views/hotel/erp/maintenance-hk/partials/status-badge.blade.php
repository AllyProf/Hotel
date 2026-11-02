@php
  $badgeClass = match ($status ?? '') {
    'pending', 'open', 'dirty', 'stored' => 'badge-warning',
    'lost' => 'badge-info',
    'found' => 'badge-primary',
    'in_progress', 'waiting_parts' => 'badge-info',
    'completed', 'available', 'cleaned', 'claimed' => 'badge-success',
    'cancelled', 'inactive', 'disposed' => 'badge-secondary',
    'maintenance' => 'badge-danger',
    'rush', 'urgent', 'high' => 'badge-danger',
    default => 'badge-light',
  };
@endphp
<span class="badge {{ $badgeClass }}">{{ $label ?? $status }}</span>
