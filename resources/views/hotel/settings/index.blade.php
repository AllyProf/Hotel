@extends('layouts.app')

@section('title', 'Live Hotel Settings')

@push('styles')
  <style>
    .hotel-settings-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      border-bottom: 2px solid rgba(0,0,0,.08);
      margin-bottom: 20px;
      padding-bottom: 0;
    }
    .hotel-settings-tabs a {
      padding: 10px 16px;
      color: #333 !important;
      font-weight: 600;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      text-decoration: none;
    }
    .hotel-settings-tabs a.active {
      color: #940000 !important;
      border-bottom-color: #940000;
    }
    .settings-section-title {
      font-weight: 700;
      margin: 20px 0 12px;
      padding-bottom: 6px;
      border-bottom: 1px solid rgba(0,0,0,.08);
    }
    .settings-table th { font-size: 13px; white-space: nowrap; }
    .amenity-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 8px;
    }
    .amenity-grid label {
      font-size: 13px;
      font-weight: 400;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .settings-save-bar {
      position: sticky;
      bottom: 0;
      background: #fff;
      border-top: 1px solid rgba(0,0,0,.1);
      padding: 14px 0;
      margin-top: 20px;
      z-index: 5;
    }
    .whatsapp-panel {
      border: 1px solid rgba(0,0,0,.08);
      background: #fafafa;
      max-width: 720px;
    }
    .whatsapp-panel__head {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 14px 18px;
      background: #eceff1;
      border-bottom: 1px solid rgba(0,0,0,.08);
      font-weight: 700;
      font-size: 16px;
      color: #222 !important;
    }
    .whatsapp-panel__head .fa-whatsapp {
      font-size: 22px;
      color: #25d366;
    }
    .whatsapp-panel__body {
      padding: 28px 18px;
      background: #fdfdfd;
    }
    .btn-whatsapp-connect {
      background: #1877f2 !important;
      border-color: #1877f2 !important;
      color: #fff !important;
      font-weight: 700;
      padding: 12px 22px;
      font-size: 15px;
      border-radius: 4px;
    }
    .btn-whatsapp-connect:hover,
    .btn-whatsapp-connect:focus {
      background: #166fe0 !important;
      border-color: #166fe0 !important;
      color: #fff !important;
    }
    .whatsapp-connected-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(37, 211, 102, 0.12);
      color: #128c7e;
      padding: 8px 14px;
      border-radius: 4px;
      font-weight: 600;
      margin-bottom: 16px;
    }
    .whatsapp-advanced {
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid rgba(0,0,0,.08);
    }
  </style>
@endpush

@section('content')
  @php
    $pms = $settings->pms ?? [];
    $be = $settings->be ?? [];
    $whatsapp = $settings->whatsapp ?? [];
    $laundry = $settings->laundry ?? [];
    $reservation = $settings->reservation ?? [];
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-cog"></i> Live Hotel Settings</h1>
      <p>Configure hotel, PMS, booking engine, and integrations</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="#">Settings</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-body">
          @include('hotel.settings.partials._tabs-nav')

          @if($tab === 'hotel')
            @include('hotel.settings.partials.tab-hotel')
          @elseif($tab === 'rooms')
            @include('hotel.settings.partials.tab-rooms')
          @elseif($tab === 'rateplan')
            @include('hotel.settings.partials.tab-rateplan')
          @elseif($tab === 'amenities')
            @include('hotel.settings.partials.tab-amenities')
          @elseif($tab === 'pms')
            @include('hotel.settings.partials.tab-pms')
          @elseif($tab === 'laundry')
            @include('hotel.settings.partials.tab-laundry')
          @elseif($tab === 'pms-services')
            @include('hotel.settings.partials.tab-pms-services')
          @elseif($tab === 'pms-category')
            @include('hotel.settings.partials.tab-pms-category')
          @elseif($tab === 'be')
            @include('hotel.settings.partials.tab-be')
          @elseif($tab === 'whatsapp')
            @include('hotel.settings.partials.tab-whatsapp')
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
