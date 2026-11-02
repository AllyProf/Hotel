@extends('layouts.app')

@section('title', 'Edit item · '.$item->item_name)

@push('styles')
  @include('hotel.erp.bar.partials._item-form-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-pencil"></i> Edit item</h1>
      <p>{{ $item->item_name }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.bar.index', ['tab' => 'items']) }}">Bar</a></li>
      <li class="breadcrumb-item">Edit</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">Please fix the errors below and try again.</div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="tile-title">Item Details</h3>
          <a href="{{ route('hotel.bar.index', ['tab' => 'items']) }}" class="btn btn-light border btn-sm">
            <i class="fa fa-arrow-left"></i> Back to items
          </a>
        </div>
        <div class="tile-body">
          <form method="POST" action="{{ route('hotel.bar.items.update', $item) }}">
            @csrf
            @method('PUT')
            @include('hotel.erp.bar.partials._item-form-fields', [
              'item' => $item,
              'formScope' => 'edit-'.$item->id,
            ])

            <div class="bar-item-form-actions mt-4 pt-3 border-top">
              <button type="submit" class="btn btn-primary btn-register-item">
                <i class="fa fa-check"></i> Save changes
              </button>
              <a href="{{ route('hotel.bar.index', ['tab' => 'items']) }}" class="btn btn-secondary ml-2">
                <i class="fa fa-times"></i> Cancel
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @if($nameSuggestions->isNotEmpty())
    <datalist id="barItemNameSuggestions">
      @foreach($nameSuggestions as $name)
        <option value="{{ $name }}"></option>
      @endforeach
    </datalist>
  @endif
@endsection

@push('scripts')
  @include('hotel.erp.bar.partials._item-form-scripts')
@endpush
