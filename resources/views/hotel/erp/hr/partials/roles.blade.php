@php $roles = $payload['roles'] ?? collect(); @endphp

<div class="hr-toolbar">
  <a href="{{ route('hotel.roles.create') }}" class="btn btn-hr-outline">Create role</a>
  <a href="{{ route('hotel.roles.index') }}" class="btn btn-hr-outline">Full roles manager</a>
</div>

<div class="hr-content">
  <div class="table-responsive">
    <table class="table table-bordered table-hover hr-table mb-0">
      <thead>
        <tr>
          <th>Role</th>
          <th>Staff</th>
          <th>Permissions</th>
          <th></th>
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
            <td>{{ count($role->permissionList()) }}</td>
            <td><a href="{{ route('hotel.roles.edit', $role) }}" class="btn btn-sm btn-primary">Edit</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="text-center text-muted py-4">No roles.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
