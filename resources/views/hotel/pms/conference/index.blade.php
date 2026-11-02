@extends('layouts.app')

@section('title', $ui['title'] ?? 'Conference')

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-users"></i> {{ $ui['title'] ?? 'Conference & Events' }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Conference</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile sv-page">
        @if(session('success'))<div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>@endif

        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-bottom" style="border-bottom-color:var(--sv-brand)!important;border-bottom-width:3px!important;">
          <h3 class="mb-0" style="font-weight:400;">Events desk</h3>
          <div>
            <a href="{{ route('hotel.services.show', $service) }}" class="btn btn-light border btn-sm mr-1">Manage rates</a>
            <a href="{{ route('hotel.conference.create') }}" class="btn btn-sv" style="width:auto;padding:8px 18px;"><i class="fa fa-plus"></i> New booking</a>
          </div>
        </div>

        <div class="sv-section">
          <ul class="nav nav-tabs mb-3">
            @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
              <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active font-weight-bold' : '' }}" href="{{ route('hotel.conference.index', ['tab' => $key]) }}">{{ $label }}</a>
              </li>
            @endforeach
          </ul>

          <div class="table-responsive">
            <table class="table table-bordered table-sm">
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Event</th>
                  <th>When</th>
                  <th>Package</th>
                  <th>Guest / company</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($bookings as $b)
                  <tr>
                    <td>{{ $b->booking_no }}</td>
                    <td>{{ $b->event_name }}</td>
                    <td>{{ $b->starts_at?->format('d/m/Y H:i') }} – {{ $b->ends_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $b->rate_label ?? '—' }}</td>
                    <td>
                      {{ $b->company_name ?: ($b->guest_name ?: $b->organizer_name ?: '—') }}
                      @if($b->room_no)<br><span class="text-muted small">Room {{ $b->room_no }}</span>@endif
                    </td>
                    <td>{{ $b->formattedTotal($hotel) }}</td>
                    <td>{{ $b->statusLabel() }}</td>
                    <td class="text-nowrap">
                      <a href="{{ route('hotel.conference.show', $b) }}" class="btn btn-sm btn-light border">View</a>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="8" class="text-muted text-center py-4">No bookings in this tab.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
