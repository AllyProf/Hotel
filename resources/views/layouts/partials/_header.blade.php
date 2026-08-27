<header class="app-header">
  <a class="app-header__logo" href="{{ route('dashboard') }}">{{ config('app.name', 'Hotel SaaS') }}</a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
  <ul class="app-nav">
    @if(!empty($showBranchSwitcher) && $activeBranch)
      <li class="dropdown branch-switcher">
        <a class="app-nav__item branch-switcher__toggle" href="#" data-toggle="dropdown" aria-label="Switch branch">
          <i class="fa fa-sitemap fa-lg"></i>
          <span class="branch-switcher__label d-none d-md-inline">{{ $activeBranch->name }}</span>
          <i class="fa fa-caret-down branch-switcher__caret d-none d-md-inline"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-right branch-switcher__menu">
          <li class="dropdown-header">Switch branch</li>
          @foreach($switcherBranches as $branch)
            <li>
              @if($branch->id === $activeBranch->id)
                <span class="dropdown-item branch-switcher__item is-active">
                  <i class="fa fa-check branch-switcher__check"></i>
                  <span>
                    {{ $branch->name }}
                    @if($branch->is_headquarters)
                      <small class="d-block text-muted">Headquarters</small>
                    @endif
                  </span>
                </span>
              @else
                <form action="{{ route('hotel.branches.switch') }}" method="POST">
                  @csrf
                  <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                  <button type="submit" class="dropdown-item branch-switcher__item">
                    <span>
                      {{ $branch->name }}
                      @if($branch->is_headquarters)
                        <small class="d-block text-muted">Headquarters</small>
                      @endif
                    </span>
                  </button>
                </form>
              @endif
            </li>
          @endforeach
          <li class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item branch-switcher__manage" href="{{ route('hotel.branches.index') }}">
              <i class="fa fa-cog"></i> Manage branches
            </a>
          </li>
        </ul>
      </li>
    @endif
    <li class="app-search">
      <input class="app-search__input" type="search" placeholder="Search">
      <button class="app-search__button"><i class="fa fa-search"></i></button>
    </li>
    <li class="dropdown">
      <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Show notifications">
        <i class="fa fa-bell-o fa-lg"></i>
      </a>
      <ul class="app-notification dropdown-menu dropdown-menu-right">
        <li class="app-notification__title">You have 4 new notifications.</li>
        <div class="app-notification__content">
          <li>
            <a class="app-notification__item" href="javascript:;">
              <span class="app-notification__icon">
                <span class="fa-stack fa-lg">
                  <i class="fa fa-circle fa-stack-2x text-primary"></i>
                  <i class="fa fa-envelope fa-stack-1x fa-inverse"></i>
                </span>
              </span>
              <div>
                <p class="app-notification__message">New reservation received</p>
                <p class="app-notification__meta">2 min ago</p>
              </div>
            </a>
          </li>
          <li>
            <a class="app-notification__item" href="javascript:;">
              <span class="app-notification__icon">
                <span class="fa-stack fa-lg">
                  <i class="fa fa-circle fa-stack-2x text-danger"></i>
                  <i class="fa fa-bed fa-stack-1x fa-inverse"></i>
                </span>
              </span>
              <div>
                <p class="app-notification__message">Room 204 needs maintenance</p>
                <p class="app-notification__meta">5 min ago</p>
              </div>
            </a>
          </li>
          <li>
            <a class="app-notification__item" href="javascript:;">
              <span class="app-notification__icon">
                <span class="fa-stack fa-lg">
                  <i class="fa fa-circle fa-stack-2x text-success"></i>
                  <i class="fa fa-money fa-stack-1x fa-inverse"></i>
                </span>
              </span>
              <div>
                <p class="app-notification__message">Payment completed</p>
                <p class="app-notification__meta">2 days ago</p>
              </div>
            </a>
          </li>
        </div>
        <li class="app-notification__footer"><a href="#">See all notifications.</a></li>
      </ul>
    </li>
    <li class="dropdown">
      <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Open Profile Menu">
        <i class="fa fa-user fa-lg"></i>
      </a>
      <ul class="dropdown-menu settings-menu dropdown-menu-right">
        <li><a class="dropdown-item" href="{{ route('pages.user') }}"><i class="fa fa-cog fa-lg"></i> Settings</a></li>
        <li><a class="dropdown-item" href="{{ route('pages.user') }}"><i class="fa fa-user fa-lg"></i> Profile</a></li>
        <li>
          <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-left">
              <i class="fa fa-sign-out fa-lg"></i> Logout
            </button>
          </form>
        </li>
      </ul>
    </li>
  </ul>
</header>
