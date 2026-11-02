@extends('layouts.app')

@section('title', 'New Sale')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-shopping-cart"></i> New Sale</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">New Sale</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="tile pb-0">
    @php
      $sellCatalogLayout = 'browse';
      $catalogView = $catalogView ?? 'list';
    @endphp
    @include('hotel.erp.bar.partials._tab-sell')
  </div>
@endsection
