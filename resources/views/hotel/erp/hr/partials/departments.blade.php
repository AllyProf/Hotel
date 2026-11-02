@php
  $departments = $payload['departments'] ?? collect();
  $posCatalogServices = $payload['posCatalogServices'] ?? collect();
@endphp

<div class="hr-toolbar">
  <button type="button" class="btn btn-hr-outline" data-toggle="modal" data-target="#hrAddDepartmentModal">Add department</button>
</div>

<div class="hr-content">
  <div class="table-responsive">
    <table class="table table-bordered table-hover hr-table mb-0">
      <thead>
        <tr>
          <th>Department</th>
          <th>Staff</th>
          <th>POS</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($departments as $department)
          <tr>
            <td><strong>{{ $department->name }}</strong></td>
            <td>{{ $department->staff_count }}</td>
            <td>
              @if($department->show_in_pos && $department->hotel_pms_service_id)
                <span class="badge badge-info">POS</span>
                <span class="text-muted small d-block">{{ $posCatalogServices->firstWhere('id', $department->hotel_pms_service_id)?->name ?? '—' }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              @if($department->is_active)
                <span class="badge badge-success">Active</span>
              @else
                <span class="badge badge-secondary">Inactive</span>
              @endif
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#hrEditDepartment{{ $department->id }}">Edit</button>
              @if($department->staff_count === 0)
                <form method="POST" action="{{ route('hotel.hr.departments.destroy', $department) }}" class="d-inline js-swal-delete">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-danger" data-title="Delete department?" data-text="Remove {{ $department->name }}?">Delete</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">No departments.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="hrAddDepartmentModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="POST" action="{{ route('hotel.hr.departments.store') }}" class="modal-content">
      @csrf
      <div class="modal-header" style="background:#940000;color:#fff;">
        <h5 class="modal-title">Add department</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group mb-0">
          <label>Name</label>
          <input type="text" name="name" class="form-control" required maxlength="120">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-hr">Save</button>
      </div>
    </form>
  </div>
</div>

@foreach($departments as $department)
  <div class="modal fade" id="hrEditDepartment{{ $department->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <form method="POST" action="{{ route('hotel.hr.departments.update', $department) }}" class="modal-content">
        @csrf
        @method('PUT')
        <div class="modal-header" style="background:#940000;color:#fff;">
          <h5 class="modal-title">Edit department</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="{{ $department->name }}" required maxlength="120">
          </div>
          <label class="mb-0 d-block">
            <input type="checkbox" name="is_active" value="1" {{ $department->is_active ? 'checked' : '' }}> Active
          </label>
          @if($posCatalogServices->isNotEmpty())
            <hr class="my-3">
            <p class="text-muted small mb-2">When enabled, this department appears on <strong>ERP → POS</strong> (Department list). Link the PMS service whose item catalog staff will sell.</p>
            <label class="mb-2 d-block">
              <input type="checkbox" name="show_in_pos" value="1" class="js-hr-dept-pos-toggle"
                {{ $department->show_in_pos ? 'checked' : '' }}> Show in POS
            </label>
            <div class="form-group mb-0 js-hr-dept-pos-service-wrap" style="{{ $department->show_in_pos ? '' : 'display:none;' }}">
              <label>PMS catalog service</label>
              <select name="hotel_pms_service_id" class="form-control js-hr-dept-pos-service">
                <option value="">— Select service —</option>
                @foreach($posCatalogServices as $svc)
                  <option value="{{ $svc->id }}" @selected((int) $department->hotel_pms_service_id === (int) $svc->id)>{{ $svc->name }}</option>
                @endforeach
              </select>
            </div>
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hr">Update</button>
        </div>
      </form>
    </div>
  </div>
@endforeach

@if($posCatalogServices->isNotEmpty())
  @push('scripts')
    <script>
      (function () {
        document.querySelectorAll('.js-hr-dept-pos-toggle').forEach(function (cb) {
          cb.addEventListener('change', function () {
            var wrap = cb.closest('.modal-body').querySelector('.js-hr-dept-pos-service-wrap');
            if (wrap) wrap.style.display = cb.checked ? '' : 'none';
          });
        });
      })();
    </script>
  @endpush
@endif
