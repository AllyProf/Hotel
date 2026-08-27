<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar hotel-sidebar">
  <div class="hotel-sidebar__brand">
    <span class="hotel-sidebar__brand-title">{{ config('app.name', 'Hotel SaaS') }}</span>
  </div>

  <div class="hotel-sidebar__property">
    <div class="hotel-sidebar__property-logo">
      <span>{{ strtoupper(substr($hotel?->name ?? 'H', 0, 1)) }}</span>
    </div>
    <div class="hotel-sidebar__property-info">
      <p class="hotel-sidebar__property-name">{{ strtoupper($hotel?->name ?? 'Hotel') }}</p>
      <p class="hotel-sidebar__property-city">{{ strtoupper($hotel?->city ?? $hotel?->country ?? '—') }}</p>
    </div>
  </div>

  <ul class="app-menu hotel-sidebar__menu">
    @forelse($sidebarGroups as $group)
      <li class="treeview {{ $group['expanded'] ? 'is-expanded' : '' }}">
        <a class="app-menu__item {{ $group['active'] ? 'active' : '' }}" href="#" data-toggle="treeview">
          <i class="app-menu__icon {{ $group['icon'] }}"></i>
          <span class="app-menu__label">{{ $group['label'] }}</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          @foreach($group['items'] as $item)
            <li>
              <a class="treeview-item {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}">
                <i class="icon {{ $item['icon'] }}"></i> {{ $item['label'] }}
              </a>
            </li>
          @endforeach
        </ul>
      </li>
    @empty
      <li>
        <span class="app-menu__item hotel-sidebar__empty">
          <i class="app-menu__icon fa fa-info-circle"></i>
          <span class="app-menu__label">No modules enabled</span>
        </span>
      </li>
    @endforelse
  </ul>
</aside>
