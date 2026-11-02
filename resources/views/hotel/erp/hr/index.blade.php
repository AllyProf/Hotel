@extends('layouts.app')

@section('title', $ui['title'] ?? 'HR')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
  <style>
    :root { --hr-brand: #940000; --hr-brand-dark: #7a0000; }
    .hr-page { background: #fff; }
    .hr-title { margin: 0; padding: 18px 20px 0; font-size: 22px; font-weight: 400; color: #333; }
    .hr-stat-row { display: flex; flex-wrap: wrap; gap: 12px; padding: 16px 20px 0; }
    .hr-stat {
      flex: 1; min-width: 120px; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px 14px;
      border-top: 3px solid var(--hr-brand);
    }
    .hr-stat__label { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; }
    .hr-stat__value { font-size: 24px; font-weight: 700; color: #111; }
    .hr-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 10px; padding: 16px 20px; }
    .hr-content { padding: 0 20px 24px; min-height: 200px; }
    .btn-hr { background: var(--hr-brand) !important; border-color: var(--hr-brand) !important; color: #fff !important; font-size: 13px; font-weight: 600; }
    .btn-hr:hover { background: var(--hr-brand-dark) !important; border-color: var(--hr-brand-dark) !important; color: #fff !important; }
    .btn-hr-outline { background: #fff !important; border: 1px solid #ccc !important; color: #333 !important; font-size: 13px; }
    .hr-table thead th { background: #343a40; color: #fff; font-size: 13px; padding: 10px 12px; white-space: nowrap; }
    .hr-table tbody td { font-size: 13px; padding: 10px 12px; vertical-align: middle; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-id-badge"></i> ERP <small class="text-muted">{{ $ui['title'] ?? 'HR' }}</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'HR' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile hr-page">
        <h3 class="hr-title">{{ $ui['title'] ?? 'HR' }}</h3>

        <div class="hr-stat-row">
          <div class="hr-stat">
            <div class="hr-stat__label">Active staff</div>
            <div class="hr-stat__value">{{ $stats['active'] ?? 0 }}</div>
          </div>
          <div class="hr-stat">
            <div class="hr-stat__label">Inactive</div>
            <div class="hr-stat__value">{{ $stats['inactive'] ?? 0 }}</div>
          </div>
          <div class="hr-stat">
            <div class="hr-stat__label">Departments</div>
            <div class="hr-stat__value">{{ $stats['departments'] ?? 0 }}</div>
          </div>
        </div>

        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
          <div class="alert alert-warning mx-3 mt-3 mb-0">{{ session('warning') }}</div>
        @endif

        <ul class="nav nav-tabs pms-brand-tabs px-3 pt-3 mb-0">
          @foreach($ui['tabs'] ?? [] as $key => $label)
            <li class="nav-item">
              <a class="nav-link {{ $filters['tab'] === $key ? 'active' : '' }}"
                href="{{ route('hotel.hr.index', array_merge(request()->except('page'), ['tab' => $key])) }}">{{ $label }}</a>
            </li>
          @endforeach
        </ul>

        @if($filters['tab'] === 'team')
          @include('hotel.erp.hr.partials.team')
        @elseif($filters['tab'] === 'departments')
          @include('hotel.erp.hr.partials.departments')
        @elseif($filters['tab'] === 'roles')
          @include('hotel.erp.hr.partials.roles')
        @else
          @include('hotel.erp.hr.partials.attendance')
        @endif
      </div>
    </div>
  </div>
@endsection
