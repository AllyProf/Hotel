@extends('layouts.app')

@section('title', 'Website Config — Booking Engine')

@push('styles')
  @include('hotel.booking-engine.partials._layout-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-cog"></i> Website Config</h1>
      <p>Room descriptions, amenities, policies, and tracking for the booking engine</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Website Config</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @include('hotel.booking-engine.partials._subnav')

        <div class="tile-body">
          @php
            $beFormRedirectTo = 'booking-engine';
            $beSettingsRoute = 'hotel.booking-engine.website-config';
          @endphp
          @include('hotel.settings.partials.tab-be')
        </div>
      </div>
    </div>
  </div>
@endsection
