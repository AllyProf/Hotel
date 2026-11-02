@php
  $tanzaniaRegions = config('tanzania_regions', []);
  $vendorPhoneCode = old('phone_country_code', $hotel->phone_country_code ?? '+255');
  $vendorEmailDomains = $vendorEmailDomains ?? [];
  $vendorEmailPresets = $vendorEmailPresets ?? [];
  $vendorPhoneOptions = config('hotel_stores.vendor_phone_codes', []);
@endphp

<div class="modal fade" id="storesAddVendorModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.stores.vendors.store') }}" id="storesVendorForm" data-no-loader>
        @csrf
        <input type="hidden" name="_method" id="storesVendorMethod" value="">
        <input type="hidden" name="stores_vendor_edit_id" id="storesVendorEditId" value="{{ old('stores_vendor_edit_id') }}">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesVendorModalTitle">{{ $ui['add_vendor'] ?? 'Add Vendor' }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0">
          <div class="stores-modal-divider"></div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Vendor Name</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name"
                  value="{{ old('name') }}" required maxlength="160">
                @error('name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group">
                <label class="control-label">TIN Number</label>
                <input type="text" class="form-control @error('gst_num') is-invalid @enderror" name="gst_num"
                  value="{{ old('gst_num') }}" maxlength="80" placeholder="Tax identification number">
                @error('gst_num')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group">
                <label class="control-label">Address</label>
                <textarea class="form-control @error('address') is-invalid @enderror" name="address" rows="3" maxlength="500">{{ old('address') }}</textarea>
                @error('address')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group stores-vendor-email-wrap">
                <label class="control-label">Email</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="storesVendorEmail"
                  value="{{ old('email') }}" maxlength="160" autocomplete="off" spellcheck="false"
                  aria-autocomplete="list" aria-controls="storesVendorEmailSuggestions" aria-expanded="false">
                <ul class="stores-vendor-email-suggestions" id="storesVendorEmailSuggestions" role="listbox" hidden></ul>
                @error('email')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group mb-0">
                <label class="control-label">Account Number</label>
                <input type="text" class="form-control @error('account_no') is-invalid @enderror" name="account_no"
                  value="{{ old('account_no') }}" maxlength="64" inputmode="numeric">
                @error('account_no')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Contact Person</label>
                <input type="text" class="form-control @error('contact_person') is-invalid @enderror" name="contact_person"
                  value="{{ old('contact_person') }}" maxlength="120">
                @error('contact_person')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group">
                <label class="control-label">Phone</label>
                <div class="stores-vendor-phone-group">
                  <select id="storesVendorPhoneCode" class="form-control stores-vendor-phone-code" aria-label="Country calling code">
                    @foreach($vendorPhoneOptions as $opt)
                      <option value="{{ $opt['code'] }}" @selected($vendorPhoneCode === $opt['code'])>{{ $opt['flag'] }} {{ $opt['code'] }}</option>
                    @endforeach
                  </select>
                  <input type="hidden" name="phone_country_code" id="storesVendorPhoneCountryCode" value="{{ $vendorPhoneCode }}">
                  <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="storesVendorPhone"
                    value="{{ old('phone') }}" inputmode="tel" maxlength="40" placeholder="Local number">
                </div>
                @error('phone')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                @error('phone_country_code')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group">
                <label class="control-label">Region</label>
                <select class="form-control @error('state') is-invalid @enderror" name="state">
                  <option value="">— Select region —</option>
                  @foreach($tanzaniaRegions as $region)
                    <option value="{{ $region }}" @selected(old('state') === $region)>{{ $region }}</option>
                  @endforeach
                </select>
                @error('state')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
              <div class="form-group mb-0">
                <label class="control-label">Bank Name</label>
                <input type="text" class="form-control @error('bank_name') is-invalid @enderror" name="bank_name"
                  value="{{ old('bank_name') }}" maxlength="160">
                @error('bank_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-stores-outline">{{ $ui['submit'] ?? 'Submit' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@once
  @push('styles')
    <style>
      .stores-vendor-phone-group {
        display: flex;
        align-items: stretch;
      }
      .stores-vendor-phone-group .stores-vendor-phone-code {
        flex: 0 0 118px;
        max-width: 118px;
        border-radius: 4px 0 0 4px;
        border-right: 0;
        padding-left: 6px;
        padding-right: 4px;
        font-size: 13px;
      }
      .stores-vendor-phone-group input[name="phone"] {
        border-radius: 0 4px 4px 0;
        flex: 1 1 auto;
      }
      .stores-vendor-email-wrap {
        position: relative;
      }
      .stores-vendor-email-suggestions {
        position: absolute;
        z-index: 1060;
        left: 0;
        right: 0;
        top: 100%;
        margin: 2px 0 0;
        padding: 0;
        list-style: none;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        max-height: 220px;
        overflow-y: auto;
      }
      .stores-vendor-email-suggestions li {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 14px;
      }
      .stores-vendor-email-suggestions li:hover,
      .stores-vendor-email-suggestions li.is-active {
        background: var(--stores-brand-soft, rgba(148, 0, 0, 0.08));
        color: var(--stores-brand, #940000);
      }
    </style>
  @endpush
  @push('scripts')
    <script>
      (function () {
        const vendorEmailPresets = @json(array_values($vendorEmailPresets));
        const vendorEmailDomains = @json(array_values($vendorEmailDomains));

        jQuery(function ($) {
          if (typeof window.cghAdminHideLoader === 'function') {
            window.cghAdminHideLoader();
          }

          const $phoneCodeSelect = $('#storesVendorPhoneCode');
          const $phoneCountryCode = $('#storesVendorPhoneCountryCode');

          $phoneCodeSelect.on('change', function () {
            $phoneCountryCode.val($(this).val() || '');
          });
          $phoneCountryCode.val($phoneCodeSelect.val() || @json($vendorPhoneCode));

          const $emailInput = $('#storesVendorEmail');
        const $emailList = $('#storesVendorEmailSuggestions');
        let emailActiveIndex = -1;

        function buildEmailSuggestions(query) {
          const q = (query || '').trim().toLowerCase();
          if (!q) return vendorEmailPresets.slice(0, 8);

          const out = [];
          const seen = new Set();

          function push(email) {
            const key = email.toLowerCase();
            if (seen.has(key)) return;
            seen.add(key);
            out.push(email);
          }

          vendorEmailPresets.forEach(function (email) {
            if (email.toLowerCase().includes(q)) push(email);
          });

          if (q.indexOf('@') === -1) {
            vendorEmailDomains.forEach(function (domain) {
              push(q + '@' + domain);
            });
          } else {
            const parts = q.split('@');
            const local = parts[0];
            const frag = parts[1] || '';
            vendorEmailDomains.forEach(function (domain) {
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
            pickEmailSuggestion($items.eq(emailActiveIndex).text());
            return;
          } else if (e.key === 'Escape') {
            $emailList.prop('hidden', true);
            return;
          } else {
            return;
          }

          $items.removeClass('is-active');
          if (emailActiveIndex >= 0) {
            $items.eq(emailActiveIndex).addClass('is-active');
          }
        });

        $(document).on('click', function (e) {
          if (!$(e.target).closest('.stores-vendor-email-wrap').length) {
            $emailList.prop('hidden', true);
            $emailInput.attr('aria-expanded', 'false');
          }
        });
        });
      })();
    </script>
  @endpush
@endonce
