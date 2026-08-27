@php
  $isEdit = $isEdit ?? false;
  $member = $staff ?? null;
@endphp

<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Full name</label>
      <input type="text" class="form-control @error('name') is-invalid @enderror" name="name"
        value="{{ old('name', $member->name ?? '') }}" required>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror" name="email"
        value="{{ old('email', $member->email ?? '') }}" required>
      @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Phone</label>
      <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone"
        value="{{ old('phone', $member->phone ?? '') }}">
      @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Role</label>
      <select class="form-control @error('hotel_role_id') is-invalid @enderror" name="hotel_role_id" required>
        <option value="">Select role</option>
        @foreach($roles as $role)
          <option value="{{ $role->id }}" {{ (string) old('hotel_role_id', $member->hotel_role_id ?? '') === (string) $role->id ? 'selected' : '' }}>
            {{ $role->name }}
          </option>
        @endforeach
      </select>
      @error('hotel_role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>

  @if($branches->isNotEmpty())
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Branch <small class="text-muted">(optional)</small></label>
        <select class="form-control @error('branch_id') is-invalid @enderror" name="branch_id">
          <option value="">All / headquarters</option>
          @foreach($branches as $branch)
            <option value="{{ $branch->id }}" {{ (string) old('branch_id', $member->branch_id ?? '') === (string) $branch->id ? 'selected' : '' }}>
              {{ $branch->name }}
            </option>
          @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
  @endif

  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">{{ $isEdit ? 'New password' : 'Password' }}</label>
      <input type="password" class="form-control @error('password') is-invalid @enderror" name="password"
        {{ $isEdit ? '' : 'required' }} autocomplete="new-password">
      @if($isEdit)
        <small class="text-muted">Leave blank to keep current password.</small>
      @endif
      @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Confirm password</label>
      <input type="password" class="form-control" name="password_confirmation" autocomplete="new-password">
    </div>
  </div>
  <div class="col-md-12">
    <div class="form-group mb-0">
      <label class="mb-0">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $member->is_active ?? true) ? 'checked' : '' }}>
        Active — can sign in to the system
      </label>
    </div>
  </div>
</div>
