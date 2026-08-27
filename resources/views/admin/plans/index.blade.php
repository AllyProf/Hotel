@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-credit-card"></i> Subscription Plans</h1>
      <p>Manage plans and enabled hotel modules</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="#">Plans</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">All Plans</h3>
          <p>
            <a class="btn btn-primary icon-btn" href="{{ route('admin.plans.create') }}">
              <i class="fa fa-plus"></i> Create Plan
            </a>
          </p>
        </div>
        <div class="tile-body">
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead>
                <tr>
                  <th>Plan</th>
                  <th>Price</th>
                  <th>Limits</th>
                  <th>Features</th>
                  <th>Hotels</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($plans as $plan)
                  <tr>
                    <td>
                      <strong>{{ $plan->name }}</strong>
                      @if($plan->description)
                        <br><small class="text-muted">{{ $plan->description }}</small>
                      @endif
                    </td>
                    <td>{{ $plan->billingLabel() }}</td>
                    <td>{{ $plan->roomsLimitLabel() }}<br>{{ $plan->usersLimitLabel() }}<br>{{ $plan->branchesLimitLabel() }}</td>
                    <td>
                      @if($plan->enabledFeatureLabels())
                        <small>{{ count($plan->enabledFeatureLabels()) }} modules enabled</small>
                        <ul class="mb-0 pl-3">
                          @foreach(array_slice($plan->enabledFeatureLabels(), 0, 3) as $feature)
                            <li><small>{{ $feature }}</small></li>
                          @endforeach
                          @if(count($plan->enabledFeatureLabels()) > 3)
                            <li><small class="text-muted">+ {{ count($plan->enabledFeatureLabels()) - 3 }} more</small></li>
                          @endif
                        </ul>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>{{ $plan->hotels_count }}</td>
                    <td>
                      @if($plan->is_active)
                        <span class="badge badge-success">Active</span>
                      @else
                        <span class="badge badge-secondary">Inactive</span>
                      @endif
                    </td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="{{ route('admin.plans.edit', $plan) }}">
                        <i class="fa fa-edit"></i> Edit
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                      No plans yet. <a href="{{ route('admin.plans.create') }}">Create the first plan</a>.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
