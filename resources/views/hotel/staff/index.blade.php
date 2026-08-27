@extends('layouts.app')

@section('title', 'Staff')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-users"></i> Staff</h1>
      <p>Manage team members and their access</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item">Staff</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">All Staff</h3>
          <p>
            <a class="btn btn-primary icon-btn" href="{{ route('hotel.staff.create') }}">
              <i class="fa fa-plus"></i> Add Staff
            </a>
          </p>
        </div>
        <div class="tile-body">
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Branch</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($staff as $member)
                  <tr>
                    <td>{{ $staff->firstItem() + $loop->index }}</td>
                    <td><strong>{{ $member->name }}</strong></td>
                    <td>{{ $member->email }}</td>
                    <td>{{ $member->hotelRole?->name ?? '—' }}</td>
                    <td>{{ $member->branch?->name ?? '—' }}</td>
                    <td>
                      @if($member->is_active)
                        <span class="badge badge-success">Active</span>
                      @else
                        <span class="badge badge-secondary">Inactive</span>
                      @endif
                    </td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="{{ route('hotel.staff.edit', $member) }}">
                        <i class="fa fa-edit"></i> Edit
                      </a>
                      <form action="{{ route('hotel.staff.destroy', $member) }}" method="POST" class="d-inline js-swal-delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" data-title="Remove staff?" data-text="This will remove {{ $member->name }} from your team.">
                          <i class="fa fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                      No staff yet. <a href="{{ route('hotel.staff.create') }}">Add your first team member</a>.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          {{ $staff->links() }}
        </div>
      </div>
    </div>
  </div>
@endsection
