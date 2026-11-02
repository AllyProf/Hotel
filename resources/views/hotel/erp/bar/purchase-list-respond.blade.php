@extends('layouts.app')

@php
  $procW = $procWorkflow ?? $purchaseList->workflowKey();
  $storesLayout = ($respondStoresLayout ?? false) || request()->query('return') === 'stores';
  $receivedShowUrl = \App\Support\DepartmentProcurement::route($procW, 'purchase-lists.received.show', $purchaseList);
  $moduleLabel = \App\Support\DepartmentProcurement::workflow($procW)['label'] ?? 'Received';
@endphp

@section('title', ($locked ? 'Budget' : 'Edit').' · '.$purchaseList->reference_no)

@push('styles')
  @if($storesLayout)
    @include('hotel.partials.brand-tab-styles')
    @include('hotel.erp.stores.partials._styles')
  @endif
  @include('hotel.erp.bar.partials._purchase-list-respond-picker-styles')
@endpush

@push('scripts')
  @include('hotel.erp.bar.partials._purchase-list-respond-scripts')
@endpush

@section('content')
  @if($storesLayout)
    <div class="app-title">
      <div>
        <h1><i class="fa fa-archive"></i> {{ config('hotel_stores.title', 'Stores') }}</h1>
        <p>{{ $purchaseList->reference_no }} · {{ $locked ? 'Prepared budget' : 'Price list for Accounts' }}</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
        <li class="breadcrumb-item"><a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}">Stores</a></li>
        <li class="breadcrumb-item"><a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}">Purchase Orders</a></li>
        <li class="breadcrumb-item">{{ $purchaseList->reference_no }}</li>
      </ul>
    </div>

    <div class="tile pb-0 stores-page">
      <div class="px-3 pt-3 pb-2 d-flex flex-wrap align-items-start justify-content-between border-bottom">
        <div>
          <h3 class="h5 font-weight-bold mb-1">{{ $locked ? 'Submitted budget' : 'Prepare budget' }}</h3>
          <p class="text-muted small mb-0">{{ $moduleLabel }} · {{ $purchaseList->department?->name ?? 'Department' }}</p>
        </div>
        <div class="stores-respond-head-actions">
          <a href="{{ $receivedShowUrl }}" class="btn btn-light btn-sm border">View request</a>
          <a href="{{ $respondBackUrl ?? route('hotel.stores.index', ['tab' => 'purchase-orders']) }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left"></i> {{ $respondBackLabel ?? 'Purchase Orders' }}
          </a>
        </div>
      </div>
      <div class="py-3">
        @include('hotel.erp.bar.partials._purchase-list-respond-inner', ['storesTemplate' => true])
      </div>
    </div>
  @else
    <div class="app-title">
      <div>
        <h1><i class="fa fa-edit"></i> {{ $purchaseList->reference_no }}</h1>
        <p>{{ $purchaseList->department?->name ?? 'Department' }} · {{ $locked ? 'Prepared budget' : 'Edit & expected prices' }}</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><a href="{{ $respondBackUrl ?? \App\Support\DepartmentProcurement::indexUrl($procW, 'received') }}">{{ $respondBackLabel ?? 'Received' }}</a></li>
        <li class="breadcrumb-item"><a href="{{ $receivedShowUrl }}">View</a></li>
        <li class="breadcrumb-item">{{ $locked ? 'Budget' : 'Edit' }}</li>
      </ul>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <div class="tile-title-w-btn">
            <h3 class="tile-title">{{ $locked ? 'Submitted budget' : 'Prepare budget' }}</h3>
            <div>
              <a href="{{ $receivedShowUrl }}" class="btn btn-light border btn-sm mr-2">View</a>
              <a href="{{ $respondBackUrl ?? \App\Support\DepartmentProcurement::indexUrl($procW, 'received') }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-arrow-left"></i> Back
              </a>
            </div>
          </div>
          <div class="tile-body">
            @include('hotel.erp.bar.partials._purchase-list-respond-inner', ['storesTemplate' => false])
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection
