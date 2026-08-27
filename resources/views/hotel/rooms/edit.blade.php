@extends('layouts.app')

@section('title', 'Edit Room')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-edit"></i> Edit Room</h1>
      <p>{{ $room->name }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.rooms.index') }}">Rooms</a></li>
      <li class="breadcrumb-item"><a href="#">{{ $room->name }}</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Room Details</h3>
        <div class="tile-body">
          <form action="{{ route('hotel.rooms.update', $room) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('hotel.rooms.partials._form-fields', ['room' => $room])
            <div class="tile-footer">
              <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Changes</button>
              <a class="btn btn-secondary" href="{{ route('hotel.rooms.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
