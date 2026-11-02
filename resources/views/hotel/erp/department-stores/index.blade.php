@extends('layouts.app')

@section('title', $ui['title'] ?? 'Department Stores')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-inbox"></i> ERP <small class="text-muted">{{ $ui['title'] ?? 'Department Stores' }}</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Department Stores' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <ul class="nav nav-tabs pms-brand-tabs mb-3">
            @foreach($ui['tabs'] ?? [] as $key => $label)
              <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                  href="{{ route('hotel.department-stores.index', ['tab' => $key]) }}">{{ $label }}
                  @if($key === 'store-requests' && ($deptStoreRequestAwaitingReceiveCount ?? 0) > 0)
                    <span class="badge badge-warning ml-1">{{ $deptStoreRequestAwaitingReceiveCount }}</span>
                  @endif
                </a>
              </li>
            @endforeach
          </ul>

          @if($tab === 'store-requests')
            @include('hotel.erp.department-stores.partials.store-requests')
          @elseif($tab === 'dept-stock')
            @include('hotel.erp.department-stores.partials.dept-stock')
          @elseif($tab === 'consumption')
            @include('hotel.erp.department-stores.partials.consumption')
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
