@extends('layouts.app')

@section('title', 'Items list · '.$purchaseList->reference_no)

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-shopping-basket"></i> {{ $purchaseList->reference_no }}</h1>
      <p>{{ $purchaseList->department?->name ?? 'Department' }} · market purchase</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.bar.index', ['tab' => 'market']) }}">Bar · Market</a></li>
      <li class="breadcrumb-item">{{ $purchaseList->reference_no }}</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="tile-title">Items List</h3>
          <div>
            <button type="submit" class="btn btn-secondary btn-sm" form="barMarketItemsForm"
              formaction="{{ route('hotel.bar.purchase-lists.market-draft', $purchaseList) }}" formnovalidate>
              <i class="fa fa-save"></i> Save Draft
            </button>
          </div>
        </div>
        <div class="tile-body">
          @include('hotel.erp.bar.partials._purchase-list-market-complete-inner')
        </div>
      </div>
    </div>
  </div>
@endsection
