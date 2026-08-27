@php
  $hotelsActive = request()->routeIs('admin.hotels.*') || request()->routeIs('admin.plans.*');
  $integrationsActive = request()->routeIs('admin.integrations.*');
@endphp

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <img class="app-sidebar__user-avatar" src="https://s3.amazonaws.com/uifaces/faces/twitter/jsa/48.jpg" alt="{{ auth()->user()->name }}">
    <div class="app-sidebar__user-info">
      <p class="app-sidebar__user-name">{{ auth()->user()->name }}</p>
      <p class="app-sidebar__user-designation">{{ auth()->user()->sidebarDesignation() }}</p>
    </div>
  </div>

  <ul class="app-menu">
    <li>
      <a class="app-menu__item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
        <i class="app-menu__icon fa fa-dashboard"></i>
        <span class="app-menu__label">Dashboard</span>
      </a>
    </li>

    <li class="treeview {{ $hotelsActive ? 'is-expanded' : '' }}">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon fa fa-building"></i>
        <span class="app-menu__label">Hotels</span>
        <i class="treeview-indicator fa fa-angle-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item {{ request()->routeIs('admin.hotels.index') ? 'active' : '' }}" href="{{ route('admin.hotels.index') }}"><i class="icon fa fa-list"></i> All Hotels</a></li>
        <li><a class="treeview-item {{ request()->routeIs('admin.hotels.create') ? 'active' : '' }}" href="{{ route('admin.hotels.create') }}"><i class="icon fa fa-plus"></i> Create Hotel</a></li>
        <li><a class="treeview-item {{ request()->routeIs('admin.plans.index', 'admin.plans.create', 'admin.plans.edit') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}"><i class="icon fa fa-credit-card"></i> Subscriptions</a></li>
      </ul>
    </li>

    <li>
      <a class="app-menu__item {{ $integrationsActive ? 'active' : '' }}" href="{{ route('admin.integrations.index') }}">
        <i class="app-menu__icon fa fa-plug"></i>
        <span class="app-menu__label">Integrations</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-ticket"></i>
        <span class="app-menu__label">Support Tickets</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-money"></i>
        <span class="app-menu__label">Payments</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-heartbeat"></i>
        <span class="app-menu__label">Usage Monitor</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-bar-chart"></i>
        <span class="app-menu__label">Reports</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-map-marker"></i>
        <span class="app-menu__label">Regional Report</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-filter"></i>
        <span class="app-menu__label">Registration Funnel</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-envelope"></i>
        <span class="app-menu__label">Demo Leads</span>
      </a>
    </li>

    <li>
      <a class="app-menu__item" href="#">
        <i class="app-menu__icon fa fa-history"></i>
        <span class="app-menu__label">Activity Logs</span>
      </a>
    </li>
  </ul>
</aside>
