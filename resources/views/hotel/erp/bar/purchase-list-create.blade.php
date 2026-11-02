@extends('layouts.app')

@section('title', ($reviseList ?? null) ? 'Edit purchase list' : 'New purchase list')

@push('styles')
  <link rel="stylesheet" href="{{ asset('panel-assets/css/select2.min.css') }}">
  <style>
    .bar-pl-item-select-wrap .select2-container {
      width: 100% !important;
    }
    .bar-pl-qty-input::-webkit-outer-spin-button,
    .bar-pl-qty-input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .bar-pl-qty-input {
      -moz-appearance: textfield;
      appearance: textfield;
      min-width: 72px;
    }
    .bar-pl-qty-cell {
      position: relative;
      z-index: 2;
    }
    .bar-pl-qty-change {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #940000;
      margin-top: 2px;
      line-height: 1.2;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-paper-plane"></i> {{ ($reviseList ?? null) ? 'Edit & send · '.$reviseList->reference_no : 'New purchase list' }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      @php($procW = $procWorkflow ?? 'bar')
      <li class="breadcrumb-item"><a href="{{ \App\Support\DepartmentProcurement::indexUrl($procW, 'purchase-lists') }}">{{ \App\Support\DepartmentProcurement::label($procW) }}</a></li>
      <li class="breadcrumb-item">{{ ($reviseList ?? null) ? 'Edit & send' : 'New list' }}</li>
    </ul>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">{{ ($reviseList ?? null) ? 'Update list' : 'Prepare list' }}</h3>
        <div class="tile-body">
          @include('hotel.erp.bar.partials._purchase-list-form')
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @include('hotel.erp.bar.partials._purchase-list-form-scripts')
@endpush
