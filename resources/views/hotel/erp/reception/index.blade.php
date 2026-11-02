@extends('layouts.app')

@section('title', 'Reception · Procurement')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-desktop"></i> Reception <small class="text-muted">Purchase orders</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">Reception</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="tile">
    <div class="tile-body">
      <ul class="nav nav-tabs pms-brand-tabs mb-3 flex-wrap">
        @foreach([
          'purchase-lists' => 'Purchase lists',
          'received' => 'Received',
          'stock-receive' => 'Receive stock',
        ] as $key => $label)
          <li class="nav-item">
            <a class="nav-link {{ ($tab ?? '') === $key ? 'active' : '' }}"
              href="{{ route('hotel.reception.index', ['tab' => $key, 'department' => request('department')]) }}">
              {{ $label }}
              @if($key === 'stock-receive' && ($stockReceivePendingCount ?? 0) > 0)
                <span class="badge badge-warning ml-1">{{ $stockReceivePendingCount }}</span>
              @endif
            </a>
          </li>
        @endforeach
      </ul>
      @include('hotel.erp.procurement.partials._module-shell')
    </div>
  </div>
@endsection
