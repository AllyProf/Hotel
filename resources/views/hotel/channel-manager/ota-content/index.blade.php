@extends('layouts.app')

@section('title', 'OTA Content')

@push('styles')
  <style>
    .ota-content-header {
      border-top: 3px solid #940000;
      padding: 20px;
    }
    .ota-room-card {
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 16px;
      margin-bottom: 16px;
      background: #fff;
    }
    .ota-room-card h4 {
      margin-bottom: 8px;
      font-size: 18px;
    }
    .ota-meta {
      color: #666;
      font-size: 13px;
      margin-bottom: 10px;
    }
    .ota-rate-table th {
      background: #f5f5f5;
      font-size: 12px;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-th"></i> OTAs <small class="text-muted">OTA Content</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">OTAs</a></li>
      <li class="breadcrumb-item">OTA Content</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="ota-content-header d-flex flex-wrap align-items-center justify-content-between">
          <div>
            @if($loaded && $property)
              <h3 class="mb-1">{{ $property['hotel_name'] ?: $hotel->name }}</h3>
              <div class="ota-meta">
                Hotel code: {{ $property['hotel_id'] ?: '—' }}
                @if(!empty($property['currency']))
                  · {{ $property['currency'] }}
                @endif
                @if(!empty($property['category']))
                  · {{ $property['category'] }}
                @endif
              </div>
              @php $address = $property['address'] ?? []; @endphp
              @if(!empty($address['line']) || !empty($address['city']))
                <div class="ota-meta">
                  {{ trim(($address['line'] ?? '').', '.($address['city'] ?? ''), ', ') }}
                </div>
              @endif
            @else
              <h3 class="mb-1">{{ $hotel->name }}</h3>
              <div class="text-muted">Property content could not be loaded from Channel Manager.</div>
            @endif
          </div>
          <form method="POST" action="{{ route('hotel.channel-manager.ota-content.refresh') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Refresh from API</button>
          </form>
        </div>

        @if($loaded && $rooms !== [])
          @foreach($rooms as $room)
            <div class="ota-room-card">
              <h4>{{ $room['name'] ?: $room['code'] }}</h4>
              <div class="ota-meta">
                Code: {{ $room['code'] ?: '—' }}
                · Rooms: {{ $room['count'] }}
                · Occupancy: {{ $room['min_occ'] }}-{{ $room['max_occ'] }}
                · {{ $room['active'] ? 'Active' : 'Inactive' }}
              </div>
              @if($room['description'] !== '')
                <p class="mb-3">{{ $room['description'] }}</p>
              @endif

              @if($room['rate_plans'] !== [])
                <div class="table-responsive">
                  <table class="table table-sm table-bordered ota-rate-table mb-0">
                    <thead>
                      <tr>
                        <th>Rate Plan</th>
                        <th>Code</th>
                        <th>Occupancy</th>
                        <th>Meals</th>
                        <th>Description</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($room['rate_plans'] as $plan)
                        <tr>
                          <td>{{ $plan['name'] ?: '—' }}</td>
                          <td>{{ $plan['code'] ?: '—' }}</td>
                          <td>{{ $plan['occupancy'] ?: '—' }}</td>
                          <td>{{ $plan['meals'] }}</td>
                          <td>{{ $plan['description'] !== '' ? $plan['description'] : '—' }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          @endforeach
        @elseif($loaded)
          <div class="p-4 font-weight-bold">No room content returned from Channel Manager.</div>
        @endif
      </div>
    </div>
  </div>
@endsection
