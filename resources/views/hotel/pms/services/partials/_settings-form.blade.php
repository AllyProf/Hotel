@php
  $serviceCurrency = old('currency', $service->displayCurrency($hotel));
  $vatLabel = 'VAT '.number_format($vatRate ?? 18, $vatRate == (int) $vatRate ? 0 : 2).'%';
  $currentTax = old('tax_category', $service->tax_category ?? 'No Tax');
  $taxMode = old('tax_mode');
  if ($taxMode === null) {
      if (strcasecmp($currentTax, 'No Tax') === 0) {
          $taxMode = 'no_tax';
      } elseif (stripos($currentTax, 'vat') === 0) {
          $taxMode = 'vat';
      } else {
          $taxMode = 'custom';
      }
  }
  $showTaxInclusive = $taxMode !== 'no_tax';
@endphp

<div class="sv-section {{ $service->showsCatalog() ? 'border-bottom' : '' }}" id="settings">
  <form method="POST" action="{{ route('hotel.services.settings.update', $service) }}" class="sv-settings-form">
    @csrf
    @method('PUT')

    @if($service->isLaundry())
      <p class="text-muted small mb-3">
        Laundry is priced per item in <strong>Item Prices</strong> below (each row has its own currency and unit).
        Set a <strong>default currency</strong> here for new price rows and tickets; change hotel-wide currency under Settings → Hotel if needed.
      </p>
      <input type="hidden" name="amount" value="0">
      <input type="hidden" name="pricing_unit" value="{{ old('pricing_unit', $service->pricing_unit ?: 'per_use') }}">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="control-label">Default currency</label>
            @include('partials._currency-select', [
              'name' => 'currency',
              'selected' => $serviceCurrency,
              'required' => true,
            ])
            @error('currency')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="control-label">Tax</label>
            <select class="form-control js-sv-tax-mode" name="tax_mode" required>
              <option value="no_tax" {{ $taxMode === 'no_tax' ? 'selected' : '' }}>No Tax</option>
              <option value="vat" {{ $taxMode === 'vat' ? 'selected' : '' }}>{{ $vatLabel }}</option>
              <option value="custom" {{ $taxMode === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
            <input type="hidden" name="tax_category" class="js-sv-tax-category-value"
              value="{{ $taxMode === 'vat' ? $vatLabel : ($taxMode === 'custom' ? $currentTax : 'No Tax') }}">
            <div class="js-sv-tax-custom-wrap mt-2" style="{{ $taxMode === 'custom' ? '' : 'display:none;' }}">
              <input type="text" class="form-control js-sv-tax-custom-input" maxlength="60"
                value="{{ $taxMode === 'custom' ? $currentTax : '' }}" placeholder="Tax name">
            </div>
            @error('tax_category')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    @elseif($service->isConference())
      @php
        $confDefaultCurrency = in_array($serviceCurrency, ['TZS', 'USD'], true) ? $serviceCurrency : 'TZS';
      @endphp
      <p class="text-muted small mb-3">
        Event pricing is set in <strong>Conference rates &amp; packages</strong> below (per day, half-day, delegate, or fixed).
        Default currency applies to new rate rows.
      </p>
      <input type="hidden" name="amount" value="0">
      <input type="hidden" name="pricing_unit" value="per_day">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="control-label">Default currency (TZS / USD)</label>
            <select class="form-control" name="currency" required>
              <option value="TZS" {{ $confDefaultCurrency === 'TZS' ? 'selected' : '' }}>TZS — TZ Shilling</option>
              <option value="USD" {{ $confDefaultCurrency === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="control-label">Tax</label>
            <select class="form-control js-sv-tax-mode" name="tax_mode" required>
              <option value="no_tax" {{ $taxMode === 'no_tax' ? 'selected' : '' }}>No Tax</option>
              <option value="vat" {{ $taxMode === 'vat' ? 'selected' : '' }}>{{ $vatLabel }}</option>
              <option value="custom" {{ $taxMode === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
            <input type="hidden" name="tax_category" class="js-sv-tax-category-value"
              value="{{ $taxMode === 'vat' ? $vatLabel : ($taxMode === 'custom' ? $currentTax : 'No Tax') }}">
            <div class="js-sv-tax-custom-wrap mt-2" style="{{ $taxMode === 'custom' ? '' : 'display:none;' }}">
              <input type="text" class="form-control js-sv-tax-custom-input" maxlength="60"
                value="{{ $taxMode === 'custom' ? $currentTax : '' }}" placeholder="Tax name">
            </div>
          </div>
        </div>
      </div>
    @elseif($service->isSwimming())
      @php
        $swimDefaultCurrency = in_array($serviceCurrency, ['TZS', 'USD'], true) ? $serviceCurrency : 'TZS';
      @endphp
      <p class="text-muted small mb-3">
        Pool pricing is configured in <strong>Pool rate plans</strong> below (per person / hour / day, adult vs child, TZS or USD).
        Default currency here is used when new rate rows are created.
      </p>
      <input type="hidden" name="amount" value="0">
      <input type="hidden" name="pricing_unit" value="per_person">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="control-label">Default currency (TZS / USD)</label>
            <select class="form-control" name="currency" required>
              <option value="TZS" {{ $swimDefaultCurrency === 'TZS' ? 'selected' : '' }}>TZS — TZ Shilling</option>
              <option value="USD" {{ $swimDefaultCurrency === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
            </select>
            @error('currency')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="control-label">Tax</label>
            <select class="form-control js-sv-tax-mode" name="tax_mode" required>
              <option value="no_tax" {{ $taxMode === 'no_tax' ? 'selected' : '' }}>No Tax</option>
              <option value="vat" {{ $taxMode === 'vat' ? 'selected' : '' }}>{{ $vatLabel }}</option>
              <option value="custom" {{ $taxMode === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
            <input type="hidden" name="tax_category" class="js-sv-tax-category-value"
              value="{{ $taxMode === 'vat' ? $vatLabel : ($taxMode === 'custom' ? $currentTax : 'No Tax') }}">
            <div class="js-sv-tax-custom-wrap mt-2" style="{{ $taxMode === 'custom' ? '' : 'display:none;' }}">
              <input type="text" class="form-control js-sv-tax-custom-input" maxlength="60"
                value="{{ $taxMode === 'custom' ? $currentTax : '' }}" placeholder="Tax name">
            </div>
            @error('tax_category')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    @else
      <div class="row">
        <div class="col-md-3">
          <div class="form-group mb-3">
            <label class="control-label">Currency</label>
            @include('partials._currency-select', [
              'name' => 'currency',
              'selected' => $serviceCurrency,
              'required' => true,
            ])
            @error('currency')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group mb-3">
            <label class="control-label">Amount</label>
            <div class="input-group">
              <span class="input-group-text js-sv-currency-prefix">{{ $serviceCurrency }}</span>
              <input type="number" class="form-control" name="amount" step="0.01" min="0" required
                value="{{ old('amount', $service->amount) }}">
            </div>
            @error('amount')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group mb-3">
            <label class="control-label">Pricing Unit</label>
            <select class="form-control" name="pricing_unit" required>
              @foreach($pricingUnits as $key => $label)
                <option value="{{ $key }}" {{ old('pricing_unit', $service->pricing_unit) === $key ? 'selected' : '' }}>
                  {{ $label }}
                </option>
              @endforeach
            </select>
            @error('pricing_unit')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group mb-3">
            <label class="control-label">Tax</label>
            <select class="form-control js-sv-tax-mode" name="tax_mode" required>
              <option value="no_tax" {{ $taxMode === 'no_tax' ? 'selected' : '' }}>No Tax</option>
              <option value="vat" {{ $taxMode === 'vat' ? 'selected' : '' }}>{{ $vatLabel }}</option>
              <option value="custom" {{ $taxMode === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
            <input type="hidden" name="tax_category" class="js-sv-tax-category-value"
              value="{{ $taxMode === 'vat' ? $vatLabel : ($taxMode === 'custom' ? $currentTax : 'No Tax') }}">
            <div class="js-sv-tax-custom-wrap mt-2" style="{{ $taxMode === 'custom' ? '' : 'display:none;' }}">
              <input type="text" class="form-control js-sv-tax-custom-input" maxlength="60"
                value="{{ $taxMode === 'custom' ? $currentTax : '' }}" placeholder="Tax name">
            </div>
            @error('tax_category')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    @endif

    @if($service->isParking())
      @include('hotel.pms.services.partials._parking-settings')
    @endif

    @if($service->isLaundry())
      @include('hotel.pms.services.partials._laundry-settings')
    @endif

    @if($service->isSwimming())
      @include('hotel.pms.services.partials._swimming-settings')
    @endif

    @if($service->isConference())
      @include('hotel.pms.services.partials._conference-settings')
    @endif

    <div class="row align-items-center">
      <div class="col-md-8">
        <div class="sv-settings-toggles mb-0">
          <label class="animated-checkbox js-sv-tax-inclusive-wrap" style="{{ $showTaxInclusive ? '' : 'display:none;' }}">
            <input type="checkbox" name="tax_inclusive" value="1" class="js-sv-tax-inclusive"
              {{ old('tax_inclusive', $service->tax_inclusive && $showTaxInclusive) ? 'checked' : '' }}>
            <span class="label-text">Tax inclusive</span>
          </label>
          <label class="animated-checkbox">
            <input type="checkbox" name="visible_on_be" value="1"
              {{ old('visible_on_be', $service->visible_on_be) ? 'checked' : '' }}>
            <span class="label-text">Visible on booking engine</span>
          </label>
          @if(! $service->isLaundry())
            <label class="animated-checkbox">
              <input type="checkbox" name="amount_editable" value="1"
                {{ old('amount_editable', $service->amount_editable) ? 'checked' : '' }}>
              <span class="label-text">Editable at front desk</span>
            </label>
          @else
            <input type="hidden" name="amount_editable" value="1">
          @endif
          <label class="animated-checkbox">
            <input type="checkbox" name="is_active" value="1"
              {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
            <span class="label-text">Active</span>
          </label>
        </div>
      </div>
      <div class="col-md-4 text-md-right">
        <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;">
          <i class="fa fa-save"></i> Save
        </button>
      </div>
    </div>
  </form>
</div>

@push('scripts')
  <script>
    (function () {
      var form = document.querySelector('.sv-settings-form');
      if (!form) return;

      var taxMode = form.querySelector('.js-sv-tax-mode');
      var taxValue = form.querySelector('.js-sv-tax-category-value');
      var customWrap = form.querySelector('.js-sv-tax-custom-wrap');
      var customInput = form.querySelector('.js-sv-tax-custom-input');
      var inclusiveWrap = form.querySelector('.js-sv-tax-inclusive-wrap');
      var inclusiveInput = form.querySelector('.js-sv-tax-inclusive');
      var currencySelect = form.querySelector('[name="currency"]');
      var currencyPrefix = form.querySelector('.js-sv-currency-prefix');
      var vatLabel = @json($vatLabel);

      function syncTaxFields() {
        var mode = taxMode ? taxMode.value : 'no_tax';

        if (customWrap) {
          customWrap.style.display = mode === 'custom' ? '' : 'none';
        }

        if (mode === 'no_tax') {
          if (taxValue) taxValue.value = 'No Tax';
          if (inclusiveWrap) inclusiveWrap.style.display = 'none';
          if (inclusiveInput) inclusiveInput.checked = false;
        } else if (mode === 'vat') {
          if (taxValue) taxValue.value = vatLabel;
          if (inclusiveWrap) inclusiveWrap.style.display = '';
        } else {
          if (taxValue && customInput) taxValue.value = customInput.value.trim();
          if (inclusiveWrap) inclusiveWrap.style.display = '';
        }
      }

      if (taxMode) taxMode.addEventListener('change', syncTaxFields);

      if (customInput) {
        customInput.addEventListener('input', function () {
          if (taxMode && taxMode.value === 'custom' && taxValue) {
            taxValue.value = customInput.value.trim();
          }
        });
      }

      if (currencySelect && currencyPrefix) {
        var syncCurrency = function () {
          currencyPrefix.textContent = currencySelect.value || 'TZS';
        };
        currencySelect.addEventListener('change', syncCurrency);
        syncCurrency();
      }

      syncTaxFields();
    })();
  </script>
@endpush
