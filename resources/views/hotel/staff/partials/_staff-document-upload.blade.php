@php
  $docKey = $docKey ?? '';
  $docLabel = $docLabel ?? 'Document';
  $required = $required ?? false;
  $existing = $existing ?? null;
  $inputId = 'staff_doc_'.$docKey;
@endphp

<div class="staff-doc-upload @error('staff_documents.'.$docKey) is-invalid @enderror" data-max-bytes="10485760">
  @if($existing)
    <div class="staff-doc-upload__current mb-2">
      <span class="staff-doc-upload__current-icon"><i class="fa fa-check-circle"></i></span>
      <div class="staff-doc-upload__current-body">
        <span class="staff-doc-upload__current-label">On file</span>
        <a href="{{ $existing->url() }}" target="_blank" rel="noopener" class="staff-doc-upload__current-name">
          {{ $existing->original_name ?? 'View document' }}
        </a>
      </div>
      <a href="{{ $existing->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-external-link"></i>
      </a>
    </div>
    <p class="text-muted small mb-2 js-staff-doc-replace-hint">Upload a new file below to replace the current one.</p>
  @endif

  <input type="file" class="d-none js-staff-doc-input" id="{{ $inputId }}"
    name="staff_documents[{{ $docKey }}]" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
    @if($required && ! $existing) data-required="1" @endif>

  <div class="staff-doc-upload__zone js-staff-doc-zone" tabindex="0" role="button"
    aria-labelledby="{{ $inputId }}_label" aria-describedby="{{ $inputId }}_hint">
    <div class="staff-doc-upload__empty js-staff-doc-empty">
      <div class="staff-doc-upload__icon-wrap">
        <i class="fa fa-cloud-upload"></i>
      </div>
      <p class="staff-doc-upload__title mb-1" id="{{ $inputId }}_label">
        @if($existing)
          Choose replacement file
        @else
          Drop file here or <span class="staff-doc-upload__browse">browse</span>
        @endif
      </p>
      <p class="staff-doc-upload__hint mb-0" id="{{ $inputId }}_hint">PDF, JPG, or PNG · maximum 10 MB</p>
    </div>

    <div class="staff-doc-upload__selected js-staff-doc-selected d-none">
      <div class="staff-doc-upload__thumb js-staff-doc-thumb">
        <i class="fa fa-file-pdf-o js-staff-doc-thumb-icon"></i>
        <img src="" alt="" class="js-staff-doc-thumb-img d-none">
      </div>
      <div class="staff-doc-upload__meta">
        <span class="staff-doc-upload__filename js-staff-doc-filename"></span>
        <span class="staff-doc-upload__filesize js-staff-doc-filesize text-muted"></span>
        <span class="staff-doc-upload__status js-staff-doc-status text-success"><i class="fa fa-check"></i> Ready to upload</span>
      </div>
      <button type="button" class="btn btn-sm btn-link text-danger js-staff-doc-clear" title="Remove selected file">
        <i class="fa fa-times"></i> Remove
      </button>
    </div>

    <div class="staff-doc-upload__error js-staff-doc-error d-none">
      <i class="fa fa-exclamation-circle"></i>
      <span class="js-staff-doc-error-text"></span>
    </div>
  </div>

  @error('staff_documents.'.$docKey)
    <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
  @enderror
</div>
