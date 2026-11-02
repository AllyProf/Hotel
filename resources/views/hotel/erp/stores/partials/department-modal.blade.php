<div class="modal fade" id="storesDepartmentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.stores.departments.store') }}" id="storesDepartmentForm" data-no-loader>
        @csrf
        <input type="hidden" name="_method" id="storesDepartmentMethod" value="">
        <input type="hidden" name="stores_department_edit_id" id="storesDepartmentEditId" value="{{ old('stores_department_edit_id') }}">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesDepartmentModalTitle">{{ $ui['add_department'] ?? 'Add Department' }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0">
          <div class="stores-modal-divider"></div>
          <div class="form-group">
            <label class="control-label">Department Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="storesDepartmentName"
              value="{{ old('name') }}" required maxlength="120">
            @error('name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
          </div>
          <div class="form-group">
            <label class="control-label">Contact Person</label>
            <input type="text" class="form-control @error('contact_person') is-invalid @enderror" name="contact_person" id="storesDepartmentContact"
              value="{{ old('contact_person') }}" maxlength="120">
            @error('contact_person')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
          </div>
          <div class="form-group mb-0">
            <label class="control-label">Amount Issued</label>
            <input type="number" step="0.01" min="0" class="form-control @error('amount_issued') is-invalid @enderror" name="amount_issued" id="storesDepartmentAmount"
              value="{{ old('amount_issued', '0') }}">
            @error('amount_issued')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-stores-outline">{{ $ui['submit'] ?? 'Submit' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
