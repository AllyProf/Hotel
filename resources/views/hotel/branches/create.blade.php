@extends('layouts.app')

@section('title', 'Add Branch')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-plus"></i> Add Branch</h1>
      <p>Create a new hotel location</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.branches.index') }}">Branches</a></li>
      <li class="breadcrumb-item"><a href="#">Create</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Branch Details</h3>
        <div class="tile-body">
          <form action="{{ route('hotel.branches.store') }}" method="POST">
            @csrf
            @include('hotel.branches.partials._form-fields')
            @include('hotel.branches.partials._form-scripts')
            <div class="tile-footer">
              <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Create Branch</button>
              <a class="btn btn-secondary" href="{{ route('hotel.branches.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
