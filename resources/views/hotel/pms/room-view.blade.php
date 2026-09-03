@extends('layouts.app')

@section('title', 'Rooms View')

@push('styles')
  <style>
    :root {
      --rv-brand: #940000;
      --rv-brand-dark: #7a0000;
      --rv-stat-label: #2e7d32;
      --rv-ease-book: cubic-bezier(0.16, 1, 0.3, 1);
      --rv-open-ms: 1200ms;
      --rv-close-ms: 1050ms;
    }

    .rv-page {
      background: #fff;
      border: 1px solid #e8e8e8;
      border-radius: 2px;
      overflow: hidden;
    }

    .rv-titlebar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      padding: 16px 20px 12px;
      border-bottom: 1px solid #eee;
    }

    .rv-titlebar h2 {
      margin: 0;
      font-size: 18px;
      font-weight: 400;
      color: #333;
    }

    .rv-stats-row {
      display: flex;
      flex-wrap: wrap;
      gap: 14px 18px;
      align-items: center;
      justify-content: flex-end;
    }

    .rv-stat-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      min-width: 72px;
    }

    .rv-stat-item__label {
      font-size: 12px;
      font-weight: 700;
      color: var(--rv-stat-label);
      text-transform: capitalize;
    }

    .rv-stat-item__badge {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: var(--rv-brand);
      color: #fff !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 700;
      line-height: 1;
    }

    .rv-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 20px 14px;
      border-bottom: 1px solid #eee;
    }

    .rv-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 16px;
      font-size: 12px;
      color: #555;
    }

    .rv-legend span { display: inline-flex; align-items: center; gap: 6px; }

    .rv-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }

    .rv-dot.is-assigned { background: #f8bbd0; }
    .rv-dot.is-checked-in { background: #86efac; }
    .rv-dot.is-checking-out { background: #f87171; }
    .rv-dot.is-maintenance { background: #9ca3af; }
    .rv-dot.is-complimentary { background: #fde047; }

    .rv-toolbar-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-left: auto;
    }

    .rv-btn-floor {
      background: var(--rv-brand);
      border: none;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 4px;
      text-decoration: none;
      cursor: pointer;
    }

    .rv-btn-floor:hover { background: var(--rv-brand-dark); color: #fff; text-decoration: none; }

    .rv-btn-floor.is-active {
      opacity: 0.92;
      cursor: default;
      pointer-events: none;
    }

    .rv-icon-round {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      border: none;
      background: var(--rv-brand);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
    }

    .rv-icon-round:hover { background: var(--rv-brand-dark); color: #fff; }

    .rv-icon-round.is-spinning i {
      animation: rv-spin 0.8s linear infinite;
    }

    @keyframes rv-spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .rv-icon-round--outline {
      background: #fff;
      color: #555;
      border: 1px solid #ccc;
      border-radius: 2px;
      width: 32px;
      height: 32px;
    }

    .rv-icon-round--outline:hover {
      background: #f5f5f5;
      color: #333;
    }

    .rv-date-nav {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-left: 4px;
    }

    .rv-date-nav .btn-nav {
      width: 34px;
      height: 34px;
      padding: 0;
      border: 1px solid #ccc;
      background: #fff;
      color: #555;
      border-radius: 4px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      font-size: 16px;
    }

    .rv-date-nav .btn-nav:hover { background: #f5f5f5; text-decoration: none; color: #333; }

    .rv-date-input-wrap {
      position: relative;
      display: inline-flex;
      align-items: stretch;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
      overflow: hidden;
      height: 34px;
      min-width: 168px;
    }

    .rv-date-input-wrap .rv-date-input-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      min-width: 38px;
      background: #f6f6f6;
      border-right: 1px solid #e5e5e5;
      color: var(--rv-brand);
      font-size: 14px;
      pointer-events: none;
    }

    .rv-date-input {
      flex: 1;
      width: 130px;
      min-width: 130px;
      height: 100%;
      padding: 0 10px;
      font-size: 13px;
      font-weight: 500;
      color: #333;
      border: none;
      background: transparent;
      line-height: 32px;
    }

    .rv-date-input:focus {
      outline: none;
      box-shadow: inset 0 0 0 1px var(--rv-brand);
    }

    .rv-date-input-wrap:focus-within {
      border-color: var(--rv-brand);
      box-shadow: 0 0 0 1px rgba(148, 0, 0, 0.15);
    }

    .rv-date-input::-webkit-calendar-picker-indicator {
      position: absolute;
      right: 0;
      top: 0;
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
      cursor: pointer;
      opacity: 0;
    }

    .rv-body { padding: 0 0 20px; }

    .rv-type-section {
      border-bottom: 1px solid #e8e8e8;
    }

    .rv-type-header {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 12px;
      padding: 12px 20px;
      background: #f3f3f3;
      border-bottom: 1px solid #e8e8e8;
    }

    .rv-type-header__name-wrap { min-width: 0; }

    .rv-type-header__name {
      font-size: 14px;
      font-weight: 700;
      color: #222;
      margin: 0;
      padding-bottom: 6px;
      border-bottom: 3px solid var(--rv-brand);
      display: inline-block;
    }

    .rv-type-header__toggle {
      border: none;
      background: transparent;
      color: #666;
      font-size: 16px;
      padding: 4px 12px;
      cursor: pointer;
      justify-self: center;
    }

    .rv-type-header__toggle:hover { color: var(--rv-brand); }

    .rv-type-header__toggle .rv-toggle-icon {
      display: inline-flex;
      transition: transform var(--rv-open-ms) var(--rv-ease-book);
    }

    .rv-type-header__toggle.is-open .rv-toggle-icon { transform: rotate(180deg); }

    .rv-type-header .rv-stats-row {
      justify-content: flex-end;
      gap: 10px 14px;
    }

    .rv-type-header .rv-stat-item { min-width: 58px; }
    .rv-type-header .rv-stat-item__label { font-size: 11px; }
    .rv-type-header .rv-stat-item__badge { width: 30px; height: 30px; font-size: 12px; }

    .rv-cards-drawer {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transform: perspective(1600px) rotateX(12deg);
      transform-origin: top center;
      transition:
        max-height var(--rv-open-ms) var(--rv-ease-book),
        opacity calc(var(--rv-open-ms) * 0.85) var(--rv-ease-book),
        transform var(--rv-open-ms) var(--rv-ease-book);
    }

    .rv-cards-drawer.is-closing { transition-duration: var(--rv-close-ms); }

    .rv-cards-drawer.is-open {
      max-height: 2000px;
      opacity: 1;
      transform: perspective(1600px) rotateX(0deg);
    }

    .rv-cards-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      padding: 18px 20px 22px;
      background: #fff;
    }

    .rv-room-card {
      width: 108px;
      min-height: 88px;
      border-radius: 6px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
      border: 1px solid #e0e0e0;
      background: #fff;
      display: flex;
      flex-direction: column;
    }

    .rv-room-card__top {
      flex: 1;
      min-height: 44px;
      padding: 8px 8px 6px;
      background: #eceff1;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 4px;
    }

    .rv-room-card.is-available .rv-room-card__top { background: #eceff1; }
    .rv-room-card.is-assigned .rv-room-card__top { background: #fce7f3; }
    .rv-room-card.is-checked-in .rv-room-card__top { background: #dcfce7; }
    .rv-room-card.is-checking-out .rv-room-card__top { background: #fee2e2; }
    .rv-room-card.is-maintenance .rv-room-card__top { background: #e5e7eb; }
    .rv-room-card.is-complimentary .rv-room-card__top { background: #fef9c3; }

    .rv-room-card__number {
      font-size: 14px;
      font-weight: 700;
      color: #222;
      line-height: 1.2;
    }

    .rv-room-card__icon {
      color: #aaa;
      font-size: 11px;
      line-height: 1;
      flex-shrink: 0;
    }

    .rv-room-card__body {
      padding: 8px;
      min-height: 36px;
      font-size: 11px;
      font-weight: 600;
      color: #444;
      line-height: 1.3;
      word-break: break-word;
    }

    .rv-room-card__body.is-empty { color: transparent; }

    .rv-type-section.is-search-hidden { display: none; }

    .rv-view-tabs {
      display: flex;
      gap: 8px;
      padding: 12px 20px 0;
    }

    .rv-view-tab {
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 4px;
      border: 1px solid #d1d5db;
      color: #555;
      text-decoration: none;
      background: #fff;
    }

    .rv-view-tab.is-active {
      background: var(--rv-brand);
      border-color: var(--rv-brand);
      color: #fff !important;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-th-large"></i> Rooms View</h1>
      <p>Floor view — room status for selected date</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Rooms View</li>
    </ul>
  </div>

  <div class="rv-page tile mb-0">
    <div class="rv-view-tabs">
      @foreach($ui['views'] as $view)
        @if($view['key'] === 'rooms')
          <span class="rv-view-tab is-active">{{ $view['label'] }}</span>
        @else
          <a href="{{ route($view['route'], [$view['date_param'] => $date->format('Y-m-d')]) }}" class="rv-view-tab">{{ $view['label'] }}</a>
        @endif
      @endforeach
    </div>

    <div class="rv-titlebar">
      <h2>Rooms View</h2>
      @include('hotel.pms.partials.room-view-stats', ['stats' => $grid['stats'], 'statLabels' => $ui['stats']])
    </div>

    <div class="rv-toolbar">
      <div class="rv-legend">
        @foreach($ui['legend'] as $item)
          <span><i class="rv-dot is-{{ $item['state'] }}"></i> {{ $item['label'] }}</span>
        @endforeach
      </div>

      <div class="rv-toolbar-actions">
        <span class="rv-btn-floor is-active">{{ $ui['floor_view_label'] }}</span>

        <div class="rv-date-nav">
          <a href="{{ route('hotel.room-view', ['date' => $prevDate->format('Y-m-d')]) }}" class="btn-nav">&lsaquo;</a>
          <form method="GET" action="{{ route('hotel.room-view') }}" class="rv-date-input-wrap mb-0">
            <span class="rv-date-input-icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
            <input type="date" class="rv-date-input" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" aria-label="Select date">
          </form>
          <a href="{{ route('hotel.room-view', ['date' => $nextDate->format('Y-m-d')]) }}" class="btn-nav">&rsaquo;</a>
        </div>

        <form method="POST" action="{{ route('hotel.room-view.sync') }}" class="d-inline mb-0 js-rv-sync-form">
          @csrf
          <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
          <button type="submit" class="rv-icon-round rv-icon-round--outline js-rv-refresh" title="Refresh">
            <i class="fa fa-refresh"></i>
          </button>
        </form>
      </div>
    </div>

    <div class="rv-body">
      @forelse($grid['room_types'] as $roomType)
        <section class="rv-type-section" data-search="{{ strtolower($roomType['name']) }}">
          <div class="rv-type-header">
            <div class="rv-type-header__name-wrap">
              <p class="rv-type-header__name">{{ $roomType['name'] }}</p>
            </div>

            <button type="button" class="rv-type-header__toggle js-rv-toggle-type is-open" aria-expanded="true" aria-label="Toggle {{ $roomType['name'] }}">
              <span class="rv-toggle-icon"><i class="fa fa-chevron-up"></i></span>
            </button>

            @include('hotel.pms.partials.room-view-stats', [
              'stats' => $roomType['stats'],
              'statLabels' => $ui['stats'],
              'compact' => true,
            ])
          </div>

          <div class="rv-cards-drawer is-open js-rv-cards-drawer">
            <div class="rv-cards-grid">
              @foreach($roomType['units'] as $unit)
                @php
                  $cardTitle = $unit['status_label'];
                  if (! empty($unit['guest'])) {
                    $cardTitle .= ' · '.$unit['guest'];
                  }
                  if (! empty($unit['channel'])) {
                    $cardTitle .= ' · '.$unit['channel'];
                  }
                @endphp
                <article class="rv-room-card is-{{ $unit['state'] }}"
                  title="{{ $cardTitle }}"
                  data-search="{{ strtolower($roomType['name'].' '.$unit['label'].' '.($unit['guest'] ?? '')) }}">
                  <div class="rv-room-card__top">
                    <span class="rv-room-card__number">{{ $unit['label'] }}</span>
                    <span class="rv-room-card__icon"><i class="fa {{ $unit['icon'] ?? 'fa-star-o' }}"></i></span>
                  </div>
                  <div class="rv-room-card__body {{ $unit['guest'] ? '' : 'is-empty' }}">
                    {{ $unit['guest'] ?? '' }}
                  </div>
                </article>
              @endforeach
            </div>
          </div>
        </section>
      @empty
        <div class="text-center text-muted py-5">
          No room types configured. Add rooms under <a href="{{ route('hotel.rooms.index') }}">Rooms</a>.
        </div>
      @endforelse
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var OPEN_MS = 1200;
      var CLOSE_MS = 1050;

      function toggleDrawer(drawer, trigger, open) {
        if (!drawer) return;
        if (open) {
          drawer.classList.remove('is-closing');
          drawer.classList.add('is-open');
          if (trigger) {
            trigger.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
          }
          return;
        }
        drawer.classList.add('is-closing');
        drawer.classList.remove('is-open');
        if (trigger) {
          trigger.classList.remove('is-open');
          trigger.setAttribute('aria-expanded', 'false');
        }
        window.setTimeout(function () { drawer.classList.remove('is-closing'); }, CLOSE_MS);
      }

      document.querySelectorAll('.js-rv-toggle-type').forEach(function (btn) {
        var section = btn.closest('.rv-type-section');
        var drawer = section ? section.querySelector('.js-rv-cards-drawer') : null;
        var animating = false;

        btn.addEventListener('click', function () {
          if (!drawer || animating) return;
          animating = true;
          var isOpen = drawer.classList.contains('is-open');
          toggleDrawer(drawer, btn, !isOpen);
          window.setTimeout(function () { animating = false; }, isOpen ? CLOSE_MS : OPEN_MS);
        });
      });

      var syncForm = document.querySelector('.js-rv-sync-form');
      if (syncForm) {
        syncForm.addEventListener('submit', function () {
          var btn = syncForm.querySelector('.js-rv-refresh');
          if (btn) btn.classList.add('is-spinning');
        });
      }
    })();
  </script>
@endpush
