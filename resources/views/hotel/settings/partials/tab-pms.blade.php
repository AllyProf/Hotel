@php $pms = $settings->pms ?? []; $reservation = $settings->reservation ?? []; @endphp
<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="pms">

  <h4 class="settings-section-title">Room Settings</h4>
  <div class="row">
    <div class="col-md-4"><div class="form-group"><label class="control-label">Invoice Heading</label><input class="form-control" name="invoice_heading" value="{{ $pms['invoice_heading'] ?? 'Tax Invoice' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Invoice Name</label><input class="form-control" name="invoice_name" value="{{ $pms['invoice_name'] ?? $hotel->name }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Invoice Address</label><input class="form-control" name="invoice_address" value="{{ $pms['invoice_address'] ?? $hotel->address }}"></div></div>
  </div>
  <div class="row">
    <div class="col-md-4"><div class="form-group"><label class="control-label">Folio Prefix</label><input class="form-control" name="folio_prefix" value="{{ $pms['folio_prefix'] ?? '' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Invoice Prefix</label><input class="form-control" name="invoice_prefix" value="{{ $pms['invoice_prefix'] ?? '' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Receipt Prefix</label><input class="form-control" name="receipt_prefix" value="{{ $pms['receipt_prefix'] ?? '' }}"></div></div>
  </div>
  <div class="row">
    <div class="col-md-4"><div class="form-group"><label class="control-label">Night Audit Time</label><input class="form-control" type="time" name="night_audit_time" value="{{ $pms['night_audit_time'] ?? '05:00' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Report Time</label><input class="form-control" type="time" name="report_time" value="{{ $pms['report_time'] ?? '11:00' }}"></div></div>
  </div>

  <h4 class="settings-section-title">Invoice Format</h4>
  <div class="row">
    <div class="col-md-6">
      <label class="animated-checkbox d-block"><input type="checkbox" name="allow_overbooking" value="1" {{ ($pms['allow_overbooking'] ?? false) ? 'checked' : '' }}><span class="label-text">Allow overbooking</span></label>
      <div class="form-group"><label class="control-label">Overbooking Count</label><input class="form-control" type="number" name="overbooking_count" value="{{ $pms['overbooking_count'] ?? 0 }}"></div>
      <label class="animated-checkbox d-block"><input type="checkbox" name="hide_rate_in_grc" value="1" {{ ($pms['hide_rate_in_grc'] ?? false) ? 'checked' : '' }}><span class="label-text">Hide Rate In GRC</span></label>
      <label class="animated-checkbox d-block"><input type="checkbox" name="separate_items_per_date" value="1" {{ ($pms['separate_items_per_date'] ?? false) ? 'checked' : '' }}><span class="label-text">Separate items in invoice per date</span></label>
      <label class="animated-checkbox d-block"><input type="checkbox" name="balance_payable" value="1" {{ ($pms['balance_payable'] ?? false) ? 'checked' : '' }}><span class="label-text">Balance Payable</span></label>
      <label class="animated-checkbox d-block"><input type="checkbox" name="hide_tax_blurb" value="1" {{ ($pms['hide_tax_blurb'] ?? false) ? 'checked' : '' }}><span class="label-text">Hide Tax blurb</span></label>
    </div>
    <div class="col-md-6">
      <div class="form-group"><label class="control-label">Invoice Total Text</label><input class="form-control" name="invoice_total_text" value="{{ $pms['invoice_total_text'] ?? '' }}"></div>
      <div class="form-group"><label class="control-label">Tax Text</label><input class="form-control" name="tax_text" value="{{ $pms['tax_text'] ?? '' }}"></div>
    </div>
  </div>

  <h4 class="settings-section-title">Reservation Settings</h4>
  @foreach(['segments' => 'Segment', 'payment_modes' => 'Payment Mode', 'identity_types' => 'Identity Type', 'expense_categories' => 'Expense Category'] as $key => $title)
    <h5 class="mt-3">{{ $title }}</h5>
    <div class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead><tr><th>Sr. no</th><th>{{ $title }}</th></tr></thead>
        <tbody>
          @foreach($reservation[$key] ?? [] as $i => $item)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><input class="form-control form-control-sm" type="text" name="{{ $key }}[]" value="{{ $item }}"></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endforeach

  <h4 class="settings-section-title">Notifications</h4>
  <label class="animated-checkbox d-block mb-2"><input type="checkbox" checked disabled><span class="label-text">Send Mail on Booking Confirmation</span></label>
  <div class="form-group">
    <label class="control-label">Email ID</label>
    <input class="form-control" type="email" name="booking_confirmation_email" value="{{ $pms['booking_confirmation_email'] ?? $hotel->email }}">
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save PMS Settings</button>
  </div>
</form>
