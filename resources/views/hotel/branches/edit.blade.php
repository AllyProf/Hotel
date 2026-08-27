@extends('layouts.app')

@section('title', 'Edit Branch')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-edit"></i> Edit Branch</h1>
      <p>Update branch details</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.branches.index') }}">Branches</a></li>
      <li class="breadcrumb-item"><a href="#">{{ $branch->name }}</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Branch Details</h3>
        <div class="tile-body">
          <form action="{{ route('hotel.branches.update', $branch) }}" method="POST">
            @csrf
            @method('PUT')
            @include('hotel.branches.partials._form-fields', ['branch' => $branch])
            @include('hotel.branches.partials._form-scripts', ['branch' => $branch])
            <div class="tile-footer">
              <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Changes</button>
              <a class="btn btn-secondary" href="{{ route('hotel.branches.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
