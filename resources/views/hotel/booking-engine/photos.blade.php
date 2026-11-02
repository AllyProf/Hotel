@extends('layouts.app')

@section('title', 'Photos — Booking Engine')

@push('styles')
  @include('hotel.booking-engine.partials._layout-styles')
  <style>
    .be-photo-thumb {
      width: 96px;
      height: 72px;
      object-fit: cover;
      border-radius: 4px;
      border: 1px solid #dee2e6;
      background: #f8f9fa;
    }

    .be-photo-placeholder {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 96px;
      height: 72px;
      border: 1px dashed #ccc;
      border-radius: 4px;
      color: #999;
      font-size: 11px;
      text-align: center;
      padding: 4px;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-picture-o"></i> Booking Engine Photos</h1>
      <p>Room photos shown on your public booking page</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Photos</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @include('hotel.booking-engine.partials._subnav')

        <div class="tile-body">
          <p class="text-muted">Upload up to {{ $ui['max_photos_per_room'] ?? 8 }} images per room type under <strong>My Hotel → Rooms</strong>. The primary photo is used first on the booking engine.</p>

          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead class="thead-light">
                <tr>
                  <th>Room type</th>
                  <th>Photos</th>
                  <th>Primary</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($rooms as $room)
                  @php
                    $photos = $room->photos->sortBy('sort_order');
                    $primary = $photos->firstWhere('is_primary', true) ?? $photos->first();
                  @endphp
                  <tr>
                    <td class="font-weight-bold">{{ $room->display_name ?: $room->name }}</td>
                    <td>
                      <div class="d-flex flex-wrap" style="gap:8px;">
                        @forelse($photos->take(4) as $photo)
                          <img src="{{ $photo->url() }}" alt="" class="be-photo-thumb">
                        @empty
                          <span class="be-photo-placeholder">No photos</span>
                        @endforelse
                        @if($photos->count() > 4)
                          <span class="be-photo-placeholder">+{{ $photos->count() - 4 }} more</span>
                        @endif
                      </div>
                    </td>
                    <td>
                      @if($primary)
                        <img src="{{ $primary->url() }}" alt="" class="be-photo-thumb">
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td class="text-right">
                      <a href="{{ route('hotel.rooms.edit', $room) }}" class="btn btn-sm btn-primary">
                        <i class="fa fa-edit"></i> Manage photos
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-4">
                      No room types yet.
                      <a href="{{ route('hotel.rooms.create') }}">Add a room type</a>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
