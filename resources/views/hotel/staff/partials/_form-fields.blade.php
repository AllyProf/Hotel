@php
  $isEdit = $isEdit ?? false;
  $member = $staff ?? null;
  $hrSettings = $hrSettings ?? [];
  $documentTypes = $documentTypes ?? [];
  $emailDatalistOptions = $emailDatalistOptions ?? [];
  $emailDomains = $emailDomains ?? [];
  $phoneCode = old('phone_country_code', $member->phone_country_code ?? ($hotel->phone_country_code ?? '+255'));
  $existingDocs = isset($member) ? $member->staffDocuments->keyBy('document_key') : collect();
  $attachmentErrorKeys = collect($documentTypes)->map(fn ($t) => 'staff_documents.'.$t['key'])->all();
  $openAttachments = (bool) old('include_attachments') || $errors->hasAny($attachmentErrorKeys);
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
    <div class="form-group staff-email-autocomplete">
      <label class="control-label">Email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="staff_email"
        value="{{ old('email', $member->email ?? '') }}" autocomplete="off" spellcheck="false" required
        aria-autocomplete="list" aria-controls="staff_email_suggestions" aria-expanded="false">
      <ul class="staff-email-suggestions-list" id="staff_email_suggestions" role="listbox" hidden></ul>
      @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Phone</label>
      <div class="staff-phone-group">
        <select id="staff_phone_code" class="form-control" aria-label="Country calling code"></select>
        <input type="hidden" name="phone_country_code" id="staff_phone_country_code" value="{{ $phoneCode }}">
        <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="staff_phone"
          value="{{ old('phone', $member->phone ?? '') }}" inputmode="tel" placeholder="Local number">
      </div>
      @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      @error('phone_country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Department <small class="text-muted">(optional)</small></label>
      <select class="form-control @error('hotel_department_id') is-invalid @enderror" name="hotel_department_id">
        <option value="">— Not assigned —</option>
        @foreach($departments ?? [] as $department)
          <option value="{{ $department->id }}" {{ (string) old('hotel_department_id', $member->hotel_department_id ?? '') === (string) $department->id ? 'selected' : '' }}>
            {{ $department->name }}
          </option>
        @endforeach
      </select>
      @error('hotel_department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
      <label class="control-label d-flex justify-content-between align-items-center">
        <span>{{ $isEdit ? 'New password' : 'Password' }}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="staff_password_generate" title="Generate secure password">
          <i class="fa fa-magic"></i> Generate
        </button>
      </label>
      <div class="staff-password-wrap input-group">
        <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="staff_password"
          {{ $isEdit ? '' : 'required' }} autocomplete="new-password"
          data-min-length="{{ (int) ($hrSettings['min_password_length'] ?? 8) }}"
          data-require-strong="{{ ! empty($hrSettings['require_strong_password']) ? '1' : '0' }}">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary" type="button" id="staff_password_toggle" aria-label="Show password">
            <i class="fa fa-eye" id="staff_password_toggle_icon"></i>
          </button>
        </div>
      </div>
      <div class="staff-password-strength mt-2" id="staff_password_strength" aria-hidden="true">
        <div class="staff-password-strength__bar"><span></span><span></span><span></span><span></span></div>
        <small class="staff-password-strength__label text-muted" id="staff_password_strength_label"></small>
      </div>
      @if($isEdit)
        <small class="text-muted">Leave blank to keep current password.</small>
      @endif
      @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label class="control-label">Confirm password</label>
      <div class="staff-password-wrap input-group">
        <input type="password" class="form-control" name="password_confirmation" id="staff_password_confirm" autocomplete="new-password">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary" type="button" id="staff_password_confirm_toggle" aria-label="Show confirm password">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  @if(count($documentTypes) > 0)
    <div class="col-md-12">
      <div class="staff-attachments-toggle mt-3 mb-2">
        <label class="animated-checkbox mb-0">
          <input type="checkbox" id="staff_attachments_open" name="include_attachments" value="1"
            {{ $openAttachments ? 'checked' : '' }}>
          <span class="label-text" style="font-weight:700;font-size:16px;">
            <i class="fa fa-paperclip"></i> Attachments &amp; documents
          </span>
        </label>
        <p class="text-muted small mb-0 mt-2">
          Check to upload files. Types are configured in
          <a href="{{ route('hotel.settings.index', ['tab' => 'hr']) }}">Settings → HR &amp; Staff</a>.
        </p>
      </div>
      <div id="staff_attachments_panel" class="staff-attachments-panel" @if(! $openAttachments) hidden @endif>
        <div class="row pt-2">
          @foreach($documentTypes as $docType)
            @php
              $docKey = $docType['key'];
              $existing = $existingDocs->get($docKey);
            @endphp
            <div class="col-md-6 col-lg-4">
              <div class="form-group staff-doc-form-group">
                <label class="control-label d-flex align-items-center flex-wrap" style="gap:6px;">
                  <span>{{ $docType['label'] }}</span>
                  @if($docType['required'] && ! $existing)
                    <span class="badge badge-danger">Required</span>
                  @elseif($existing)
                    <span class="badge badge-success">Uploaded</span>
                  @else
                    <span class="badge badge-secondary">Optional</span>
                  @endif
                </label>
                @include('hotel.staff.partials._staff-document-upload', [
                  'docKey' => $docKey,
                  'docLabel' => $docType['label'],
                  'required' => $docType['required'] && ! $existing,
                  'existing' => $existing,
                ])
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  <div class="col-md-12">
    <div class="form-group mb-0">
      <label class="mb-0">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $member->is_active ?? true) ? 'checked' : '' }}>
        Active — can sign in to the system
      </label>
    </div>
  </div>
</div>
