<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Plan Name <span class="text-danger">*</span></label>
      <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name', $plan->name ?? '') }}" required>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Price <span class="text-danger">*</span></label>
      <input class="form-control @error('price') is-invalid @enderror" type="number" name="price" min="0" step="0.01" value="{{ old('price', $plan->price ?? '') }}" required>
      @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Billing Cycle <span class="text-danger">*</span></label>
      <select class="form-control @error('billing_cycle') is-invalid @enderror" name="billing_cycle" required>
        <option value="monthly" {{ old('billing_cycle', $plan->billing_cycle ?? 'monthly') === 'monthly' ? 'selected' : '' }}>Monthly</option>
        <option value="yearly" {{ old('billing_cycle', $plan->billing_cycle ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option>
      </select>
      @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Max Rooms <span class="text-danger">*</span></label>
      <input class="form-control @error('max_rooms') is-invalid @enderror" type="number" name="max_rooms" min="0" value="{{ old('max_rooms', $plan->max_rooms ?? 0) }}" required>
      <small class="text-muted">Use 0 for unlimited</small>
      @error('max_rooms')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Max Users <span class="text-danger">*</span></label>
      <input class="form-control @error('max_users') is-invalid @enderror" type="number" name="max_users" min="0" value="{{ old('max_users', $plan->max_users ?? 0) }}" required>
      <small class="text-muted">Use 0 for unlimited</small>
      @error('max_users')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Max Branches <span class="text-danger">*</span></label>
      <input class="form-control @error('max_branches') is-invalid @enderror" type="number" name="max_branches" min="0" value="{{ old('max_branches', $plan->max_branches ?? 0) }}" required>
      <small class="text-muted">Use 0 for unlimited (multi-branch plans)</small>
      @error('max_branches')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-3">
    <div class="form-group">
      <label class="control-label">Sort Order</label>
      <input class="form-control @error('sort_order') is-invalid @enderror" type="number" name="sort_order" min="0" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
      @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-3">
    <div class="form-group pt-4">
      <div class="animated-checkbox">
        <label>
          <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
          <span class="label-text">Plan is active</span>
        </label>
      </div>
    </div>
  </div>
</div>

<div class="form-group">
  <label class="control-label">Description</label>
  <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3">{{ old('description', $plan->description ?? '') }}</textarea>
  @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<hr>
@include('admin.plans.partials._feature-checkboxes', ['featureOptions' => $featureOptions])
