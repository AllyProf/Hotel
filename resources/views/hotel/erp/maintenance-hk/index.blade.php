@extends('layouts.app')

@section('title', $ui['title'] ?? 'Maintenance & HK')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-asterisk"></i> ERP <small class="text-muted">{{ $ui['title'] ?? 'Maintenance & HK' }}</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Maintenance & HK' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">{{ $ui['title'] ?? 'Maintenance & HK' }}</h3>
          @if($tab === 'maintenance')
            <p>
              <button type="button" class="btn btn-primary icon-btn" data-toggle="modal" data-target="#mhNewOrderModal">
                <i class="fa fa-plus"></i> New work order
              </button>
            </p>
          @elseif($tab === 'lost_found')
            <p>
              <button type="button" class="btn btn-primary icon-btn" data-toggle="modal" data-target="#mhLostFoundModal">
                <i class="fa fa-plus"></i> Log item
              </button>
            </p>
          @endif
        </div>

        <div class="tile-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif
          @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
          @endif

          <ul class="nav nav-tabs pms-brand-tabs mb-3">
            @foreach($ui['tabs'] ?? [] as $key => $label)
              <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                  href="{{ route('hotel.maintenance-hk.index', ['tab' => $key, 'filter' => $filter]) }}">{{ $label }}
                  @if($key === 'store-requests' && ($hkStoreRequestAwaitingReceiveCount ?? 0) > 0)
                    <span class="badge badge-warning ml-1">{{ $hkStoreRequestAwaitingReceiveCount }}</span>
                  @endif
                </a>
              </li>
            @endforeach
          </ul>

          @if($isProcurementTab ?? false)
            @include('hotel.erp.procurement.partials._module-shell')
          @else
            @if($tab !== 'rooms' && ! in_array($tab, ['store-requests', 'dept-stock', 'consumption'], true))
              <ul class="nav nav-pills pms-brand-tabs mb-4">
                @foreach(['open' => 'Open', 'done' => 'Completed', 'all' => 'All'] as $fKey => $fLabel)
                  <li class="nav-item">
                    <a class="nav-link {{ $filter === $fKey ? 'active' : '' }}"
                      href="{{ route('hotel.maintenance-hk.index', ['tab' => $tab, 'filter' => $fKey]) }}">{{ $fLabel }}</a>
                  </li>
                @endforeach
              </ul>
            @endif

            @if($tab === 'housekeeping')
              @include('hotel.erp.maintenance-hk.partials.housekeeping')
            @elseif($tab === 'maintenance')
              @include('hotel.erp.maintenance-hk.partials.maintenance')
            @elseif($tab === 'lost_found')
              <p class="text-muted small mb-3">
                Log found items here only. Reception claims and closes entries under
                <a href="{{ route('hotel.lost-found.index') }}">PMS → Lost &amp; Found</a>.
              </p>
              @include('hotel.erp.maintenance-hk.partials.lost-found', [
                'lostFoundReturnTo' => 'maintenance_hk',
                'lostFoundFilter' => $filter,
              ])
            @elseif($tab === 'rooms')
              @include('hotel.erp.maintenance-hk.partials.room-board')
            @elseif($tab === 'store-requests')
              @include('hotel.erp.maintenance-hk.partials.store-requests')
            @elseif($tab === 'dept-stock')
              @include('hotel.erp.maintenance-hk.partials.dept-stock')
            @elseif($tab === 'consumption')
              @include('hotel.erp.maintenance-hk.partials.consumption')
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>

  @include('hotel.erp.maintenance-hk.partials.new-order-modal')
  @include('hotel.erp.maintenance-hk.partials.new-lost-found-modal', ['lostFoundReturnTo' => 'maintenance_hk'])
@endsection
