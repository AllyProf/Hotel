@extends('layouts.app')

@section('title', 'Rooms')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-bed"></i> Rooms</h1>
      <p>Manage room types for {{ $hotel->name }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="#">Rooms</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">All Room Types</h3>
          <p>
            @if($canAddRoom)
              <a class="btn btn-primary icon-btn" href="{{ route('hotel.rooms.create') }}">
                <i class="fa fa-plus"></i> Add Room
              </a>
            @else
              <span class="text-muted small">
                Room limit reached ({{ $hotel->rooms()->count() }} / {{ $hotel->maxRooms() ?: '∞' }})
              </span>
            @endif
          </p>
        </div>
        <div class="tile-body">
          @if($hotel->maxRooms() > 0)
            <p class="text-muted small mb-3">
              Plan limit: {{ $hotel->rooms()->count() }} of {{ $hotel->maxRooms() }} room types used.
            </p>
          @endif

          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Room Type</th>
                  <th>Rooms</th>
                  <th>Room Numbers</th>
                  <th>Max Guests</th>
                  <th>Pricing</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($rooms as $room)
                  <tr>
                    <td>{{ $rooms->firstItem() + $loop->index }}</td>
                    <td>
                      <strong>{{ $room->name }}</strong>
                      @if($room->description)
                        <br><small class="text-muted">{{ $room->description }}</small>
                      @endif
                    </td>
                    <td>{{ $room->room_count }}</td>
                    <td>
                      @if($room->units->isNotEmpty())
                        @foreach($room->units as $unit)
                          <span class="badge badge-light border">{{ $unit->room_number }}@if($unit->label) <small class="text-muted">({{ $unit->label }})</small>@endif</span>
                        @endforeach
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>{{ $room->max_occupancy }}</td>
                    <td>
                      @php
                        $plan = $room->ratePlans->first();
                        $fmtPrice = function ($value) {
                            if ($value === null || $value === '') {
                                return '0';
                            }
                            $amount = (float) $value;

                            return fmod($amount, 1.0) === 0.0
                                ? number_format($amount, 0, '.', ',')
                                : rtrim(rtrim(number_format($amount, 2, '.', ','), '0'), '.');
                        };
                      @endphp
                      @if($plan)
                        @php
                          $hasLocal = (float) ($plan->local_base_rate ?? 0) > 0;
                          $hasIntl = (float) ($plan->base_rate ?? 0) > 0;
                        @endphp
                        @if($hasLocal || $hasIntl)
                          <small>
                            @if($hasLocal)
                              Local: <strong>{{ $fmtPrice($plan->local_base_rate) }} {{ $plan->local_currency ?? $hotel->currency }}</strong>
                            @endif
                            @if($hasLocal && $hasIntl)<br>@endif
                            @if($hasIntl)
                              Intl: <strong>{{ $fmtPrice($plan->base_rate) }} {{ $plan->international_currency ?? 'USD' }}</strong>
                            @endif
                            @if($room->photos_count > 0)
                              <br><span class="text-muted">{{ $room->photos_count }} photo(s)</span>
                            @endif
                          </small>
                        @else
                          <a href="{{ route('hotel.settings.index', ['tab' => 'rateplan']) }}" class="small">Set prices</a>
                        @endif
                      @else
                        <a href="{{ route('hotel.settings.index', ['tab' => 'rateplan']) }}" class="small">Set prices</a>
                      @endif
                    </td>
                    <td>
                      @if($room->is_enabled)
                        <span class="badge badge-success">Active</span>
                      @else
                        <span class="badge badge-secondary">Inactive</span>
                      @endif
                    </td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="{{ route('hotel.rooms.edit', $room) }}">
                        <i class="fa fa-edit"></i> Edit
                      </a>
                      <form action="{{ route('hotel.rooms.destroy', $room) }}" method="POST" class="d-inline js-swal-delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" data-title="Delete room?" data-text="This will permanently remove {{ $room->name }}.">
                          <i class="fa fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                      No rooms yet.
                      @if($canAddRoom)
                        <a href="{{ route('hotel.rooms.create') }}">Add your first room</a> — only 4 fields to fill in.
                      @endif
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          {{ $rooms->links() }}

          <p class="text-muted small mt-3 mb-0">
            <i class="fa fa-info-circle"></i>
            Manage prices under <a href="{{ route('hotel.settings.index', ['tab' => 'rateplan']) }}">Settings → Prices</a>.
            Update daily availability under <a href="{{ route('hotel.channel-manager.update-rooms') }}">Update Rooms</a>.
          </p>
        </div>
      </div>
    </div>
  </div>
@endsection
