@php
  $phoneCode = old('phone_country_code', ($staff ?? null)?->phone_country_code ?? ($hotel->phone_country_code ?? '+255'));
  $emailDatalistOptions = $emailDatalistOptions ?? [];
  $emailDomains = $emailDomains ?? [];
@endphp

@push('styles')
  <style>
    .staff-phone-group {
      display: flex;
      align-items: stretch;
      gap: 0;
    }
    .staff-phone-group .select2-container {
      width: 118px !important;
      flex: 0 0 118px;
    }
    .staff-phone-group .select2-container .select2-selection--single {
      height: 38px;
      border-radius: 4px 0 0 4px;
      border-right: 0;
      display: flex;
      align-items: center;
    }
    .staff-phone-group .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px;
      padding-left: 8px;
      padding-right: 24px;
    }
    .staff-phone-group .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
    }
    .staff-phone-group #staff_phone {
      border-radius: 0 4px 4px 0;
      flex: 1 1 auto;
    }
    .select2-flag { margin-right: 6px; }
    .staff-password-strength__bar {
      display: flex;
      gap: 4px;
      height: 4px;
    }
    .staff-password-strength__bar span {
      flex: 1;
      background: #e0e0e0;
      border-radius: 2px;
      transition: background 0.2s;
    }
    .staff-password-strength.is-weak .staff-password-strength__bar span:nth-child(1) { background: #dc3545; }
    .staff-password-strength.is-fair .staff-password-strength__bar span:nth-child(-n+2) { background: #fd7e14; }
    .staff-password-strength.is-good .staff-password-strength__bar span:nth-child(-n+3) { background: #ffc107; }
    .staff-password-strength.is-strong .staff-password-strength__bar span { background: #28a745; }
    .staff-email-autocomplete { position: relative; }
    .staff-email-suggestions-list {
      position: absolute;
      z-index: 1050;
      left: 0;
      right: 0;
      top: 100%;
      margin: 2px 0 0;
      padding: 0;
      list-style: none;
      background: #fff;
      border: 1px solid rgba(0,0,0,.15);
      border-radius: 4px;
      box-shadow: 0 4px 12px rgba(0,0,0,.12);
      max-height: 220px;
      overflow-y: auto;
    }
    .staff-email-suggestions-list li {
      padding: 8px 12px;
      cursor: pointer;
      font-size: 14px;
    }
    .staff-email-suggestions-list li:hover,
    .staff-email-suggestions-list li.is-active {
      background: rgba(148, 0, 0, 0.08);
      color: #940000;
    }
    .staff-attachments-panel {
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 4px;
      padding: 12px 14px 4px;
      background: #fafafa;
    }
    .staff-doc-upload__current {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      background: rgba(40, 167, 69, 0.08);
      border: 1px solid rgba(40, 167, 69, 0.25);
      border-radius: 4px;
    }
    .staff-doc-upload__current-icon { color: #28a745; font-size: 18px; }
    .staff-doc-upload__current-body { flex: 1; min-width: 0; }
    .staff-doc-upload__current-label {
      display: block;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #666;
      font-weight: 600;
    }
    .staff-doc-upload__current-name {
      display: block;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: #940000;
    }
    .staff-doc-upload__zone {
      border: 2px dashed rgba(148, 0, 0, 0.25);
      border-radius: 6px;
      padding: 18px 14px;
      text-align: center;
      background: #fff;
      cursor: pointer;
      transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
    }
    .staff-doc-upload__zone:hover,
    .staff-doc-upload__zone:focus {
      outline: none;
      border-color: rgba(148, 0, 0, 0.55);
      background: rgba(148, 0, 0, 0.03);
    }
    .staff-doc-upload__zone.is-dragover {
      border-color: #940000;
      background: rgba(148, 0, 0, 0.06);
      box-shadow: 0 0 0 3px rgba(148, 0, 0, 0.12);
    }
    .staff-doc-upload__zone.has-file {
      border-style: solid;
      border-color: rgba(40, 167, 69, 0.45);
      padding: 12px 14px;
      text-align: left;
    }
    .staff-doc-upload__zone.has-error {
      border-color: #dc3545;
      background: rgba(220, 53, 69, 0.04);
    }
    .staff-doc-upload__icon-wrap {
      width: 48px;
      height: 48px;
      margin: 0 auto 10px;
      border-radius: 50%;
      background: rgba(148, 0, 0, 0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #940000;
      font-size: 22px;
    }
    .staff-doc-upload__title { font-weight: 600; font-size: 14px; margin: 0; color: #333; }
    .staff-doc-upload__browse { color: #940000; text-decoration: underline; }
    .staff-doc-upload__hint { font-size: 12px; color: #888; }
    .staff-doc-upload__selected {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .staff-doc-upload__thumb {
      width: 52px;
      height: 52px;
      flex-shrink: 0;
      border-radius: 4px;
      border: 1px solid rgba(0,0,0,.1);
      background: #f5f5f5;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      font-size: 26px;
      color: #c0392b;
    }
    .staff-doc-upload__thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .staff-doc-upload__meta {
      flex: 1;
      min-width: 120px;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .staff-doc-upload__filename { font-weight: 600; font-size: 13px; word-break: break-all; }
    .staff-doc-upload__filesize { font-size: 12px; }
    .staff-doc-upload__status { font-size: 12px; font-weight: 600; }
    .staff-doc-upload__error {
      margin-top: 10px;
      padding: 8px 10px;
      background: rgba(220, 53, 69, 0.1);
      border-radius: 4px;
      color: #dc3545;
      font-size: 13px;
      text-align: left;
    }
    .staff-doc-form-group .badge { font-size: 11px; font-weight: 600; }
  </style>
@endpush

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script type="module">
    import { Country } from 'https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm';

    const oldPhoneCode = @json($phoneCode);
    const staffEmailPresets = @json(array_values($emailDatalistOptions));
    const staffEmailDomains = @json(array_values($emailDomains));

    jQuery(function ($) {
      const countries = Country.getAllCountries().sort((a, b) => a.name.localeCompare(b.name));
      const $phoneCodeSelect = $('#staff_phone_code');
      const $phoneCountryCode = $('#staff_phone_country_code');

      countries.forEach(function (country) {
        const phone = '+' + country.phonecode;
        const phoneSelected = oldPhoneCode === phone ? ' selected' : '';
        $phoneCodeSelect.append(
          '<option value="' + phone + '" data-flag="' + country.flag + '" data-iso="' + country.isoCode + '"' + phoneSelected + '>' + country.flag + ' ' + phone + '</option>'
        );
      });

      function formatPhoneCode(option) {
        if (!option.id) return option.text;
        const flag = $(option.element).data('flag') || '';
        return $('<span><span class="select2-flag">' + flag + '</span> ' + option.id + '</span>');
      }

      $phoneCodeSelect.select2({
        width: '118px',
        minimumResultsForSearch: 6,
        templateResult: formatPhoneCode,
        templateSelection: formatPhoneCode,
      });

      $phoneCodeSelect.on('change', function () {
        $phoneCountryCode.val($(this).val() || '');
      });
      $phoneCountryCode.val($phoneCodeSelect.val() || oldPhoneCode);

      const $pwd = $('#staff_password');
      const $pwdConfirm = $('#staff_password_confirm');
      const $strength = $('#staff_password_strength');
      const $strengthLabel = $('#staff_password_strength_label');
      const minLen = parseInt($pwd.data('min-length'), 10) || 8;
      const requireStrong = String($pwd.data('require-strong')) === '1';

      function scorePassword(value) {
        if (!value) return { score: 0, label: '' };
        let score = 0;
        if (value.length >= minLen) score++;
        if (value.length >= minLen + 4) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        const capped = Math.min(4, Math.max(1, Math.ceil(score * 4 / 5)));
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        return { score: capped, label: labels[capped] };
      }

      function updateStrength() {
        const value = $pwd.val();
        $strength.removeClass('is-weak is-fair is-good is-strong');
        if (!value) {
          $strengthLabel.text('');
          return;
        }
        const result = scorePassword(value);
        const classes = ['', 'is-weak', 'is-fair', 'is-good', 'is-strong'];
        $strength.addClass(classes[result.score] || '');
        $strengthLabel.text(result.label);
      }

      $pwd.on('input', updateStrength);
      updateStrength();

      function bindToggle(btnSelector, input) {
        $(btnSelector).on('click', function () {
          const $input = $(input);
          const isPassword = $input.attr('type') === 'password';
          $input.attr('type', isPassword ? 'text' : 'password');
          $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
      }
      bindToggle('#staff_password_toggle', $pwd);
      bindToggle('#staff_password_confirm_toggle', $pwdConfirm);

      function randomPassword(length) {
        const lower = 'abcdefghjkmnpqrstuvwxyz';
        const upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        const digits = '23456789';
        const special = '!@#$%&*';
        const all = lower + upper + digits + special;
        let pwd = lower[Math.floor(Math.random() * lower.length)]
          + upper[Math.floor(Math.random() * upper.length)]
          + digits[Math.floor(Math.random() * digits.length)];
        if (requireStrong) {
          pwd += special[Math.floor(Math.random() * special.length)];
        }
        while (pwd.length < length) {
          pwd += all[Math.floor(Math.random() * all.length)];
        }
        return pwd.split('').sort(() => Math.random() - 0.5).join('');
      }

      $('#staff_password_generate').on('click', function () {
        const len = Math.max(minLen, requireStrong ? 12 : 10);
        const generated = randomPassword(len);
        $pwd.val(generated).attr('type', 'text');
        $pwdConfirm.val(generated).attr('type', 'text');
        $('#staff_password_toggle_icon').removeClass('fa-eye').addClass('fa-eye-slash');
        updateStrength();
      });

      const $emailInput = $('#staff_email');
      const $emailList = $('#staff_email_suggestions');
      let emailActiveIndex = -1;

      function buildEmailSuggestions(query) {
        const q = (query || '').trim().toLowerCase();
        if (!q) return staffEmailPresets.slice(0, 8);

        const out = [];
        const seen = new Set();

        function push(email) {
          const key = email.toLowerCase();
          if (seen.has(key)) return;
          seen.add(key);
          out.push(email);
        }

        staffEmailPresets.forEach(function (email) {
          if (email.toLowerCase().includes(q)) push(email);
        });

        if (q.indexOf('@') === -1) {
          staffEmailDomains.forEach(function (domain) {
            push(q + '@' + domain);
          });
        } else {
          const parts = q.split('@');
          const local = parts[0];
          const frag = parts[1] || '';
          staffEmailDomains.forEach(function (domain) {
            if (!frag || domain.toLowerCase().indexOf(frag) === 0) {
              push(local + '@' + domain);
            }
          });
        }

        return out.slice(0, 8);
      }

      function renderEmailSuggestions(items) {
        $emailList.empty();
        emailActiveIndex = -1;
        if (!items.length) {
          $emailList.prop('hidden', true);
          $emailInput.attr('aria-expanded', 'false');
          return;
        }
        items.forEach(function (email, index) {
          $emailList.append(
            $('<li role="option" tabindex="-1"></li>').text(email).data('index', index)
          );
        });
        $emailList.prop('hidden', false);
        $emailInput.attr('aria-expanded', 'true');
      }

      function pickEmailSuggestion(email) {
        $emailInput.val(email);
        $emailList.prop('hidden', true).empty();
        $emailInput.attr('aria-expanded', 'false');
      }

      $emailInput.on('input focus', function () {
        renderEmailSuggestions(buildEmailSuggestions($emailInput.val()));
      });

      $emailList.on('click', 'li', function () {
        pickEmailSuggestion($(this).text());
      });

      $emailInput.on('keydown', function (e) {
        const $items = $emailList.find('li');
        if ($emailList.prop('hidden') || !$items.length) return;

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          emailActiveIndex = Math.min(emailActiveIndex + 1, $items.length - 1);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          emailActiveIndex = Math.max(emailActiveIndex - 1, 0);
        } else if (e.key === 'Enter' && emailActiveIndex >= 0) {
          e.preventDefault();
          pickEmailSuggestion($($items.get(emailActiveIndex)).text());
          return;
        } else if (e.key === 'Escape') {
          $emailList.prop('hidden', true);
          $emailInput.attr('aria-expanded', 'false');
          return;
        } else {
          return;
        }

        $items.removeClass('is-active');
        if (emailActiveIndex >= 0) {
          $($items.get(emailActiveIndex)).addClass('is-active');
        }
      });

      $(document).on('click', function (e) {
        if (!$(e.target).closest('.staff-email-autocomplete').length) {
          $emailList.prop('hidden', true);
          $emailInput.attr('aria-expanded', 'false');
        }
      });

      const $attachmentsToggle = $('#staff_attachments_open');
      const $attachmentsPanel = $('#staff_attachments_panel');

      function syncAttachmentsPanel() {
        if ($attachmentsToggle.length && $attachmentsPanel.length) {
          if ($attachmentsToggle.is(':checked')) {
            $attachmentsPanel.prop('hidden', false);
          } else {
            $attachmentsPanel.prop('hidden', true);
          }
        }
      }

      $attachmentsToggle.on('change', syncAttachmentsPanel);
      syncAttachmentsPanel();

      function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
      }

      function isAllowedStaffDoc(file) {
        const allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        return allowed.indexOf(file.type) !== -1 || ['jpg', 'jpeg', 'png', 'pdf'].indexOf(ext) !== -1;
      }

      $('.staff-doc-upload').each(function () {
        const $wrap = $(this);
        const maxBytes = parseInt($wrap.data('max-bytes'), 10) || 10485760;
        const $input = $wrap.find('.js-staff-doc-input');
        const $zone = $wrap.find('.js-staff-doc-zone');
        const $empty = $wrap.find('.js-staff-doc-empty');
        const $selected = $wrap.find('.js-staff-doc-selected');
        const $error = $wrap.find('.js-staff-doc-error');
        const $errorText = $wrap.find('.js-staff-doc-error-text');
        const $filename = $wrap.find('.js-staff-doc-filename');
        const $filesize = $wrap.find('.js-staff-doc-filesize');
        const $thumbImg = $wrap.find('.js-staff-doc-thumb-img');
        const $thumbIcon = $wrap.find('.js-staff-doc-thumb-icon');
        let previewUrl = null;

        function clearPreviewUrl() {
          if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
          }
        }

        function showError(message) {
          $zone.addClass('has-error');
          $errorText.text(message);
          $error.removeClass('d-none');
        }

        function clearError() {
          $zone.removeClass('has-error');
          $error.addClass('d-none');
          $errorText.text('');
        }

        function resetSelection() {
          clearPreviewUrl();
          $input.val('');
          $zone.removeClass('has-file');
          $empty.removeClass('d-none');
          $selected.addClass('d-none');
          clearError();
        }

        function applyFile(file) {
          clearError();
          if (!file) {
            resetSelection();
            return;
          }
          if (!isAllowedStaffDoc(file)) {
            resetSelection();
            showError('Please choose a PDF, JPG, or PNG file.');
            return;
          }
          if (file.size > maxBytes) {
            resetSelection();
            showError('File is too large. Maximum size is 10 MB.');
            return;
          }

          $filename.text(file.name);
          $filesize.text(formatFileSize(file.size));
          $empty.addClass('d-none');
          $selected.removeClass('d-none');
          $zone.addClass('has-file');

          clearPreviewUrl();
          $thumbImg.addClass('d-none');
          $thumbIcon.removeClass('d-none fa-file-image-o fa-file-pdf-o');

          if (file.type.indexOf('image/') === 0 || /\.(jpe?g|png)$/i.test(file.name)) {
            previewUrl = URL.createObjectURL(file);
            $thumbImg.attr('src', previewUrl).removeClass('d-none');
            $thumbIcon.addClass('d-none');
          } else {
            $thumbIcon.addClass('fa-file-pdf-o');
          }
        }

        function openPicker() {
          $input.trigger('click');
        }

        $zone.on('click', function (e) {
          if ($(e.target).closest('.js-staff-doc-clear').length) return;
          openPicker();
        });

        $zone.on('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openPicker();
          }
        });

        $input.on('change', function () {
          const file = this.files && this.files[0] ? this.files[0] : null;
          applyFile(file);
        });

        $wrap.on('click', '.js-staff-doc-clear', function (e) {
          e.preventDefault();
          e.stopPropagation();
          resetSelection();
        });

        $zone.on('dragenter dragover', function (e) {
          e.preventDefault();
          e.stopPropagation();
          $zone.addClass('is-dragover');
        });

        $zone.on('dragleave drop', function (e) {
          e.preventDefault();
          e.stopPropagation();
          $zone.removeClass('is-dragover');
        });

        $zone.on('drop', function (e) {
          const dt = e.originalEvent.dataTransfer;
          if (!dt || !dt.files || !dt.files.length) return;
          const file = dt.files[0];
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          $input[0].files = dataTransfer.files;
          applyFile(file);
        });
      });
    });
  </script>
@endpush
