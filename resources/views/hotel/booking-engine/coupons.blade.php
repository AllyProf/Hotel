@extends('layouts.app')

@section('title', 'BE Coupons — Booking Engine')

@push('styles')
  @include('hotel.booking-engine.partials._layout-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-tag"></i> BE Coupons</h1>
      <p>Promo codes for your direct booking engine</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">BE Coupons</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @include('hotel.booking-engine.partials._subnav')

        <h3 class="tile-title">Add coupon</h3>
        <div class="tile-body pb-0">
          <form method="POST" action="{{ route('hotel.booking-engine.coupons.store') }}" class="mb-4">
            @csrf
            <div class="row align-items-end">
              <div class="col-md-2">
                <div class="form-group mb-md-0">
                  <label class="control-label">Code</label>
                  <input type="text" name="code" class="form-control" value="{{ old('code') }}" required maxlength="40" style="text-transform:uppercase;">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group mb-md-0">
                  <label class="control-label">Type</label>
                  <select name="discount_type" class="form-control">
                    <option value="percent" {{ old('discount_type', 'percent') === 'percent' ? 'selected' : '' }}>Percent</option>
                    <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed amount</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group mb-md-0">
                  <label class="control-label">Value</label>
                  <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="{{ old('discount_value', '10') }}" required>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group mb-md-0">
                  <label class="control-label">Valid from</label>
                  <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from') }}">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group mb-md-0">
                  <label class="control-label">Valid to</label>
                  <input type="date" name="valid_to" class="form-control" value="{{ old('valid_to') }}">
                </div>
              </div>
              <div class="col-md-1">
                <div class="form-group mb-md-0">
                  <label class="control-label">Min stay</label>
                  <input type="number" min="0" name="min_stay" class="form-control" value="{{ old('min_stay', 0) }}">
                </div>
              </div>
              <div class="col-md-1">
                <label class="animated-checkbox d-block mb-2">
                  <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}>
                  <span class="label-text">Active</span>
                </label>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Add</button>
              </div>
            </div>
          </form>
        </div>

        <h3 class="tile-title mt-3">Coupons</h3>
        <div class="tile-body">
          @forelse($coupons as $coupon)
            <div class="border rounded p-3 mb-3">
              <form method="POST" action="{{ route('hotel.booking-engine.coupons.update', $coupon['id']) }}" class="mb-0">
                @csrf
                @method('PUT')
                <div class="row align-items-end">
                  <div class="col-md-2">
                    <label class="control-label small text-muted">Code</label>
                    <input type="text" name="code" class="form-control form-control-sm" value="{{ $coupon['code'] ?? '' }}" required>
                  </div>
                  <div class="col-md-2">
                    <label class="control-label small text-muted">Type</label>
                    <select name="discount_type" class="form-control form-control-sm">
                      <option value="percent" {{ ($coupon['discount_type'] ?? '') === 'percent' ? 'selected' : '' }}>Percent</option>
                      <option value="fixed" {{ ($coupon['discount_type'] ?? '') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                    </select>
                  </div>
                  <div class="col-md-1">
                    <label class="control-label small text-muted">Value</label>
                    <input type="number" step="0.01" min="0" name="discount_value" class="form-control form-control-sm" value="{{ $coupon['discount_value'] ?? 0 }}">
                  </div>
                  <div class="col-md-2">
                    <label class="control-label small text-muted">From</label>
                    <input type="date" name="valid_from" class="form-control form-control-sm" value="{{ $coupon['valid_from'] ?? '' }}">
                  </div>
                  <div class="col-md-2">
                    <label class="control-label small text-muted">To</label>
                    <input type="date" name="valid_to" class="form-control form-control-sm" value="{{ $coupon['valid_to'] ?? '' }}">
                  </div>
                  <div class="col-md-1">
                    <label class="control-label small text-muted">Min stay</label>
                    <input type="number" min="0" name="min_stay" class="form-control form-control-sm" value="{{ $coupon['min_stay'] ?? 0 }}">
                  </div>
                  <div class="col-md-2 text-right">
                    <label class="animated-checkbox d-inline-block mr-2 mb-2">
                      <input type="checkbox" name="active" value="1" {{ ($coupon['active'] ?? true) ? 'checked' : '' }}>
                      <span class="label-text">Active</span>
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                  </div>
                </div>
              </form>
              <form method="POST" action="{{ route('hotel.booking-engine.coupons.destroy', $coupon['id']) }}" class="mt-2 text-right" onsubmit="return confirm('Remove this coupon?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </div>
          @empty
            <p class="text-muted mb-0">No coupons yet. Add one above.</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
