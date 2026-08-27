@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-shield"></i> Roles & Permissions</h1>
      <p>Control what each staff role can access</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item">Roles</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">All Roles</h3>
          <p>
            <a class="btn btn-primary icon-btn" href="{{ route('hotel.roles.create') }}">
              <i class="fa fa-plus"></i> Create Role
            </a>
          </p>
        </div>
        <div class="tile-body">
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead>
                <tr>
                  <th>Role</th>
                  <th>Staff assigned</th>
                  <th>Permissions</th>
                  <th>Type</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($roles as $role)
                  <tr>
                    <td>
                      <strong>{{ $role->name }}</strong>
                      @if($role->description)
                        <br><small class="text-muted">{{ $role->description }}</small>
                      @endif
                    </td>
                    <td>{{ $role->users_count }}</td>
                    <td>{{ count($role->permissionList()) }} permissions</td>
                    <td>
                      @if($role->is_system)
                        <span class="badge badge-info">System</span>
                      @else
                        <span class="badge badge-secondary">Custom</span>
                      @endif
                    </td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="{{ route('hotel.roles.edit', $role) }}">
                        <i class="fa fa-edit"></i> Edit
                      </a>
                      @if(! $role->is_system)
                        <form action="{{ route('hotel.roles.destroy', $role) }}" method="POST" class="d-inline js-swal-delete">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-sm btn-danger" data-title="Delete role?" data-text="This will permanently remove {{ $role->name }}.">
                            <i class="fa fa-trash"></i>
                          </button>
                        </form>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No roles yet.</td>
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
