@extends('layouts.app')

@section('title', 'All Hotels')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-building"></i> All Hotels</h1>
      <p>Hotel accounts registered on the platform</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="#">Hotels</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title">Hotels</h3>
          <p>
            <a class="btn btn-primary icon-btn" href="{{ route('admin.hotels.create') }}">
              <i class="fa fa-plus"></i> Create Hotel
            </a>
          </p>
        </div>
        <div class="tile-body">
          <div class="table-responsive">
            <table class="table table-hover table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Hotel Name</th>
                  <th>Plan</th>
                  <th>TIN</th>
                  <th>Contact Email</th>
                  <th>Phone</th>
                  <th>City</th>
                  <th>Branches</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($hotels as $hotel)
                  <tr>
                    <td>{{ $hotels->firstItem() + $loop->index }}</td>
                    <td>{{ $hotel->name }}</td>
                    <td>{{ $hotel->plan?->name ?? '—' }}</td>
                    <td>{{ $hotel->tin ?? '—' }}</td>
                    <td>{{ $hotel->email }}</td>
                    <td>{{ $hotel->phone ?? '—' }}</td>
                    <td>{{ $hotel->city ?? '—' }}</td>
                    <td>
                      @if($hotel->supportsMultiBranch())
                        {{ $hotel->branches_count }}
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>
                      @if($hotel->isActive())
                        <span class="badge badge-success">Active</span>
                      @else
                        <span class="badge badge-secondary">{{ ucfirst($hotel->status) }}</span>
                      @endif
                    </td>
                    <td>{{ $hotel->created_at->format('M d, Y') }}</td>
                    <td>
                      @if($hotel->adminUser)
                        <form action="{{ route('admin.hotels.impersonate', $hotel) }}" method="POST" class="d-inline js-swal-confirm">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-primary" title="Login as hotel admin"
                            data-title="Impersonate hotel?"
                            data-text="You will be logged in as the admin for {{ $hotel->name }}."
                            data-confirm="Yes, impersonate"
                            data-cancel="Cancel">
                            <i class="fa fa-user-secret"></i> Impersonate
                          </button>
                        </form>
                      @else
                        <span class="text-muted small">No admin</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="11" class="text-center text-muted py-4">
                      No hotels yet.
                      <a href="{{ route('admin.hotels.create') }}">Create the first hotel account</a>.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          {{ $hotels->links() }}
        </div>
      </div>
    </div>
  </div>
@endsection
