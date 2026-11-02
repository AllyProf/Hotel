<div class="stores-toolbar">
  <form method="GET" action="{{ route('hotel.stores.index') }}" class="stores-toolbar-form" data-no-loader>
    <input type="hidden" name="tab" value="settings">
    <input type="hidden" name="settings" value="item-category">

    <div class="stores-toolbar-row">
      <div class="stores-toolbar-actions">
        <button type="button" class="btn btn-stores-outline btn-stores-add-item js-stores-store-dept-add"
          data-toggle="modal" data-target="#storesStoreDepartmentModal"
          @if(($pmsServicesForStoreDepartments ?? collect())->isEmpty()) disabled title="Add services under PMS → Services first" @endif>
          {{ $ui['add_store_department'] ?? 'Add Department' }}
        </button>
        <div class="stores-search-field">
          <label class="control-label stores-search-label" for="storesStoreDeptSearch">{{ $ui['store_department_search_placeholder'] ?? 'Search department…' }}</label>
          <div class="input-group stores-search-input">
            <input type="search" name="category_search" id="storesStoreDeptSearch" class="form-control"
              placeholder="{{ $ui['store_department_search_placeholder'] ?? 'Search department…' }}" value="{{ $categorySearch ?? '' }}">
            <div class="input-group-append">
              <button type="submit" class="btn btn-light border" aria-label="Search"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

@if(($pmsServicesForStoreDepartments ?? collect())->isEmpty())
  <p class="text-muted small px-3 mb-2">
    Create services under <a href="{{ route('hotel.services.index') }}">PMS → Services</a>, then link each store department to a service here.
  </p>
@endif

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        <th>{{ $ui['store_department_name'] ?? 'Department name' }}</th>
        <th>{{ $ui['service_offered'] ?? 'Service offered' }}</th>
        <th style="width:72px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($categoryTree as $cat)
        <tr>
          <td>{{ $cat->name }}</td>
          <td>
            @if($cat->pmsService)
              <a href="{{ route('hotel.services.show', $cat->pmsService) }}">{{ $cat->pmsService->name }}</a>
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td class="text-nowrap">
            <button type="button" class="stores-icon-btn text-primary js-stores-edit-store-dept" title="Edit"
              data-id="{{ $cat->id }}"
              data-name="{{ e($cat->name) }}"
              data-service-id="{{ $cat->hotel_pms_service_id ?? '' }}">
              <i class="fa fa-pencil"></i>
            </button>
            <form method="POST" action="{{ route('hotel.stores.categories.destroy', $cat) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="stores-icon-btn text-danger" title="Delete"
                data-title="Delete department?"
                data-text="Remove {{ e($cat->name) }}? Master items in this department will also be removed."
                data-confirm="Yes, delete it">
                <i class="fa fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="text-muted text-center py-4">
            No departments yet. Use <strong>Add Department</strong> to link a name to a PMS service.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="modal fade" id="storesStoreDepartmentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.stores.categories.store') }}" id="storesStoreDepartmentForm" data-no-loader>
        @csrf
        <input type="hidden" name="_method" id="storesStoreDepartmentMethod" value="">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesStoreDepartmentModalTitle">{{ $ui['add_store_department'] ?? 'Add Department' }}</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0">
          <div class="stores-modal-divider"></div>
          <div class="form-group">
            <label class="control-label">{{ $ui['store_department_name'] ?? 'Department name' }} <span class="text-danger">*</span></label>
            <input type="text" name="name" id="storesStoreDepartmentName" class="form-control" required maxlength="120"
              value="{{ old('name') }}">
          </div>
          <div class="form-group mb-0">
            <label class="control-label">{{ $ui['service_offered'] ?? 'Service offered' }} <span class="text-danger">*</span></label>
            <select name="hotel_pms_service_id" id="storesStoreDepartmentService" class="form-control" required>
              <option value="">Select service…</option>
              @foreach($pmsServicesForStoreDepartments ?? [] as $service)
                <option value="{{ $service->id }}" @selected((string) old('hotel_pms_service_id') === (string) $service->id)>{{ $service->name }}</option>
              @endforeach
            </select>
            <p class="text-muted small mb-0 mt-2">{{ $ui['store_department_service_hint'] ?? '' }}</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-stores-outline">{{ $ui['submit'] ?? 'Submit' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
jQuery(function ($) {
  var storeUrl = @json(route('hotel.stores.categories.store'));
  var updateUrlTemplate = @json(url('/hotel/stores/categories/__ID__'));
  var addTitle = @json($ui['add_store_department'] ?? 'Add Department');
  var editTitle = @json($ui['edit_store_department'] ?? 'Edit Department');
  var mode = 'add';

  function setAddMode() {
    $('#storesStoreDepartmentForm').attr('action', storeUrl);
    $('#storesStoreDepartmentMethod').val('');
    $('#storesStoreDepartmentModalTitle').text(addTitle);
  }

  function fillEdit(data) {
    $('#storesStoreDepartmentForm').attr('action', updateUrlTemplate.replace('__ID__', data.id));
    $('#storesStoreDepartmentMethod').val('PUT');
    $('#storesStoreDepartmentModalTitle').text(editTitle);
    $('#storesStoreDepartmentName').val(data.name || '');
    $('#storesStoreDepartmentService').val(data.serviceId ? String(data.serviceId) : '');
  }

  $('#storesStoreDepartmentModal').on('show.bs.modal', function () {
    if (mode === 'edit') {
      return;
    }
    setAddMode();
    $('#storesStoreDepartmentName').val('');
    $('#storesStoreDepartmentService').val('');
  });

  $('#storesStoreDepartmentModal').on('hidden.bs.modal', function () {
    mode = 'add';
  });

  $('.js-stores-store-dept-add').on('click', function () {
    mode = 'add';
  });

  $(document).on('click', '.js-stores-edit-store-dept', function (e) {
    e.preventDefault();
    var $btn = $(this);
    if (!$btn.data('id')) {
      return;
    }
    mode = 'edit';
    fillEdit({
      id: $btn.data('id'),
      name: $btn.data('name') || '',
      serviceId: $btn.data('service-id') || '',
    });
    $('#storesStoreDepartmentModal').modal('show');
  });

  @if($errors->any() && ($settingsTab ?? '') === 'item-category')
    mode = @json(old('_method') === 'PUT' ? 'edit' : 'add');
    $('#storesStoreDepartmentModal').modal('show');
    @if(old('_method') === 'PUT')
      fillEdit({
        id: @json(old('stores_store_dept_edit_id')),
        name: @json(old('name')),
        serviceId: @json(old('hotel_pms_service_id')),
      });
    @else
      setAddMode();
    @endif
  @endif
});
</script>
@endpush
