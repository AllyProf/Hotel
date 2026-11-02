@php
  $settingsKey = $procSettingsKey ?? 'hk_procurement';
  $tabKey = $procSettingsTab ?? $settingsKey;
  $moduleLabel = $procModuleLabel ?? 'Department';
  $moduleHint = $procModuleHint ?? '';
  $root = is_array($settings->{$settingsKey}) ? $settings->{$settingsKey} : app(\App\Services\HotelSettingsService::class)->defaultDepartmentProcurement();
  $pl = $root['purchase_list'] ?? [];
  $recipientEmails = $pl['recipient_emails'] ?? [];
  $emailsText = is_array($recipientEmails) ? implode(', ', $recipientEmails) : (string) $recipientEmails;
  $selectedStaffIds = collect($pl['recipient_staff_user_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
  $selectedAccountantIds = collect($pl['accountant_staff_user_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
  $payablesVendors = \App\Models\HotelVendor::query()->where('hotel_id', $hotel->id)->orderBy('name')->get();
  $defaultVendorId = (int) ($pl['default_vendor_id'] ?? 0);
  $staffUsers = $hotel->users()
    ->where('role', \App\Models\User::ROLE_HOTEL_STAFF)
    ->whereNotNull('email')
    ->orderBy('name')
    ->get();
@endphp
<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="{{ $tabKey }}">

  <h4 class="settings-section-title">{{ $moduleLabel }} purchase lists</h4>
  @if($moduleHint !== '')
    <p class="text-muted">{!! $moduleHint !!}</p>
  @endif

  <div class="form-group">
    <label class="control-label">Budget currency</label>
    @include('partials._currency-select', [
      'name' => 'purchase_list_budget_currency',
      'selected' => old('purchase_list_budget_currency', $pl['budget_currency'] ?? ''),
      'allowEmpty' => true,
      'emptyLabel' => 'Automatic (Prices local currency, then hotel default)',
    ])
  </div>

  <div class="form-group">
    <label class="control-label">Default Payables vendor (PO on accountant approve)</label>
    <select class="form-control" name="purchase_list_default_vendor_id">
      <option value="">Automatic — Market / cash purchases</option>
      @foreach($payablesVendors as $vendor)
        <option value="{{ $vendor->id }}" @selected($defaultVendorId === (int) $vendor->id)>{{ $vendor->name }}</option>
      @endforeach
    </select>
  </div>

  <div class="form-group">
    <label class="control-label">Accountant approvers</label>
    @if($staffUsers->isEmpty())
      <p class="text-muted mb-0">No staff with email on file.</p>
    @else
      <div class="row">
        @foreach($staffUsers as $member)
          <div class="col-md-6 col-lg-4">
            <label class="animated-checkbox d-block mb-2">
              <input type="checkbox" name="purchase_list_accountant_staff_user_ids[]" value="{{ $member->id }}"
                {{ in_array((int) $member->id, $selectedAccountantIds, true) ? 'checked' : '' }}>
              <span class="label-text">{{ $member->name }} <small class="text-muted">({{ $member->email }})</small></span>
            </label>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="form-group">
    <label class="control-label">Store / procurement recipients (email)</label>
    <input class="form-control" name="purchase_list_recipient_emails" value="{{ $emailsText }}"
      placeholder="stores@hotel.com, procurement@hotel.com">
    <p class="text-muted mb-0 mt-2" style="font-size:13px;">Department lists from {{ $moduleLabel }} are sent here for review and budgeting (usually the store manager).</p>
  </div>

  <div class="form-group">
    <label class="control-label">Also notify staff members</label>
    @if($staffUsers->isEmpty())
      <p class="text-muted mb-0">No staff with email on file.</p>
    @else
      <div class="row">
        @foreach($staffUsers as $member)
          <div class="col-md-6 col-lg-4">
            <label class="animated-checkbox d-block mb-2">
              <input type="checkbox" name="purchase_list_recipient_staff_user_ids[]" value="{{ $member->id }}"
                {{ in_array((int) $member->id, $selectedStaffIds, true) ? 'checked' : '' }}>
              <span class="label-text">{{ $member->name }} <small class="text-muted">({{ $member->email }})</small></span>
            </label>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <label class="animated-checkbox d-block mb-4">
    <input type="checkbox" name="purchase_list_include_hotel_email" value="1"
      {{ ! empty($pl['include_hotel_email']) ? 'checked' : '' }}>
    <span class="label-text">Include the hotel main email ({{ $hotel->email ?: 'not set on hotel profile' }})</span>
  </label>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
  </div>
</form>
