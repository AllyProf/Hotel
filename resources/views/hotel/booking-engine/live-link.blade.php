@extends('layouts.app')

@section('title', 'Live Link — Booking Engine')

@push('styles')
  @include('hotel.booking-engine.partials._layout-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-globe"></i> Booking Engine</h1>
      <p>Publish and share your direct booking link</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Live Link</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @include('hotel.booking-engine.partials._subnav')

        @php
          $beDash = config('hotel_dashboard.booking_engine');
        @endphp

        <div class="tile-title-w-btn">
          <h3 class="tile-title">{{ $beDash['title'] ?? 'Direct booking' }}</h3>
          @if($bookingEngine['enabled'] ?? false)
            <span class="badge badge-success">Live</span>
          @else
            <span class="badge badge-secondary">Not published</span>
          @endif
        </div>

        <div class="tile-body">
          <p class="text-muted mb-4">{{ $beDash['intro'] ?? '' }}</p>

          <form method="POST" action="{{ route('hotel.booking-engine.live-link.update') }}" class="mb-4">
            @csrf
            @method('PUT')

            <div class="card border mb-4">
              <div class="card-body">
                <div class="form-group">
                  <label class="mb-0">
                    <input type="checkbox" name="enabled" value="1" {{ old('enabled', $bookingEngine['enabled'] ?? false) ? 'checked' : '' }}>
                    Enable direct booking on my website
                  </label>
                </div>

                <div class="form-group mb-0" id="be-booking-link">
                  <label class="control-label font-weight-bold">Your booking link</label>
                  <div class="input-group">
                    <input type="text" class="form-control" readonly value="{{ $bookingEngine['booking_url'] ?? '' }}">
                    <div class="input-group-append">
                      <button type="button" class="btn btn-outline-secondary js-copy-text" data-copy="{{ $bookingEngine['booking_url'] ?? '' }}">Copy</button>
                      @if(! empty($bookingEngine['booking_url']))
                        <a class="btn btn-outline-primary" href="{{ $bookingEngine['booking_url'] }}" target="_blank" rel="noopener">Open</a>
                      @endif
                    </div>
                  </div>
                  <small class="text-muted">Share this link on your website, Google profile, or social media.</small>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Custom link name</label>
                  <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">/book/</span></div>
                    <input type="text" class="form-control" name="public_slug"
                      value="{{ old('public_slug', $bookingEngine['public_slug'] ?? '') }}"
                      pattern="[a-z0-9-]+" title="Lowercase letters, numbers, and hyphens only">
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Custom domain <small class="text-muted">(optional)</small></label>
                  <input type="text" class="form-control" name="custom_domain"
                    value="{{ old('custom_domain', $bookingEngine['custom_domain'] ?? '') }}"
                    placeholder="book.yourhotel.com">
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
            <a href="{{ route('hotel.booking-engine.website-config') }}" class="btn btn-outline-primary ml-2">
              <i class="fa fa-cog"></i> Policies &amp; tracking
            </a>
          </form>

          @if(! empty($beDash['steps']))
            <h5 class="font-weight-bold mb-3">Quick setup</h5>
            @foreach($beDash['steps'] as $index => $step)
              <div class="hotel-start-step mb-3">
                <span class="hotel-start-step__num">{{ $index + 1 }}</span>
                <div class="flex-grow-1">
                  <h5 class="mb-1">{{ $step['title'] }}</h5>
                  <p class="text-muted mb-2">{{ $step['body'] }}</p>
                  @if(! empty($step['route']))
                    <a class="btn btn-primary btn-sm" href="{{ route($step['route'], $step['route_params'] ?? $step['params'] ?? []) }}">{{ $step['label'] }}</a>
                  @elseif(! empty($step['anchor']))
                    <a class="btn btn-outline-primary btn-sm" href="{{ $step['anchor'] }}">{{ $step['label'] }}</a>
                  @endif
                </div>
              </div>
            @endforeach
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.querySelectorAll('.js-copy-text').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy') || '';
        if (!text || !navigator.clipboard) return;
        navigator.clipboard.writeText(text);
      });
    });
  </script>
@endpush
