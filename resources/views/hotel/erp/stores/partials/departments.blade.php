@inject('storeDeptService', 'App\Services\Stores\StoreDepartmentService')

<div class="stores-toolbar">
  <form method="GET" action="{{ route('hotel.stores.index') }}" class="stores-toolbar-form" data-no-loader>
    <input type="hidden" name="tab" value="settings">
    <input type="hidden" name="settings" value="department">

    <div class="stores-toolbar-row">
      <div class="stores-toolbar-actions">
        <button type="button" class="btn btn-stores-outline btn-stores-add-item js-stores-department-add" data-toggle="modal" data-target="#storesDepartmentModal">
          {{ $ui['add_department'] ?? 'Add Department' }}
        </button>
        <div class="stores-search-field">
          <label class="control-label stores-search-label" for="storesDepartmentSearch">{{ $ui['department_search_placeholder'] ?? 'Search Department…' }}</label>
          <div class="input-group stores-search-input">
            <input type="search" name="department_search" id="storesDepartmentSearch" class="form-control"
              placeholder="{{ $ui['department_search_placeholder'] ?? 'Search Department…' }}" value="{{ $departmentSearch }}">
            <div class="input-group-append">
              <button type="submit" class="btn btn-light border" aria-label="Search"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        <th>Name</th>
        <th>Contact Person</th>
        <th class="text-right">Amount Issued</th>
        <th style="width:72px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($departments as $department)
        <tr>
          <td>{{ $department->name }}</td>
          <td>{{ $department->contact_person ?: '—' }}</td>
          <td class="text-right">{{ $storeDeptService->formatMoney((float) $department->amount_issued, $hotel) }}</td>
          <td class="text-nowrap">
            <button type="button" class="stores-icon-btn text-primary js-stores-edit-department" title="Edit"
              data-id="{{ $department->id }}"
              data-name="{{ $department->name }}"
              data-contact-person="{{ $department->contact_person }}"
              data-amount-issued="{{ (float) $department->amount_issued }}">
              <i class="fa fa-pencil"></i>
            </button>
            <form method="POST" action="{{ route('hotel.stores.departments.destroy', $department) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="stores-icon-btn text-danger" title="Delete"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="text-muted text-center py-4">
            No departments yet. Use <strong>Add Department</strong> to create one.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('hotel.erp.stores.partials.department-modal')

@push('scripts')
<script>
jQuery(function ($) {
  var storeUrl = @json(route('hotel.stores.departments.store'));
  var updateUrlTemplate = @json(url('/hotel/stores/departments/__ID__'));
  var addTitle = @json($ui['add_department'] ?? 'Add Department');
  var editTitle = @json($ui['edit_department'] ?? 'Edit Department');
  var deptModalMode = 'add';

  function setDepartmentFormAddMode() {
    $('#storesDepartmentForm').attr('action', storeUrl);
    $('#storesDepartmentMethod').val('');
    $('#storesDepartmentEditId').val('');
    $('#storesDepartmentModalTitle').text(addTitle);
  }

  function fillDepartmentForm(data) {
    $('#storesDepartmentForm').attr('action', updateUrlTemplate.replace('__ID__', data.id));
    $('#storesDepartmentMethod').val('PUT');
    $('#storesDepartmentEditId').val(String(data.id));
    $('#storesDepartmentModalTitle').text(editTitle);
    $('#storesDepartmentName').val(data.name || '');
    $('#storesDepartmentContact').val(data.contactPerson || '');
    $('#storesDepartmentAmount').val(data.amountIssued != null ? data.amountIssued : '0');
  }

  function resetDepartmentFormFields() {
    var form = document.getElementById('storesDepartmentForm');
    if (form) {
      form.reset();
    }
    $('#storesDepartmentAmount').val('0');
  }

  $('#storesDepartmentModal').on('show.bs.modal', function () {
    if (deptModalMode === 'edit') {
      return;
    }
    setDepartmentFormAddMode();
    resetDepartmentFormFields();
  });

  $('#storesDepartmentModal').on('hidden.bs.modal', function () {
    deptModalMode = 'add';
  });

  $('.js-stores-department-add').on('click', function () {
    deptModalMode = 'add';
  });

  $(document).on('click', '.js-stores-edit-department', function (e) {
    e.preventDefault();
    var $btn = $(this).closest('.js-stores-edit-department');
    if (!$btn.data('id')) {
      return;
    }
    deptModalMode = 'edit';
    fillDepartmentForm({
      id: $btn.data('id'),
      name: $btn.data('name') || '',
      contactPerson: $btn.data('contact-person') || '',
      amountIssued: $btn.data('amount-issued'),
    });
    $('#storesDepartmentModal').modal('show');
  });

  @if($errors->any() && ($settingsTab ?? '') === 'department')
    deptModalMode = @json(old('stores_department_edit_id') ? 'edit' : 'add');
    $('#storesDepartmentModal').modal('show');
    @if(old('stores_department_edit_id'))
      fillDepartmentForm({
        id: @json(old('stores_department_edit_id')),
        name: @json(old('name')),
        contactPerson: @json(old('contact_person')),
        amountIssued: @json(old('amount_issued', 0)),
      });
    @else
      setDepartmentFormAddMode();
    @endif
  @endif
});
</script>
@endpush
