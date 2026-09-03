@extends('layouts.app')

@section('title', $ui['title'] ?? 'Accounts')

@push('styles')
  <style>
    :root { --ac-brand: #940000; --ac-brand-dark: #7a0000; }
    .ac-page { background: #fff; }
    .ac-title {
      margin: 0;
      padding: 18px 20px 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }
    .ac-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 0;
      padding: 0 20px;
      border-bottom: 1px solid #e5e7eb;
    }
    .ac-tab {
      padding: 12px 18px;
      font-size: 14px;
      color: #666;
      text-decoration: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -1px;
    }
    .ac-tab.is-active {
      color: #333;
      font-weight: 600;
      border-bottom-color: var(--ac-brand);
      background: #f8f9fa;
    }
    .ac-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
      padding: 16px 20px;
    }
    .ac-search input {
      min-width: 200px;
      height: 36px;
      font-size: 13px;
    }
    .btn-ac {
      background: var(--ac-brand) !important;
      border-color: var(--ac-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      min-height: 36px;
    }
    .btn-ac:hover { background: var(--ac-brand-dark) !important; border-color: var(--ac-brand-dark) !important; color: #fff !important; }
    .btn-ac-outline {
      background: #fff !important;
      border: 1px solid #ccc !important;
      color: #333 !important;
      font-size: 13px;
      min-height: 36px;
    }
    .ac-content { padding: 0 20px 24px; min-height: 200px; }
    .ac-empty { font-size: 15px; font-weight: 600; color: #333; padding: 12px 0; }
    .ac-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      padding: 10px 12px;
      border-color: #2d3238;
      white-space: nowrap;
    }
    .ac-table tbody td {
      font-size: 13px;
      padding: 10px 12px;
      border-color: #dee2e6;
      vertical-align: middle;
    }
    .ac-table tfoot td {
      background: #6c757d;
      color: #fff;
      font-weight: 700;
      padding: 10px 12px;
    }
    .ac-filters {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      padding: 16px 20px 0;
    }
    .ac-filters label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .ac-filters .form-control { min-height: 36px; font-size: 13px; }
    .ac-modal-header {
      background: var(--ac-brand);
      color: #fff;
      border-bottom: none;
    }
    .ac-modal-divider {
      height: 3px;
      background: var(--ac-brand);
      margin: 0 0 18px;
    }
  </style>
@endpush

@section('content')
  @inject('companyData', 'App\Services\CompanyDataService')

  <div class="app-title">
    <div>
      <h1><i class="fa fa-calculator"></i> ERP <small class="text-muted">{{ $ui['title'] ?? 'Accounts' }}</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Accounts' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile ac-page">
        <h3 class="ac-title">{{ $ui['title'] ?? 'Accounts' }}</h3>

        <nav class="ac-tabs">
          @foreach($ui['tabs'] ?? [] as $key => $label)
            <a href="{{ route('hotel.accounts.index', array_merge(request()->except('page'), ['tab' => $key])) }}"
              class="ac-tab {{ $filters['tab'] === $key ? 'is-active' : '' }}">{{ $label }}</a>
          @endforeach
        </nav>

        @if($filters['tab'] === 'receivables')
          @include('hotel.erp.accounts.partials.receivables')
        @elseif($filters['tab'] === 'payables')
          @include('hotel.erp.accounts.partials.payables')
        @elseif($filters['tab'] === 'taxes')
          @include('hotel.erp.accounts.partials.taxes')
        @else
          @include('hotel.erp.accounts.partials.reconciliation')
        @endif
      </div>
    </div>
  </div>

  @include('hotel.erp.accounts.partials.add-company-modal')
  @include('hotel.erp.accounts.partials.add-vendor-modal')
@endsection
