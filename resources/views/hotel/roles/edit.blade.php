@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-edit"></i> Edit Role</h1>
      <p>{{ $role->name }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.roles.index') }}">Roles</a></li>
      <li class="breadcrumb-item">Edit</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Role Details</h3>
        <div class="tile-body">
          <form action="{{ route('hotel.roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')
            @include('hotel.roles.partials._form-fields')
            <div class="tile-footer">
              <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Role</button>
              <a class="btn btn-secondary" href="{{ route('hotel.roles.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
