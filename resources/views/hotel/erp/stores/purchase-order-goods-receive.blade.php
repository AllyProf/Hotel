@extends('layouts.app')

@section('title', 'Items list · '.$goodsReceiveOrder->po_number)

@push('styles')
  @include('hotel.erp.bar.partials._bar-market-page-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-shopping-basket"></i> {{ $goodsReceiveOrder->po_number }}</h1>
      <p>{{ $goodsReceiveOrder->storeDepartment?->name ?? 'Store' }} · receive into stock</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}">Stores · Purchase Orders</a></li>
      <li class="breadcrumb-item">{{ $goodsReceiveOrder->po_number }}</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="tile-title">Items List</h3>
          <div>
            @if(! ($readOnly ?? $goodsReceiveOrder->goods_received))
              <button type="submit" class="btn btn-secondary btn-sm" form="storesPoReceiveForm"
                formaction="{{ route('hotel.stores.purchase-orders.receive-draft', $goodsReceiveOrder) }}" formnovalidate>
                <i class="fa fa-save"></i> Save Draft
              </button>
            @endif
          </div>
        </div>
        <div class="tile-body">
          @include('hotel.erp.stores.partials._purchase-order-goods-receive-inner')
        </div>
      </div>
    </div>
  </div>
@endsection
