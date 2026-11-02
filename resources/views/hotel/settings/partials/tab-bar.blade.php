@php
  $bar = $settings->bar ?? app(\App\Services\HotelSettingsService::class)->defaultBar();
  $pl = $bar['purchase_list'] ?? [];
  $recipientEmails = $pl['recipient_emails'] ?? [];
  $emailsText = is_array($recipientEmails) ? implode(', ', $recipientEmails) : (string) $recipientEmails;
  $selectedStaffIds = collect($pl['recipient_staff_user_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
  $selectedAccountantIds = collect($pl['accountant_staff_user_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
  $barPayablesVendors = \App\Models\HotelVendor::query()->where('hotel_id', $hotel->id)->orderBy('name')->get();
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
  <input type="hidden" name="tab" value="bar">

  <h4 class="settings-section-title">Bar purchase lists</h4>
  <p class="text-muted">When a department sends a purchase list (Bar, HK, Laundry, Reception, etc.), <strong>recipients below — typically the store manager / stores team</strong> — get an email with a link to review the list and submit a budget. The sending department tracks status under <strong>Purchase lists</strong>. The store team can still create buying directly under <strong>ERP → Stores → Purchase Orders</strong>.</p>

  <div class="form-group">
    <label class="control-label">Budget currency</label>
    @include('partials._currency-select', [
      'name' => 'purchase_list_budget_currency',
      'selected' => old('purchase_list_budget_currency', $pl['budget_currency'] ?? ''),
      'allowEmpty' => true,
      'emptyLabel' => 'Automatic (Prices local currency, then hotel default)',
    ])
    <p class="text-muted mb-0 mt-2" style="font-size:13px;">Used for procurement totals on purchase lists (e.g. TZS). Not hardcoded — set this if your hotel profile uses USD but stores buy in local currency.</p>
  </div>

  <div class="form-group">
    <label class="control-label">Default Payables vendor (PO on accountant approve)</label>
    <select class="form-control" name="purchase_list_default_vendor_id">
      <option value="">Automatic — Market / cash purchases</option>
      @foreach($barPayablesVendors as $vendor)
        <option value="{{ $vendor->id }}" @selected($defaultVendorId === (int) $vendor->id)>{{ $vendor->name }}</option>
      @endforeach
    </select>
    <p class="text-muted mb-0 mt-2" style="font-size:13px;">When a purchase list budget is approved, an open PO is created for this vendor. Add vendors under <strong>ERP → Accounts → Payables</strong>.</p>
  </div>

  <div class="form-group">
    <label class="control-label">Accountant approvers (final budget / payment release)</label>
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
      <p class="text-muted mb-0 mt-2" style="font-size:13px;">After procurement submits a budget (any department on the list), these users approve it under <strong>ERP → Accounts → Purchase budgets</strong>. <strong>Hotel admin</strong> can always approve without being listed here.</p>
    @endif
  </div>

  <div class="form-group">
    <label class="control-label">Recipient email addresses</label>
    <input class="form-control" name="purchase_list_recipient_emails" value="{{ $emailsText }}"
      placeholder="stores@hotel.com, procurement@hotel.com">
    <p class="text-muted mb-0 mt-2" style="font-size:13px;">Comma-separated. Typical targets: stores, procurement, or accounts.</p>
  </div>

  <div class="form-group">
    <label class="control-label">Also notify staff members</label>
    @if($staffUsers->isEmpty())
      <p class="text-muted mb-0">No staff with email on file. Add staff under <strong>My Hotel → Staff</strong>.</p>
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
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Bar settings</button>
  </div>
</form>
