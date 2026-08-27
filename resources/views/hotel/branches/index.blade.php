@extends('layouts.app')

@section('title', 'Branches')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-sitemap"></i> Branches</h1>
      <p>Manage locations for {{ $hotel->name }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="#">Branches</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">All Branches</h3>
          <p>
            @if($canAddBranch)
              <a class="btn btn-primary icon-btn" href="{{ route('hotel.branches.create') }}">
                <i class="fa fa-plus"></i> Add Branch
              </a>
            @else
              <span class="text-muted small">
                Branch limit reached ({{ $hotel->branches()->count() }} / {{ $hotel->maxBranches() ?: '∞' }})
              </span>
            @endif
          </p>
        </div>
        <div class="tile-body">
          @if($hotel->maxBranches() > 0)
            <p class="text-muted small mb-3">
              Plan limit: {{ $hotel->branches()->count() }} of {{ $hotel->maxBranches() }} branches used.
            </p>
          @endif
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Branch</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>City</th>
                  <th>Status</th>
                  <th>HQ</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($branches as $branch)
                  <tr>
                    <td>{{ $branches->firstItem() + $loop->index }}</td>
                    <td>
                      <strong>{{ $branch->name }}</strong>
                      @if($branch->address)
                        <br><small class="text-muted">{{ $branch->address }}</small>
                      @endif
                    </td>
                    <td>{{ $branch->email ?? '—' }}</td>
                    <td>{{ $branch->phone ?? '—' }}</td>
                    <td>{{ $branch->city ?? '—' }}</td>
                    <td>
                      @if($branch->isActive())
                        <span class="badge badge-success">Active</span>
                      @else
                        <span class="badge badge-secondary">Inactive</span>
                      @endif
                    </td>
                    <td>
                      @if($branch->is_headquarters)
                        <span class="badge badge-primary">HQ</span>
                      @else
                        —
                      @endif
                    </td>
                    <td>
                      <a class="btn btn-sm btn-primary" href="{{ route('hotel.branches.edit', $branch) }}">
                        <i class="fa fa-edit"></i> Edit
                      </a>
                      <form action="{{ route('hotel.branches.destroy', $branch) }}" method="POST" class="d-inline js-swal-delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" data-title="Delete branch?" data-text="This will permanently remove {{ $branch->name }}.">
                          <i class="fa fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                      No branches yet. <a href="{{ route('hotel.branches.create') }}">Add your first branch</a>.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          {{ $branches->links() }}
        </div>
      </div>
    </div>
  </div>
@endsection
