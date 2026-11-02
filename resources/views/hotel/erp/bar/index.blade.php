@extends('layouts.app')

@section('title', $ui['title'] ?? 'Bar')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
  <style>
    .bar-page-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 20px 0;
    }
    .bar-page-head .nav-tabs {
      border-bottom: none;
      margin-bottom: 0;
    }
    .bar-page-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      padding-bottom: 8px;
    }
    .bar-page-divider {
      border-bottom: 2px solid #940000;
      margin: 0 20px;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-glass"></i> {{ $ui['title'] ?? 'Bar' }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Bar' }}</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile pb-3">
        @php
          $barNavTabs = $ui['nav_tabs'] ?? [
            'stock' => $ui['stock_tab'] ?? 'Stock',
            'purchase-lists' => $ui['purchase_lists_tab'] ?? 'Purchase lists',
          ];
          if ($isPurchaseListRecipient ?? false) {
            $barNavTabs['received'] = $ui['received_tab'] ?? 'Received';
          }
        @endphp
        <div class="bar-page-head">
          <ul class="nav nav-tabs pms-brand-tabs">
            @foreach($barNavTabs as $tabKey => $tabLabel)
              @php($tabBadge = (int) (($barTabBadges ?? [])[$tabKey] ?? 0))
              <li class="nav-item">
                <a class="nav-link {{ ($tab ?? 'stock') === $tabKey ? 'active' : '' }}"
                  href="{{ route('hotel.bar.index', ['tab' => $tabKey]) }}">
                  {{ $tabLabel }}
                  @if($tabBadge > 0)
                    <span class="badge badge-warning ml-1">{{ $tabBadge }}</span>
                  @endif
                </a>
              </li>
            @endforeach
          </ul>

          <div class="bar-page-actions">
            @if(($tab ?? 'stock') === 'received')
              <a class="btn btn-light border btn-sm" href="{{ route('hotel.settings.index', ['tab' => 'bar']) }}">
                <i class="fa fa-cog"></i> Recipient settings
              </a>
            @elseif(($tab ?? 'stock') === 'market')
              <a class="btn btn-light border btn-sm" href="{{ route('hotel.accounts.index', ['tab' => 'bar_budgets']) }}">
                <i class="fa fa-calculator"></i> Accountant queue
              </a>
            @elseif(($tab ?? 'stock') === 'stock-receive')
              <a class="btn btn-light border btn-sm" href="{{ route('hotel.bar.index', ['tab' => 'market']) }}">
                <i class="fa fa-shopping-basket"></i> Market lists
              </a>
            @elseif(($tab ?? 'stock') === 'purchase-lists')
              @if($purchaseListRecipientsConfigured)
                <a class="btn btn-primary btn-sm" href="{{ route('hotel.bar.purchase-lists.create') }}">
                  <i class="fa fa-paper-plane"></i> New list
                </a>
              @else
                <a class="btn btn-primary btn-sm" href="{{ route('hotel.settings.index', ['tab' => 'bar']) }}">
                  <i class="fa fa-cog"></i> Recipients
                </a>
              @endif
            @elseif(($tab ?? 'stock') === 'stock' && ($barStoreIndentsPending ?? 0) > 0)
              <a class="btn btn-light border btn-sm" href="{{ route('hotel.bar.index', ['tab' => 'store-indents']) }}">
                <i class="fa fa-download"></i> Receive from store ({{ $barStoreIndentsPending }})
              </a>
            @elseif(($tab ?? 'stock') === 'stock' && ($stockReceivePendingCount ?? 0) > 0)
              <a class="btn btn-light border btn-sm" href="{{ route('hotel.bar.index', ['tab' => 'stock-receive']) }}">
                <i class="fa fa-truck"></i> Receive stock ({{ $stockReceivePendingCount }})
              </a>
            @elseif(($tab ?? 'stock') === 'items')
              <a class="btn btn-light border btn-sm" href="{{ route('hotel.services.show', $service) }}">
                <i class="fa fa-cog"></i> Menu setup
              </a>
              <a class="btn btn-primary btn-sm" href="{{ route('hotel.bar.items.create') }}">
                <i class="fa fa-plus"></i> Register items
              </a>
            @endif
          </div>
        </div>

        <div class="bar-page-divider"></div>

        <div class="tile-body pt-3">
          @if(($tab ?? 'stock') === 'purchase-lists')
            @include('hotel.erp.bar.partials._tab-purchase-lists')
          @elseif(($tab ?? 'stock') === 'received')
            @include('hotel.erp.bar.partials._tab-received-lists')
          @elseif(($tab ?? 'stock') === 'market')
            @include('hotel.erp.bar.partials._tab-market-lists')
          @elseif(($tab ?? 'stock') === 'stock-receive')
            @include('hotel.erp.bar.partials._tab-stock-receive')
          @elseif(($tab ?? 'stock') === 'store-indents')
            @include('hotel.erp.bar.partials._tab-store-indents')
          @elseif(($tab ?? 'stock') === 'stock')
            @include('hotel.erp.bar.partials._tab-stock')
          @elseif(($tab ?? 'stock') === 'items')
            @include('hotel.erp.bar.partials._tab-items')
          @endif
        </div>
      </div>
    </div>
  </div>

@endsection
