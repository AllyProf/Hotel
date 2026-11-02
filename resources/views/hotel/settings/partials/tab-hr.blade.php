@php
  $hr = $settings->hr ?? app(\App\Services\HotelSettingsService::class)->defaultHr();
  $docTypes = $hr['staff_document_types'] ?? [];
  $hints = $hr['email_domain_hints'] ?? [];
  if (is_array($hints)) {
    $hintsText = implode(', ', $hints);
  } else {
    $hintsText = (string) $hints;
  }
  $emptyRow = ['key' => '', 'label' => '', 'required' => false, 'enabled' => false];
  $rows = array_values($docTypes);
  while (count($rows) < 6) {
    $rows[] = $emptyRow;
  }
@endphp
<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="hr">

  <h4 class="settings-section-title">Staff accounts</h4>
  <p class="text-muted">These options apply when creating or editing staff under <strong>Staff</strong>.</p>
  <div class="row">
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">Minimum password length</label>
        <input class="form-control" type="number" min="6" max="32" name="min_password_length"
          value="{{ $hr['min_password_length'] ?? 8 }}">
      </div>
    </div>
    <div class="col-md-8">
      <label class="animated-checkbox d-block mb-0 mt-4">
        <input type="checkbox" name="require_strong_password" value="1"
          {{ ! empty($hr['require_strong_password']) ? 'checked' : '' }}>
        <span class="label-text">Require strong passwords (uppercase, lowercase, and a number)</span>
      </label>
    </div>
  </div>

  <div class="form-group">
    <label class="control-label">Email domain hints</label>
    <input class="form-control" name="email_domain_hints" value="{{ $hintsText }}"
      placeholder="yourhotel.com, company.co.tz">
    <p class="text-muted mb-0 mt-2" style="font-size:13px;">Comma-separated domains suggested on the staff email field. Your hotel email domain is added automatically.</p>
  </div>

  <h4 class="settings-section-title">Staff attachments</h4>
  <p class="text-muted">Enable document types shown on the staff form. Mark <strong>Required</strong> for uploads mandatory on new staff (optional when replacing an existing file on edit).</p>
  <div class="table-responsive">
    <table class="table table-bordered settings-table">
      <thead>
        <tr>
          <th style="width:22%">Key <small class="text-muted">(slug)</small></th>
          <th>Label</th>
          <th style="width:90px">Enabled</th>
          <th style="width:90px">Required</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $index => $row)
          <tr>
            <td>
              <input class="form-control form-control-sm" type="text"
                name="staff_document_types[{{ $index }}][key]" value="{{ $row['key'] ?? '' }}"
                placeholder="national_id">
            </td>
            <td>
              <input class="form-control form-control-sm" type="text"
                name="staff_document_types[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}"
                placeholder="National ID / Passport">
            </td>
            <td class="text-center">
              <input type="checkbox" name="staff_document_types[{{ $index }}][enabled]" value="1"
                {{ ! empty($row['enabled']) ? 'checked' : '' }}>
            </td>
            <td class="text-center">
              <input type="checkbox" name="staff_document_types[{{ $index }}][required]" value="1"
                {{ ! empty($row['required']) ? 'checked' : '' }}>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save HR &amp; Staff</button>
  </div>
</form>
