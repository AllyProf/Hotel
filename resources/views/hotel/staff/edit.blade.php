@extends('layouts.app')

@section('title', 'Edit Staff')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-edit"></i> Edit Staff</h1>
      <p>{{ $staff->name }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.staff.index') }}">Staff</a></li>
      <li class="breadcrumb-item">Edit</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Staff Details</h3>
        <div class="tile-body">
          <form action="{{ route('hotel.staff.update', $staff) }}" method="POST">
            @csrf
            @method('PUT')
            @include('hotel.staff.partials._form-fields', ['staff' => $staff, 'isEdit' => true])
            <div class="tile-footer">
              <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Staff</button>
              <a class="btn btn-secondary" href="{{ route('hotel.staff.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
