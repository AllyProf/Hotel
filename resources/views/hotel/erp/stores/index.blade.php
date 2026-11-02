@extends('layouts.app')

@section('title', config('hotel_stores.title', 'Stores'))

@push('styles')
  @include('hotel.partials.brand-tab-styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@section('content')
  <div class="app-title">
    <div><h1><i class="fa fa-archive"></i> {{ config('hotel_stores.title', 'Stores') }}</h1></div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">Stores</li>
    </ul>
  </div>

  <div class="tile pb-0 stores-page">
    <div class="stores-page-head">
      <ul class="nav nav-tabs stores-main-tabs">
        @foreach($mainTabs as $key => $label)
          <li class="nav-item">
            <a class="nav-link {{ $mainTab === $key ? 'active' : '' }}"
              href="{{ route('hotel.stores.index', ['tab' => $key, 'settings' => $key === 'settings' ? $settingsTab : null]) }}">
              {{ $label }}
              @if($key === 'purchase-orders' && ($storePlPendingCount ?? 0) > 0)
                <span class="badge badge-warning ml-1">{{ $storePlPendingCount }}</span>
              @endif
              @if($key === 'indent' && ($storeIndentPendingCount ?? 0) > 0)
                <span class="badge badge-warning ml-1">{{ $storeIndentPendingCount }}</span>
              @endif
            </a>
          </li>
        @endforeach
      </ul>
    </div>

    @if($mainTab === 'settings')
      <div class="stores-settings-bar">
        <ul class="nav stores-sub-tabs">
          @foreach($settingsTabs as $key => $label)
            <li class="nav-item">
              <a class="nav-link {{ $settingsTab === $key ? 'active' : '' }}"
                href="{{ route('hotel.stores.index', ['tab' => 'settings', 'settings' => $key]) }}">
                {{ $label }}
              </a>
            </li>
          @endforeach
        </ul>
        <div class="stores-settings-bar-actions">
          <a href="{{ route('hotel.roles.index') }}" class="btn btn-light btn-sm mr-2" title="Assign PO approve, invoice, edit, delete">
            <i class="fa fa-lock"></i> Roles &amp; access
          </a>
          <a href="{{ route('hotel.settings.index') }}" class="btn btn-stores-config btn-sm">
            {{ $ui['configure_user'] ?? 'Configure User' }}
          </a>
        </div>
      </div>

      @if($settingsTab === 'master-items' && $masterData)
        @include('hotel.erp.stores.partials.master-items')
      @elseif($settingsTab === 'item-category')
        @include('hotel.erp.stores.partials.item-categories')
      @elseif($settingsTab === 'tax-groups')
        @include('hotel.erp.stores.partials.tax-groups')
      @elseif($settingsTab === 'units-packaging')
        @include('hotel.erp.stores.partials.units-packaging')
      @elseif($settingsTab === 'vendor')
        @include('hotel.erp.stores.partials.vendors')
      @elseif($settingsTab === 'department')
        @include('hotel.erp.stores.partials.departments')
      @else
        @include('hotel.erp.stores.partials.placeholder')
      @endif
    @elseif($mainTab === 'purchase-orders')
      @include('hotel.erp.stores.partials.purchase-orders')
    @elseif($mainTab === 'invoices')
      @include('hotel.erp.stores.partials.invoices')
    @elseif($mainTab === 'stock' && $stockData)
      @include('hotel.erp.stores.partials.stock')
    @elseif($mainTab === 'indent')
      @include('hotel.erp.stores.partials.indents')
    @elseif($mainTab === 'consumption')
      @include('hotel.erp.stores.partials.consumption')
    @elseif($mainTab === 'reports' && $reportData)
      @include('hotel.erp.stores.partials.reports')
    @else
      @include('hotel.erp.stores.partials.placeholder')
    @endif
  </div>
@endsection
