@extends('layouts.app')

@section('title', 'Edit Plan')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-edit"></i> Edit Subscription Plan</h1>
      <p>Update pricing, limits, and enabled modules</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="{{ route('admin.plans.index') }}">Plans</a></li>
      <li class="breadcrumb-item"><a href="#">{{ $plan->name }}</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title">Plan Details</h3>
        <div class="tile-body">
          <form action="{{ route('admin.plans.update', $plan) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.plans.partials._form-fields', [
              'plan' => $plan,
              'featureOptions' => $featureOptions,
              'selectedFeatures' => $plan->enabledFeatureKeys(),
            ])
            <div class="tile-footer">
              <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Changes</button>
              <a class="btn btn-secondary" href="{{ route('admin.plans.index') }}">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
