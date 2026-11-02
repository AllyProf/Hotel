@php
  $pms = $settings->pms ?? [];
  $reservation = $settings->reservation ?? [];
  $reservationDefaults = app(\App\Services\HotelSettingsService::class)->defaultReservation();

  foreach (['bank_providers', 'mobile_payment_providers'] as $providerKey) {
    if (empty($reservation[$providerKey])) {
      $reservation[$providerKey] = $reservationDefaults[$providerKey] ?? [];
    }
  }
@endphp
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

  <h4 class="settings-section-title">Front Desk</h4>
  <p class="text-muted mb-3">Standard check-in / check-out times and late checkout fee used on the Check-out page.</p>
  <div class="row">
    <div class="col-md-4"><div class="form-group"><label class="control-label">Check-in Time</label><input class="form-control" type="time" name="front_desk_checkin_time" value="{{ $pms['front_desk_checkin_time'] ?? '14:00' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Check-out Time</label><input class="form-control" type="time" name="front_desk_checkout_time" value="{{ $pms['front_desk_checkout_time'] ?? '11:00' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Late Check-out Amount</label><input class="form-control" type="number" step="0.01" min="0" name="late_checkout_amount" value="{{ $pms['late_checkout_amount'] ?? 50 }}"></div></div>
  </div>
  <div class="row">
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Room VAT Rate (%)</label>
        <input class="form-control" type="number" step="0.01" min="0" max="100" name="vat_rate"
          value="{{ $pms['vat_rate'] ?? 18 }}">
        <p class="text-muted mb-0 mt-2" style="font-size:13px;">Used on Create Reservation to split room charges and VAT (e.g. 18 for Tanzania).</p>
      </div>
    </div>
    <div class="col-md-8">
      <label class="animated-checkbox d-block mb-0">
        <input type="checkbox" name="payment_reference_required" value="1" {{ ($pms['payment_reference_required'] ?? false) ? 'checked' : '' }}>
        <span class="label-text">Payment reference number is mandatory when collecting payment at check-out</span>
      </label>
      <p class="text-muted mb-0 mt-2" style="font-size:13px;">When unchecked, staff can record payments with or without a reference (e.g. M-Pesa code, bank transfer ID).</p>
    </div>
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
  <p class="text-muted mb-3">Manage dropdown options used on reservations, check-out payments, guest profiles, and expenses. Banks and mobile providers appear when the payment mode name includes <strong>Bank</strong> or <strong>Mobile</strong>.</p>
  @foreach([
    'segments' => 'Segment',
    'payment_modes' => 'Payment Mode',
    'bank_providers' => 'Bank',
    'mobile_payment_providers' => 'Mobile Payment Provider',
    'identity_types' => 'Identity Type',
    'expense_categories' => 'Expense Category',
  ] as $key => $title)
    <div class="pms-res-list mb-4" data-list-key="{{ $key }}">
      <div class="d-flex align-items-center justify-content-between flex-wrap mb-2" style="gap:8px;">
        <h5 class="mt-0 mb-0">{{ $title }}</h5>
        <button type="button" class="btn btn-sm btn-outline-secondary js-pms-res-add" data-field="{{ $key }}" data-label="{{ $title }}">
          <i class="fa fa-plus"></i> Add {{ $title }}
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead>
            <tr>
              <th style="width:72px;">Sr. no</th>
              <th>{{ $title }}</th>
              <th style="width:88px;">Action</th>
            </tr>
          </thead>
          <tbody class="js-pms-res-body" data-field="{{ $key }}">
            @forelse($reservation[$key] ?? [] as $i => $item)
              <tr class="js-pms-res-row">
                <td class="js-pms-res-index">{{ $i + 1 }}</td>
                <td>
                  <input class="form-control form-control-sm" type="text" name="{{ $key }}[]" value="{{ $item }}" maxlength="120" placeholder="{{ $title }} name">
                </td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline-danger js-pms-res-remove" title="Remove {{ $title }}" aria-label="Remove {{ $title }}">
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr class="js-pms-res-empty">
                <td colspan="3" class="text-muted text-center py-3">No {{ strtolower($title) }}s yet. Click <strong>Add {{ $title }}</strong>.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endforeach

  <template id="pmsResRowTemplate">
    <tr class="js-pms-res-row">
      <td class="js-pms-res-index"></td>
      <td>
        <input class="form-control form-control-sm js-pms-res-input" type="text" maxlength="120">
      </td>
      <td>
        <button type="button" class="btn btn-sm btn-outline-danger js-pms-res-remove" title="Remove item" aria-label="Remove item">
          <i class="fa fa-trash"></i>
        </button>
      </td>
    </tr>
  </template>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var template = document.getElementById('pmsResRowTemplate');

      function reindexRows(tbody) {
        var rows = tbody.querySelectorAll('.js-pms-res-row');
        rows.forEach(function (row, index) {
          var indexCell = row.querySelector('.js-pms-res-index');
          if (indexCell) {
            indexCell.textContent = String(index + 1);
          }
        });

        var emptyRow = tbody.querySelector('.js-pms-res-empty');
        if (emptyRow) {
          emptyRow.style.display = rows.length ? 'none' : '';
        }
      }

      function fieldName(tbody) {
        return tbody.getAttribute('data-field') + '[]';
      }

      document.querySelectorAll('.js-pms-res-add').forEach(function (button) {
        button.addEventListener('click', function () {
          var key = button.getAttribute('data-field');
          var label = button.getAttribute('data-label') || 'Item';
          var tbody = document.querySelector('.js-pms-res-body[data-field="' + key + '"]');

          if (!tbody || !template) {
            return;
          }

          var emptyRow = tbody.querySelector('.js-pms-res-empty');
          if (emptyRow) {
            emptyRow.remove();
          }

          var row = template.content.firstElementChild.cloneNode(true);
          var input = row.querySelector('.js-pms-res-input');

          if (input) {
            input.name = fieldName(tbody);
            input.placeholder = label + ' name';
          }

          var removeBtn = row.querySelector('.js-pms-res-remove');
          if (removeBtn) {
            removeBtn.setAttribute('title', 'Remove ' + label);
            removeBtn.setAttribute('aria-label', 'Remove ' + label);
          }

          tbody.appendChild(row);
          reindexRows(tbody);

          if (input) {
            input.focus();
          }
        });
      });

      document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-pms-res-remove');
        if (!button) {
          return;
        }

        var row = button.closest('.js-pms-res-row');
        var tbody = button.closest('.js-pms-res-body');

        if (!row || !tbody) {
          return;
        }

        row.remove();

        if (!tbody.querySelector('.js-pms-res-row')) {
          var empty = document.createElement('tr');
          empty.className = 'js-pms-res-empty';
          empty.innerHTML = '<td colspan="3" class="text-muted text-center py-3">No items yet. Click <strong>Add</strong> above.</td>';
          tbody.appendChild(empty);
        }

        reindexRows(tbody);
      });
    });
  </script>

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
