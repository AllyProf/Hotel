@php $staff = $payload['staff'] ?? null; @endphp

<div class="hr-toolbar">
  <a href="{{ route('hotel.staff.create') }}" class="btn btn-hr"><i class="fa fa-plus"></i> Add staff</a>
  <form method="GET" action="{{ route('hotel.hr.index') }}" class="d-flex">
    <input type="hidden" name="tab" value="team">
    <input class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search team…">
  </form>
</div>

<div class="hr-content">
  @if($staff && $staff->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-bordered table-hover hr-table mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Department</th>
            <th>Role</th>
            <th>Branch</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($staff as $member)
            <tr>
              <td><strong>{{ $member->name }}</strong></td>
              <td>{{ $member->email }}</td>
              <td>{{ $member->department?->name ?? '—' }}</td>
              <td>{{ $member->hotelRole?->name ?? '—' }}</td>
              <td>{{ $member->branch?->name ?? '—' }}</td>
              <td>
                @if($member->is_active)
                  <span class="badge badge-success">Active</span>
                @else
                  <span class="badge badge-secondary">Inactive</span>
                @endif
              </td>
              <td class="text-nowrap">
                <a href="{{ route('hotel.staff.edit', $member) }}" class="btn btn-sm btn-primary">Edit</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($staff->hasPages())
      <div class="mt-3">{{ $staff->links() }}</div>
    @endif
  @else
    <p class="text-muted py-4 mb-0">No staff yet. <a href="{{ route('hotel.staff.create') }}">Add your first team member</a>.</p>
  @endif
</div>
