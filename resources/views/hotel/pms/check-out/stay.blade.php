@extends('layouts.app')

@section('title', 'Stay — '.$summary['guest'])

@push('styles')
  <style>:root { --co-brand: #940000; --co-brand-dark: #7a0000; --co-brand-soft: rgba(148, 0, 0, 0.08); }</style>
  @include('hotel.pms.check-out.partials._stay-detail-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-address-card-o"></i> Guest stay</h1>
      <p>Folio and activity for in-house / departing guests</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item"><a href="{{ $backUrl }}">Check-out</a></li>
      <li class="breadcrumb-item">{{ $summary['booking_id'] ?? 'Stay' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="co-stay-page-head">
          <div>
            <h3><i class="fa fa-user"></i>{{ $summary['guest'] }}</h3>
            <p class="co-stay-subtitle">
              Booking {{ $summary['booking_id'] }}
              @if(! empty($summary['room_no']))
                · Room {{ $summary['room_no'] }}
              @elseif(! empty($summary['room_type']))
                · {{ $summary['room_type'] }}
              @endif
              · {{ $summary['pms_status'] }}
              · As of {{ $date->format('d M Y') }}
            </p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ $backUrl }}" class="btn btn-sm btn-co-outline">
              <i class="fa fa-arrow-left"></i> Back to check-out
            </a>
            <a href="{{ $printAllUrl }}" class="btn btn-sm btn-co-outline" target="_blank" rel="noopener">
              <i class="fa fa-print"></i> Print all bills
            </a>
            <a href="{{ $summary['reservation_url'] }}" class="btn btn-sm btn-co-primary">
              <i class="fa fa-external-link"></i> Full booking
            </a>
          </div>
        </div>

        <div class="p-3 p-md-4">
          @if(isset($foundInRoom) && $foundInRoom->isNotEmpty())
            <div class="alert alert-warning">
              <strong><i class="fa fa-search"></i> Lost &amp; Found in this room</strong>
              <ul class="mb-0 mt-2 pl-3">
                @foreach($foundInRoom as $foundItem)
                  <li>
                    <strong>{{ $foundItem->reference_no }}</strong> — {{ $foundItem->item_name }}
                    @if($foundItem->storage_location)
                      <span class="text-muted">({{ $foundItem->storage_location }})</span>
                    @endif
                  </li>
                @endforeach
              </ul>
              <a href="{{ route('hotel.lost-found.index', ['filter' => 'open']) }}" class="alert-link">Open Lost &amp; Found register</a>
            </div>
          @endif
          @include('hotel.pms.check-out.partials._stay-detail-content', ['summary' => $summary])
        </div>
      </div>
    </div>
  </div>
@endsection

@push('styles')
  <style>
    .btn-co-outline {
      background: #fff !important;
      border-color: #d1d5db !important;
      color: #555 !important;
      font-size: 12px;
      font-weight: 600;
    }

    .btn-co-outline:hover {
      background: var(--co-brand-soft) !important;
      border-color: var(--co-brand) !important;
      color: var(--co-brand) !important;
    }

    .btn-co-primary {
      background: var(--co-brand) !important;
      border-color: var(--co-brand) !important;
      color: #fff !important;
      font-size: 12px;
      font-weight: 600;
    }

    .btn-co-primary:hover {
      background: var(--co-brand-dark) !important;
      border-color: var(--co-brand-dark) !important;
    }
  </style>
@endpush
