@php
  $selected = old('permissions', isset($role) ? $role->permissionList() : []);
@endphp

<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Role name</label>
      <input type="text" class="form-control @error('name') is-invalid @enderror" name="name"
        value="{{ old('name', $role->name ?? '') }}" required maxlength="120">
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Description</label>
      <input type="text" class="form-control @error('description') is-invalid @enderror" name="description"
        value="{{ old('description', $role->description ?? '') }}" maxlength="500">
      @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="form-group">
  <label class="control-label d-block mb-2">Permissions</label>
  <div class="row">
    @foreach($permissionGroups as $group => $permissions)
      <div class="col-md-6 col-lg-4 mb-3">
        <div class="card border h-100">
          <div class="card-header bg-light py-2 font-weight-bold">{{ $group }}</div>
          <div class="card-body py-2">
            @foreach($permissions as $key => $label)
              <label class="d-block mb-2">
                <input type="checkbox" name="permissions[]" value="{{ $key }}"
                  {{ in_array($key, $selected, true) ? 'checked' : '' }}>
                {{ $label }}
              </label>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
