@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-dashboard"></i> Dashboard</h1>
      <p>Platform overview — manage hotel accounts from here.</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-6 col-lg-3">
      <div class="widget-small primary coloured-icon">
        <i class="icon fa fa-building fa-3x"></i>
        <div class="info">
          <h4>Total Hotels</h4>
          <p><b>{{ $hotelsCount }}</b></p>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="widget-small info coloured-icon">
        <i class="icon fa fa-check-circle fa-3x"></i>
        <div class="info">
          <h4>Active Hotels</h4>
          <p><b>{{ $activeHotelsCount }}</b></p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">Quick Actions</h3>
          <p>
            <a class="btn btn-primary icon-btn" href="{{ route('admin.hotels.create') }}">
              <i class="fa fa-plus"></i> Create Hotel Account
            </a>
          </p>
        </div>
        <div class="tile-body">
          <p>As the platform owner, you can register new hotels on the system. Each hotel gets its own admin login to manage rooms, reservations, and operations.</p>
          <a class="btn btn-primary" href="{{ route('admin.hotels.index') }}">
            <i class="fa fa-building mr-1"></i> View All Hotels
          </a>
        </div>
      </div>
    </div>
  </div>
@endsection
