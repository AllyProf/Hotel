@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
  <style>
    :root {
      --hd-brand: #940000;
      --hd-brand-dark: #7a0000;
    }

    .hd-stat {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 18px 20px;
      height: 100%;
    }

    .hd-stat__label {
      font-size: 12px;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin-bottom: 6px;
    }

    .hd-stat__value {
      font-size: 28px;
      font-weight: 700;
      color: #111827;
      line-height: 1.1;
    }

    .hd-stat__sub {
      font-size: 12px;
      color: #9ca3af;
      margin-top: 4px;
    }

    .hd-stat.is-brand {
      border-top: 3px solid var(--hd-brand);
    }

    .hd-panel {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      height: 100%;
    }

    .hd-panel__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 18px;
      border-bottom: 1px solid #eee;
    }

    .hd-panel__head h3 {
      margin: 0;
      font-size: 15px;
      font-weight: 600;
      color: #333;
    }

    .hd-panel__head a {
      font-size: 12px;
      font-weight: 600;
      color: var(--hd-brand);
      text-decoration: none;
    }

    .hd-panel__head a:hover {
      text-decoration: underline;
    }

    .hd-table {
      width: 100%;
      border-collapse: collapse;
    }

    .hd-table th {
      background: var(--hd-brand);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 8px 14px;
      border: 1px solid var(--hd-brand-dark);
      white-space: nowrap;
    }

    .hd-table td {
      padding: 10px 14px;
      font-size: 13px;
      border-bottom: 1px solid #eee;
      vertical-align: middle;
    }

    .hd-table tbody tr:last-child td {
      border-bottom: none;
    }

    .hd-table tbody tr:hover td {
      background: #fafafa;
    }

    .hd-guest {
      font-weight: 600;
      color: #222;
    }

    .hd-empty {
      padding: 32px 18px;
      text-align: center;
      color: #9ca3af;
      font-size: 13px;
    }

    .hd-quick {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 20px;
    }

    .hd-quick a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border: 1px solid #d1d5db;
      border-radius: 4px;
      background: #fff;
      color: #444;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      transition: border-color 0.15s, color 0.15s, background 0.15s;
    }

    .hd-quick a:hover {
      border-color: var(--hd-brand);
      color: var(--hd-brand);
      background: rgba(148, 0, 0, 0.04);
      text-decoration: none;
    }

    .hd-date {
      font-size: 13px;
      color: #6b7280;
    }
  </style>
@endpush

@section('content')
  @php
    $stats = $snapshot['stats'] ?? [];
    $arrivals = $snapshot['arrivals'] ?? [];
    $departures = $snapshot['departures'] ?? [];
    $today = $snapshot['date'] ?? now();
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-dashboard"></i> {{ $hotel?->display_name ?: $hotel?->name ?? 'Dashboard' }}</h1>
      <p class="hd-date mb-0">{{ $today->format('l, d M Y') }} · Front desk overview</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item">Dashboard</li>
    </ul>
  </div>

  @if(($stats['lost_found_open'] ?? 0) > 0)
    <div class="alert alert-warning mb-3">
      <strong><i class="fa fa-search"></i> Lost &amp; Found:</strong>
      {{ $stats['lost_found_open'] }} item(s) in storage
      @if(($stats['lost_found_today'] ?? 0) > 0)
        · {{ $stats['lost_found_today'] }} logged today
      @endif
      —
      <a href="{{ route('hotel.lost-found.index', ['filter' => 'open']) }}" class="alert-link font-weight-bold">Open register</a>
    </div>
  @endif

  <div class="row mb-3">
    <div class="col-6 col-md-3 mb-3 mb-md-0">
      <div class="hd-stat is-brand">
        <div class="hd-stat__label">Arrivals today</div>
        <div class="hd-stat__value">{{ $stats['arrivals'] ?? 0 }}</div>
        <div class="hd-stat__sub">Pending check-in</div>
      </div>
    </div>
    <div class="col-6 col-md-3 mb-3 mb-md-0">
      <div class="hd-stat is-brand">
        <div class="hd-stat__label">Departures today</div>
        <div class="hd-stat__value">{{ $stats['departures'] ?? 0 }}</div>
        <div class="hd-stat__sub">Checking out</div>
      </div>
    </div>
    <div class="col-6 col-md-3 mb-3 mb-md-0">
      <div class="hd-stat is-brand">
        <div class="hd-stat__label">In house</div>
        <div class="hd-stat__value">{{ $stats['in_house'] ?? 0 }}</div>
        <div class="hd-stat__sub">Current guests</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="hd-stat is-brand">
        <div class="hd-stat__label">Occupancy</div>
        <div class="hd-stat__value">{{ $stats['occupancy_pct'] ?? 0 }}%</div>
        <div class="hd-stat__sub">{{ $stats['occupied'] ?? 0 }} / {{ $stats['total_rooms'] ?? 0 }} rooms</div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4 mb-md-0">
      <div class="hd-panel">
        <div class="hd-panel__head">
          <h3>Today's arrivals</h3>
          <a href="{{ route('hotel.check-in.index') }}">Check-in →</a>
        </div>
        @if(count($arrivals))
          <table class="hd-table">
            <thead>
              <tr>
                <th>Guest</th>
                <th>Room</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody>
              @foreach($arrivals as $row)
                <tr>
                  <td><span class="hd-guest">{{ $row['guest'] }}</span></td>
                  <td>{{ $row['room'] }}</td>
                  <td>{{ $row['source'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="hd-empty">No arrivals for today.</div>
        @endif
      </div>
    </div>

    <div class="col-md-6">
      <div class="hd-panel">
        <div class="hd-panel__head">
          <h3>Today's departures</h3>
          <a href="{{ route('hotel.check-out.index') }}">Check-out →</a>
        </div>
        @if(count($departures))
          <table class="hd-table">
            <thead>
              <tr>
                <th>Guest</th>
                <th>Room</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach($departures as $row)
                <tr>
                  <td><span class="hd-guest">{{ $row['guest'] }}</span></td>
                  <td>{{ $row['room'] }}</td>
                  <td>{{ $row['status'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="hd-empty">No departures for today.</div>
        @endif
      </div>
    </div>
  </div>

  <div class="hd-quick">
    <a href="{{ route('hotel.check-in.index') }}"><i class="fa fa-sign-in"></i> Check-in</a>
    <a href="{{ route('hotel.check-out.index') }}"><i class="fa fa-sign-out"></i> Check-out</a>
    <a href="{{ route('hotel.room-view') }}"><i class="fa fa-th-large"></i> Room view</a>
    <a href="{{ route('hotel.channel-manager.live-bookings') }}"><i class="fa fa-columns"></i> Live bookings</a>
    <a href="{{ route('hotel.reservations.index') }}"><i class="fa fa-calendar"></i> Reservations</a>
  </div>
@endsection
