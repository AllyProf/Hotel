@extends('layouts.app')

@section('title', 'Stay View')

@push('styles')
  <style>
    :root {
      --sv-brand: #940000;
      --sv-brand-dark: #7a0000;
      --sv-brand-soft: rgba(148, 0, 0, 0.08);
      --sv-ease-book: cubic-bezier(0.16, 1, 0.3, 1);
      --sv-ease-soft: cubic-bezier(0.25, 0.8, 0.25, 1);
      --sv-open-ms: 1200ms;
      --sv-close-ms: 1050ms;
      --sv-fade-ms: 800ms;
    }

    @media (prefers-reduced-motion: reduce) {
      :root {
        --sv-open-ms: 1ms;
        --sv-close-ms: 1ms;
        --sv-fade-ms: 1ms;
      }
    }

    .sv-page {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      overflow: hidden;
    }

    .sv-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      padding: 14px 18px;
      border-bottom: 1px solid #e5e7eb;
    }

    .sv-view-tabs { display: flex; gap: 8px; }
    .sv-view-tab {
      padding: 6px 14px; font-size: 13px; font-weight: 600; border-radius: 4px;
      border: 1px solid #d1d5db; color: #555; text-decoration: none; background: #fff;
    }
    .sv-view-tab:hover { border-color: var(--sv-brand); color: var(--sv-brand); text-decoration: none; }
    .sv-view-tab.is-active {
      background: var(--sv-brand); border-color: var(--sv-brand); color: #fff !important;
    }

    .sv-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 12px 16px;
      align-items: center;
      font-size: 12px;
      color: #555;
    }

    .sv-legend span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .sv-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
      flex-shrink: 0;
    }

    .sv-dot.is-assigned { background: #f8bbd0; }
    .sv-dot.is-checked-in { background: #86efac; }
    .sv-dot.is-checking-out { background: #f87171; }
    .sv-dot.is-checked-out { background: #166534; }
    .sv-dot.is-maintenance { background: #ef4444; }
    .sv-dot.is-complimentary { background: #fde047; }

    .sv-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      align-items: center;
      font-size: 13px;
      font-weight: 600;
      color: #333;
    }

    .sv-stat {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .sv-stat__badge {
      min-width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--sv-brand);
      color: #fff !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      line-height: 1;
    }

    .sv-stat__badge,
    .sv-date-badge,
    .sv-type-day-badge {
      -webkit-text-fill-color: #fff;
    }

    .sv-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 18px;
      border-bottom: 1px solid #e5e7eb;
      background: #fafafa;
    }

    .sv-toolbar-left {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      flex: 1;
      min-width: 0;
    }

    .sv-date-nav {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-left: auto;
    }

    .sv-icon-btn {
      width: 34px;
      height: 34px;
      border: 1px solid #d1d5db;
      background: #fff;
      color: #555;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }

    .sv-icon-btn:hover,
    .sv-icon-btn.is-active {
      background: var(--sv-brand-soft);
      border-color: var(--sv-brand);
      color: var(--sv-brand);
      text-decoration: none;
    }

    .sv-icon-btn.is-spinning i {
      animation: sv-spin 0.8s linear infinite;
    }

    @keyframes sv-spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .sv-search-wrap {
      width: 0;
      max-width: 0;
      opacity: 0;
      overflow: hidden;
      transition: max-width var(--sv-open-ms) var(--sv-ease-book), opacity 0.7s var(--sv-ease-book), margin 0.7s var(--sv-ease-book);
      margin-left: 0;
    }

    .sv-search-wrap.is-open {
      width: auto;
      max-width: 260px;
      opacity: 1;
      margin-left: 4px;
      flex: 1;
    }

    .sv-search-input {
      width: 100%;
      min-width: 200px;
      height: 34px;
      font-size: 13px;
      border: 1px solid #d1d5db;
      border-radius: 4px;
      padding: 6px 12px;
      background: #fff;
    }

    .sv-search-input:focus {
      border-color: var(--sv-brand);
      outline: none;
      box-shadow: 0 0 0 2px rgba(148, 0, 0, 0.12);
    }

    .sv-unit-row.is-search-hidden,
    .sv-room-type.is-search-hidden {
      display: none !important;
    }

    .sv-date-input {
      width: 130px;
      font-size: 13px;
      text-align: center;
    }

    .sv-scroll {
      overflow: auto;
    }

    .sv-table-stack {
      min-width: 980px;
    }

    .sv-grid-main .sv-col-room {
      width: 148px;
    }

    .sv-grid-main .sv-col-day {
      width: 118px;
      min-width: 118px;
    }

    .sv-col-room {
      width: 140px;
    }

    .sv-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 980px;
      table-layout: fixed;
    }

    .sv-table th,
    .sv-table td {
      border: 1px solid #d1d5db;
      vertical-align: middle;
    }

    .sv-table thead th {
      background: var(--sv-brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 10px 8px;
      text-align: center;
    }

    /* Main grid: timeline header layout only (colours unchanged — brand red) */
    .sv-grid-main .sv-table thead th {
      padding: 12px 6px 20px;
      position: relative;
      vertical-align: bottom;
    }

    .sv-grid-main .sv-table thead th:first-child {
      padding-left: 12px;
      vertical-align: middle;
    }

    .sv-date-head {
      line-height: 1.25;
    }

    .sv-date-head small {
      display: block;
      font-weight: 600;
      opacity: 0.9;
      font-size: 11px;
    }

    .sv-date-badge {
      position: absolute;
      left: 50%;
      bottom: -11px;
      transform: translateX(-50%);
      min-width: 22px;
      height: 22px;
      padding: 0 6px;
      border-radius: 999px;
      background: var(--sv-brand);
      color: #fff !important;
      font-size: 11px;
      font-weight: 700;
      line-height: 22px;
      text-align: center;
      border: 2px solid #fff;
      z-index: 2;
    }

    .sv-table thead th:first-child {
      text-align: left;
      width: 140px;
    }

    .sv-type-block {
      margin: 0;
    }

    .sv-type-header-table .sv-table {
      margin: 0;
    }

    .sv-book-drawer {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      backface-visibility: hidden;
      transition:
        max-height var(--sv-open-ms) var(--sv-ease-book),
        opacity calc(var(--sv-open-ms) * 0.85) var(--sv-ease-book),
        transform var(--sv-open-ms) var(--sv-ease-book);
    }

    .sv-book-drawer.is-closing {
      transition-duration: var(--sv-close-ms);
    }

    .sv-book-drawer.is-open {
      opacity: 1;
      transform: perspective(1600px) rotateX(0deg);
    }

    .sv-book-drawer .sv-table {
      margin: 0;
    }

    .sv-summary-drawer {
      transform: perspective(1600px) rotateX(-14deg);
      transform-origin: bottom center;
    }

    .sv-summary-drawer.is-open {
      max-height: 340px;
    }

    .sv-type-drawer {
      transform: perspective(1600px) rotateX(14deg);
      transform-origin: top center;
    }

    .sv-type-drawer.is-open {
      max-height: 1200px;
    }

    .sv-room-type td {
      background: #f8f9fa;
      font-weight: 700;
      font-size: 13px;
      color: #111;
      padding: 8px 10px;
      border-left: 3px solid var(--sv-brand);
    }

    .sv-grid-main .sv-room-type td {
      background: #546e7a;
      color: #fff;
      border-color: #546e7a;
      border-left: none;
      padding: 9px 12px;
    }

    .sv-grid-main .sv-room-type__toggle {
      color: #fff;
    }

    .sv-grid-main .sv-room-type__toggle:hover {
      color: #eceff1;
    }

    .sv-type-metrics td {
      background: #eceff1;
      border-color: #dfe3e6;
      padding: 5px 2px;
      height: 30px;
      text-align: center;
      vertical-align: middle;
    }

    .sv-type-metrics-label {
      background: #eceff1 !important;
    }

    .sv-type-day-badge {
      display: inline-block;
      min-width: 20px;
      height: 20px;
      padding: 0 5px;
      line-height: 20px;
      border-radius: 999px;
      background: var(--sv-brand);
      color: #fff !important;
      font-size: 10px;
      font-weight: 700;
    }

    .sv-room-type__toggle {
      border: none;
      background: transparent;
      color: var(--sv-brand);
      font-weight: 700;
      padding: 0;
      margin-right: 8px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0;
    }

    .sv-room-type__toggle:hover {
      color: var(--sv-brand-dark);
    }

    .sv-room-type__toggle:focus {
      outline: none;
    }

    .sv-toggle-icon {
      display: inline-flex;
      width: 22px;
      justify-content: center;
      transition: transform var(--sv-open-ms) var(--sv-ease-book);
    }

    .sv-room-type__toggle.is-open .sv-toggle-icon {
      transform: rotate(180deg);
    }

    .sv-type-drawer .sv-unit-row {
      opacity: 0;
      transform: translateY(-10px);
      transition:
        opacity calc(var(--sv-fade-ms) + 200ms) var(--sv-ease-book),
        transform calc(var(--sv-fade-ms) + 200ms) var(--sv-ease-book);
    }

    .sv-type-drawer.is-open .sv-unit-row {
      opacity: 1;
      transform: translateY(0);
    }

    .sv-type-drawer.is-open .sv-unit-row:nth-child(1) { transition-delay: 0.08s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(2) { transition-delay: 0.14s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(3) { transition-delay: 0.2s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(4) { transition-delay: 0.26s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(5) { transition-delay: 0.32s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(6) { transition-delay: 0.38s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(7) { transition-delay: 0.44s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(8) { transition-delay: 0.5s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(9) { transition-delay: 0.56s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(10) { transition-delay: 0.62s; }
    .sv-type-drawer.is-open .sv-unit-row:nth-child(n+11) { transition-delay: 0.68s; }

    .sv-room-label {
      width: 140px;
      background: #fff;
      font-size: 13px;
      font-weight: 600;
      color: #333;
      padding: 8px 10px;
      white-space: nowrap;
    }

    .sv-grid-main .sv-room-label {
      position: sticky;
      left: 0;
      z-index: 2;
      box-shadow: 1px 0 0 #d1d5db;
    }

    .sv-grid-main .sv-unit-row:nth-child(even) .sv-room-label,
    .sv-grid-main .sv-unit-row:nth-child(even) .sv-cell {
      background: #fafbfc;
    }

    .sv-room-label__meta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .sv-room-label__status {
      color: #aaa;
      font-size: 12px;
      width: 16px;
      text-align: center;
    }

    .sv-room-label__icon {
      color: #bbb;
      margin-right: 6px;
      font-size: 11px;
    }

    .sv-cell {
      height: 42px;
      padding: 4px;
      background: #fff;
      position: relative;
    }

    .sv-grid-main .sv-cell {
      height: 40px;
      padding: 6px 1px;
      background: #fff;
    }

    .sv-grid-main .sv-table td,
    .sv-grid-main .sv-table th {
      border-color: #e8edf1;
    }

    .sv-grid-main .sv-table tbody td.sv-cell {
      border-left-color: #eef2f5;
      border-right-color: #eef2f5;
    }

    .sv-cell.is-maintenance,
    .sv-cell.sv-cell--maintenance {
      background: #fecaca;
      color: #991b1b;
      font-size: 11px;
      font-weight: 700;
      text-align: center;
      vertical-align: middle;
    }

    .sv-grid-main .sv-cell.sv-cell--maintenance {
      background: #fff;
      padding: 6px 3px;
    }

    .sv-grid-main .sv-maintenance-bar {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      min-height: 28px;
      border-radius: 14px;
      background: #78909c;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
    }

    .sv-booking {
      height: 100%;
      min-height: 34px;
      border-radius: 3px;
      padding: 6px 8px;
      font-size: 11px;
      font-weight: 700;
      color: #111;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      border: 1px solid rgba(0, 0, 0, 0.08);
    }

    /* Main timeline — reference-style dual-tone capsules (grid only) */
    .sv-grid-main .sv-booking {
      display: flex;
      align-items: stretch;
      min-height: 28px;
      height: 100%;
      margin: 0;
      padding: 0;
      font-size: 11px;
      border-radius: 16px;
      overflow: hidden;
      border: none;
      background: transparent;
      color: #111;
      box-shadow: none;
    }

    .sv-grid-main .sv-booking__cap {
      flex: 0 1 auto;
      max-width: 46%;
      min-width: 4.5em;
      padding: 7px 10px;
      font-weight: 700;
      line-height: 1.15;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      background: #1b3d24;
      color: #fff;
      border: none;
    }

    .sv-grid-main .sv-booking__tail {
      flex: 1 1 auto;
      min-width: 0;
      padding: 7px 10px;
      font-weight: 600;
      line-height: 1.15;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      display: flex;
      align-items: center;
    }

    .sv-grid-main .sv-booking.is-checked-in .sv-booking__tail,
    .sv-grid-main .sv-booking.is-checked-out .sv-booking__tail {
      background: #81c784;
      color: #1b5e20;
    }

    .sv-grid-main .sv-booking.is-assigned .sv-booking__tail,
    .sv-grid-main .sv-booking.is-complimentary .sv-booking__tail {
      background: #f4aba8;
      color: #5d4037;
    }

    .sv-grid-main .sv-booking.is-checking-out .sv-booking__tail {
      background: #ffcdd2;
      color: #4e342e;
    }

    .sv-grid-main .sv-booking.is-checking-out--solo .sv-booking__cap {
      max-width: 46%;
    }

    .sv-booking.is-assigned { background: #fce7f3; border-color: #f9a8d4; }
    .sv-booking.is-checked-in { background: #dcfce7; border-color: #86efac; }
    .sv-booking.is-checking-out { background: #fee2e2; border-color: #fca5a5; }
    .sv-booking.is-checked-out { background: #d1fae5; border-color: #059669; color: #065f46; }
    .sv-booking.is-complimentary { background: #fef9c3; border-color: #fde047; }

    .sv-summary-row th,
    .sv-summary-row td {
      background: var(--sv-brand-dark);
      color: #fff !important;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 8px;
      text-align: center;
      opacity: 0;
      transform: translateY(-12px);
      transition:
        opacity calc(var(--sv-fade-ms) + 200ms) var(--sv-ease-book),
        transform calc(var(--sv-fade-ms) + 200ms) var(--sv-ease-book);
    }

    .sv-summary-drawer.is-open .sv-summary-row:nth-child(1) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(1) td { transition-delay: 0.12s; }
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(2) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(2) td { transition-delay: 0.2s; }
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(3) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(3) td { transition-delay: 0.28s; }
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(4) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(4) td { transition-delay: 0.36s; }
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(5) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(5) td { transition-delay: 0.44s; }
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(6) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(6) td { transition-delay: 0.52s; }
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(7) th,
    .sv-summary-drawer.is-open .sv-summary-row:nth-child(7) td { transition-delay: 0.6s; }

    .sv-summary-drawer.is-open .sv-summary-row th,
    .sv-summary-drawer.is-open .sv-summary-row td {
      opacity: 1;
      transform: translateY(0);
    }

    .sv-summary-row th {
      text-align: left;
    }

    .sv-summary-row td:last-child {
      position: relative;
    }

    .sv-summary-close {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: #fff;
      font-size: 18px;
      line-height: 1;
      opacity: 0.85;
      transition: opacity 0.15s ease;
    }

    .sv-summary-close:hover {
      opacity: 1;
    }

    .sv-footer-row td {
      background: var(--sv-brand);
      color: #fff !important;
      font-weight: 700;
      font-size: 13px;
      text-align: center;
      padding: 10px 8px;
    }

    .sv-footer-row td:first-child {
      text-align: left;
      padding-left: 12px;
    }

    .sv-footer-toggle {
      background: transparent;
      color: #fff;
      font-weight: 700;
      font-size: 13px;
      padding: 0;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }

    .sv-footer-toggle:hover,
    .sv-footer-toggle:focus {
      color: #fff;
      outline: none;
    }

    .sv-footer-toggle__icon {
      display: inline-flex;
      transition: transform var(--sv-open-ms) var(--sv-ease-book);
    }

    .sv-footer-toggle.is-open .sv-footer-toggle__icon {
      transform: rotate(180deg);
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-bed"></i> Stay View</h1>
      <p>Room calendar — reservations and availability</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Stay View</li>
    </ul>
  </div>

  <div class="sv-page tile mb-0">
    <div class="sv-head">
      <div class="sv-view-tabs">
        <span class="sv-view-tab is-active">Stay View</span>
        <a href="{{ route('hotel.room-view', ['date' => $start->format('Y-m-d')]) }}" class="sv-view-tab">Rooms View</a>
      </div>

      <div class="sv-legend">
        <span><i class="sv-dot is-assigned"></i> Assigned</span>
        <span><i class="sv-dot is-checked-in"></i> Checked in</span>
        <span><i class="sv-dot is-checking-out"></i> Checking out</span>
        <span><i class="sv-dot is-checked-out"></i> Checked out</span>
        <span><i class="sv-dot is-maintenance"></i> Maintenance</span>
        <span><i class="sv-dot is-complimentary"></i> Complimentary</span>
      </div>

      <div class="sv-stats">
        <span class="sv-stat">Guests <span class="sv-stat__badge">{{ $grid['stats']['guests'] }}</span></span>
        <span class="sv-stat">Occupied <span class="sv-stat__badge">{{ $grid['stats']['occupied'] }}</span></span>
        <span class="sv-stat">Available <span class="sv-stat__badge">{{ $grid['stats']['available'] }}</span></span>
      </div>
    </div>

    <div class="sv-toolbar">
      <div class="sv-toolbar-left">
        <button type="button" class="sv-icon-btn js-sv-refresh" title="Refresh">
          <i class="fa fa-refresh"></i>
        </button>

        <form method="POST" action="{{ route('hotel.stay-view.sync') }}" class="d-inline mb-0 js-sv-sync-form">
          @csrf
          <input type="hidden" name="start" value="{{ $start->format('Y-m-d') }}">
          <button type="submit" class="sv-icon-btn" title="Sync inventory from Channel Manager">
            <i class="fa fa-exchange"></i>
          </button>
        </form>

        @if(app()->environment('local', 'testing') && config('hotel_pms.stay_view.allow_sample_bookings', true))
          <form method="POST" action="{{ route('hotel.stay-view.sample-bookings') }}" class="d-inline mb-0">
            @csrf
            <input type="hidden" name="start" value="{{ $start->format('Y-m-d') }}">
            <button type="submit" class="sv-icon-btn" title="Generate sample room bookings for this week">
              <i class="fa fa-users"></i>
            </button>
          </form>
          @if(! empty($grid['has_sample_bookings']))
            <form method="POST" action="{{ route('hotel.stay-view.sample-bookings.clear') }}" class="d-inline mb-0 js-sv-clear-sample-form">
              @csrf
              @method('DELETE')
              <input type="hidden" name="start" value="{{ $start->format('Y-m-d') }}">
              <button type="submit" class="sv-icon-btn" title="Remove sample bookings">
                <i class="fa fa-times"></i>
              </button>
            </form>
          @endif
        @endif

        <button type="button" class="sv-icon-btn js-sv-search-toggle" title="Search" aria-expanded="false">
          <i class="fa fa-search"></i>
        </button>

        <div class="sv-search-wrap" id="svSearchWrap">
          <input type="search" class="sv-search-input js-sv-search-input" placeholder="Search room or guest..." autocomplete="off">
        </div>
      </div>

      <div class="sv-date-nav">
        @php
          $cashBookTo = $start->copy()->addDays(\App\Services\StayViewService::WINDOW_DAYS - 1);
        @endphp
        <a href="{{ route('hotel.stay-view.cash-book', [
          'from_date' => $start->format('Y-m-d'),
          'to_date' => $cashBookTo->format('Y-m-d'),
          'stay_start' => $start->format('Y-m-d'),
        ]) }}" class="sv-icon-btn" title="Expenses (cash book)">
          <i class="fa fa-usd"></i>
        </a>
        <a href="{{ route('hotel.stay-view', ['start' => $prevStart->format('Y-m-d')]) }}" class="sv-icon-btn" title="Previous week">&laquo;</a>
        <form method="GET" action="{{ route('hotel.stay-view') }}" class="d-inline mb-0">
          <input type="date" class="form-control form-control-sm sv-date-input" name="start"
            value="{{ $start->format('Y-m-d') }}" onchange="this.form.submit()">
        </form>
        <a href="{{ route('hotel.stay-view', ['start' => $nextStart->format('Y-m-d')]) }}" class="sv-icon-btn" title="Next week">&raquo;</a>
      </div>
    </div>

    @if(! empty($grid['has_sample_bookings']))
      <p class="mb-0 px-3 py-2 text-muted small border-bottom bg-light">
        Sample bookings are loaded for this hotel (IDs <code>STAYVIEW-DEMO-*</code>). Use the <i class="fa fa-times"></i> toolbar button to remove them.
      </p>
    @elseif(! empty($grid['is_demo']))
      <p class="mb-0 px-3 py-2 text-muted small border-bottom bg-light">
        Preview guests only — click <i class="fa fa-users"></i> in the toolbar to save sample bookings to the database.
      </p>
    @endif

    <div class="sv-scroll">
      <div class="sv-table-stack sv-grid-main">
        <table class="sv-table" id="svTable">
          <colgroup>
            <col class="sv-col-room">
            @foreach($grid['dates'] as $date)
              <col class="sv-col-day">
            @endforeach
          </colgroup>
          <thead>
            <tr>
              <th>Rooms</th>
              @foreach($grid['dates'] as $date)
                @php $dateKey = $date->format('Y-m-d'); @endphp
                <th>
                  <div class="sv-date-head">
                    {{ $date->format('d M') }}
                    <small>{{ $date->format('D') }}</small>
                  </div>
                  @if(($grid['date_header_counts'][$dateKey] ?? 0) > 0)
                    <span class="sv-date-badge" title="Arrivals">{{ $grid['date_header_counts'][$dateKey] }}</span>
                  @endif
                </th>
              @endforeach
            </tr>
          </thead>
        </table>

        @forelse($grid['room_types'] as $roomType)
          <div class="sv-type-block" data-room-type="{{ $roomType['id'] }}">
            <table class="sv-table sv-type-header-table">
              <colgroup>
                <col class="sv-col-room">
                @foreach($grid['dates'] as $date)
                  <col class="sv-col-day">
                @endforeach
              </colgroup>
              <tbody>
                <tr class="sv-room-type js-sv-room-type">
                  <td colspan="{{ $grid['dates']->count() + 1 }}">
                    <button type="button" class="sv-room-type__toggle js-sv-toggle-type is-open" aria-expanded="true" aria-label="Toggle {{ $roomType['name'] }}">
                      <span class="sv-toggle-icon"><i class="fa fa-chevron-up"></i></span>
                    </button>
                    {{ $roomType['name'] }}
                  </td>
                </tr>
                <tr class="sv-type-metrics">
                  <td class="sv-type-metrics-label"></td>
                  @foreach($grid['date_keys'] as $dateKey)
                    <td>
                      @php $typeCount = (int) ($roomType['date_counts'][$dateKey] ?? 0); @endphp
                      @if($typeCount > 0)
                        <span class="sv-type-day-badge" title="Stays this day">{{ $typeCount }}</span>
                      @endif
                    </td>
                  @endforeach
                </tr>
              </tbody>
            </table>

            <div class="sv-book-drawer sv-type-drawer is-open js-sv-type-drawer" data-room-type="{{ $roomType['id'] }}">
              <table class="sv-table">
                <colgroup>
                  <col class="sv-col-room">
                  @foreach($grid['dates'] as $date)
                    <col class="sv-col-day">
                  @endforeach
                </colgroup>
                <tbody>
                  @foreach($roomType['units'] as $unit)
                    @php
                      $searchBits = [$roomType['name'], $unit['label']];
                      foreach ($unit['cells'] as $cell) {
                        if (($cell['type'] ?? '') === 'booking') {
                          $searchBits[] = $cell['guest'] ?? '';
                          $searchBits[] = $cell['channel'] ?? '';
                        }
                      }
                    @endphp
                    <tr class="sv-unit-row js-sv-unit-row" data-search="{{ strtolower(implode(' ', array_filter($searchBits))) }}">
                      <td class="sv-room-label">
                        <span class="sv-room-label__meta">
                          <span>{{ $unit['label'] }}</span>
                          <span class="sv-room-label__status" aria-hidden="true">
                            @if(($unit['status'] ?? '') === \App\Models\HotelRoomUnit::STATUS_MAINTENANCE)
                              <i class="fa fa-wrench" title="Maintenance"></i>
                            @else
                              <i class="fa fa-ellipsis-h"></i>
                            @endif
                          </span>
                        </span>
                      </td>
                      @foreach($unit['cells'] as $cell)
                        @if(($cell['type'] ?? '') === 'booking')
                          @php $span = (int) ($cell['colspan'] ?? 1); @endphp
                          @php
                            $status = $cell['status'] ?? 'assigned';
                            $guest = $cell['guest'] ?? 'Guest';
                            $soloCheckout = $status === 'checking-out';
                          @endphp
                          <td class="sv-cell" colspan="{{ $span }}">
                            <div class="sv-booking is-{{ $status }} @if($soloCheckout) is-checking-out--solo @endif" title="{{ $cell['status_label'] ?? 'Assigned' }} · {{ $cell['channel'] ?? '' }}">
                              <span class="sv-booking__cap">{{ $guest }}</span>
                              <span class="sv-booking__tail" aria-hidden="true"></span>
                            </div>
                          </td>
                        @elseif(($cell['type'] ?? '') === 'maintenance')
                          <td class="sv-cell sv-cell--maintenance" colspan="{{ $cell['colspan'] ?? 1 }}">
                            <div class="sv-maintenance-bar">{{ $cell['label'] ?? 'Out of Order' }}</div>
                          </td>
                        @else
                          <td class="sv-cell"></td>
                        @endif
                      @endforeach
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @empty
          <table class="sv-table">
            <colgroup>
              <col class="sv-col-room">
              @foreach($grid['dates'] as $date)
                <col class="sv-col-day">
              @endforeach
            </colgroup>
            <tbody>
              <tr>
                <td colspan="{{ $grid['dates']->count() + 1 }}" class="text-center text-muted py-4">
                  No rooms configured. Add rooms under <a href="{{ route('hotel.rooms.index') }}">Rooms</a>.
                </td>
              </tr>
            </tbody>
          </table>
        @endforelse

        <div id="svSummaryPanel" class="sv-book-drawer sv-summary-drawer">
          <table class="sv-table">
            <colgroup>
              <col class="sv-col-room">
              @foreach($grid['dates'] as $date)
                <col class="sv-col-day">
              @endforeach
            </colgroup>
            <tbody>
              @php
                $summaryRows = [
                  'Available Rooms' => 'available',
                  'Rooms Occupied' => 'occupied',
                  'Occupancy' => 'occupancy',
                  'No. of guests' => 'guests',
                  'Arriving' => 'arriving',
                  'Checking Out' => 'checking_out',
                  'Bar rate' => 'bar_rate',
                ];
              @endphp
              @foreach($summaryRows as $label => $key)
                <tr class="sv-summary-row">
                  <th>{{ $label }}</th>
                  @foreach($grid['date_keys'] as $dateKey)
                    <td>
                      @if($key === 'occupancy')
                        {{ $grid['summary'][$dateKey][$key] ?? 0 }}%
                      @elseif($key === 'bar_rate')
                        {{ number_format((float) ($grid['summary'][$dateKey][$key] ?? 0), 0) }}
                      @else
                        {{ $grid['summary'][$dateKey][$key] ?? 0 }}
                      @endif
                      @if($loop->parent->last && $loop->last)
                        <button type="button" class="sv-summary-close js-sv-summary-close" title="Close">&times;</button>
                      @endif
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <table class="sv-table">
          <colgroup>
            <col class="sv-col-room">
            @foreach($grid['dates'] as $date)
              <col class="sv-col-day">
            @endforeach
          </colgroup>
          <tfoot>
            <tr class="sv-footer-row">
              <td>
                <button type="button" class="sv-footer-toggle js-sv-summary-toggle" aria-expanded="false">
                  <span class="sv-footer-toggle__icon"><i class="fa fa-chevron-up"></i></span>
                  Available Rooms
                </button>
              </td>
              @foreach($grid['date_keys'] as $dateKey)
                <td>{{ $grid['summary'][$dateKey]['available'] ?? 0 }}</td>
              @endforeach
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var OPEN_MS = 1200;
      var CLOSE_MS = 1050;

      function toggleBookDrawer(drawer, trigger, open) {
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
        window.setTimeout(function () {
          drawer.classList.remove('is-closing');
        }, CLOSE_MS);
      }

      document.querySelectorAll('.js-sv-toggle-type').forEach(function (btn) {
        var block = btn.closest('.sv-type-block');
        var drawer = block ? block.querySelector('.js-sv-type-drawer') : null;
        var animating = false;

        btn.addEventListener('click', function () {
          if (!drawer || animating) return;
          animating = true;
          var isOpen = drawer.classList.contains('is-open');
          toggleBookDrawer(drawer, btn, !isOpen);
          window.setTimeout(function () {
            animating = false;
          }, isOpen ? CLOSE_MS : OPEN_MS);
        });
      });

      var summaryPanel = document.getElementById('svSummaryPanel');
      var summaryToggle = document.querySelector('.js-sv-summary-toggle');
      var summaryAnimating = false;

      function setSummaryOpen(open) {
        if (!summaryPanel || !summaryToggle || summaryAnimating) return;
        summaryAnimating = true;
        toggleBookDrawer(summaryPanel, summaryToggle, open);
        window.setTimeout(function () {
          summaryAnimating = false;
        }, open ? OPEN_MS : CLOSE_MS);
      }

      if (summaryToggle) {
        summaryToggle.addEventListener('click', function () {
          setSummaryOpen(!summaryPanel.classList.contains('is-open'));
        });
      }

      document.querySelectorAll('.js-sv-summary-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
          setSummaryOpen(false);
        });
      });

      var refreshBtn = document.querySelector('.js-sv-refresh');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
          refreshBtn.classList.add('is-spinning');
          window.location.reload();
        });
      }

      var syncForm = document.querySelector('.js-sv-sync-form');
      if (syncForm) {
        syncForm.addEventListener('submit', function () {
          var btn = syncForm.querySelector('.sv-icon-btn');
          if (btn) btn.classList.add('is-spinning');
        });
      }

      var searchToggle = document.querySelector('.js-sv-search-toggle');
      var searchWrap = document.getElementById('svSearchWrap');
      var searchInput = document.querySelector('.js-sv-search-input');

      function setSearchOpen(open) {
        if (!searchWrap || !searchToggle) return;
        searchWrap.classList.toggle('is-open', open);
        searchToggle.classList.toggle('is-active', open);
        searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && searchInput) {
          window.setTimeout(function () { searchInput.focus(); }, 420);
        }
        if (!open && searchInput) {
          searchInput.value = '';
          applySearch('');
        }
      }

      if (searchToggle) {
        searchToggle.addEventListener('click', function () {
          setSearchOpen(!searchWrap.classList.contains('is-open'));
        });
      }

      function applySearch(query) {
        var term = (query || '').trim().toLowerCase();
        document.querySelectorAll('.sv-type-block').forEach(function (block) {
          var typeRow = block.querySelector('.js-sv-room-type');
          var unitRows = block.querySelectorAll('.js-sv-unit-row');
          var visibleCount = 0;

          unitRows.forEach(function (row) {
            var haystack = row.getAttribute('data-search') || '';
            var match = term === '' || haystack.indexOf(term) !== -1;
            row.classList.toggle('is-search-hidden', !match);
            if (match) visibleCount++;
          });

          if (typeRow) {
            typeRow.classList.toggle('is-search-hidden', term !== '' && visibleCount === 0);
          }

          var drawer = block.querySelector('.js-sv-type-drawer');
          if (drawer && term !== '' && visibleCount === 0) {
            drawer.classList.add('is-search-hidden');
          } else if (drawer) {
            drawer.classList.remove('is-search-hidden');
          }
        });
      }

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          applySearch(this.value);
        });
      }

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && searchWrap && searchWrap.classList.contains('is-open')) {
          setSearchOpen(false);
        }
      });
    })();
  </script>
@endpush
