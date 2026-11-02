@php
  $defaultCurrency = $defaultLaundryCurrency ?? $service->displayCurrency($hotel);
  $categories = $service->serviceCategories->where('is_active', true);
  $packages = $service->servicePackages->where('is_active', true);
  $currencies = config('currencies', []);
  $garmentNames = collect($service->laundrySettings()['items'] ?? [])->pluck('name')->filter()->values();
  $defaultPackageId = $packages->firstWhere('abbreviation', 'pc')?->id
    ?? $packages->firstWhere('abbreviation', 'piece')?->id
    ?? $packages->first()?->id;
@endphp

<div class="sv-section border-top" id="laundry-prices">
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
    <h4 class="sv-section-title mb-0"><i class="fa fa-tags"></i> Item Prices</h4>
    <a href="{{ route('hotel.laundry.index') }}" class="btn btn-sv" style="width:auto;padding:6px 16px;">
      <i class="fa fa-external-link"></i> Laundry Desk
    </a>
  </div>

  @if($packages->isEmpty())
    <p class="text-muted small mb-2">
      No <strong>units</strong> yet — scroll to <strong>Packages</strong> below and click <strong>Import Packages</strong> (e.g. Piece, Kilogram, Bag).
    </p>
  @endif
  @if($categories->isEmpty())
    <p class="text-muted small mb-2">
      No <strong>categories</strong> yet — import categories (Wash &amp; Fold, Dry Cleaning, etc.) below.
    </p>
  @endif

  <form method="POST" action="{{ route('hotel.services.laundry-prices.update', $service) }}" id="svLaundryPricesForm">
    @csrf
    @method('PUT')

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <div class="d-flex align-items-center gap-2">
        <label class="control-label mb-0 small text-muted">Default currency for new rows</label>
        <select class="form-control form-control-sm js-sv-laundry-default-currency" style="width:220px;">
          @foreach($currencies as $code => $label)
            <option value="{{ $code }}" {{ $defaultCurrency === $code ? 'selected' : '' }}>{{ $code }} — {{ $label }}</option>
          @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary js-sv-laundry-apply-currency" title="Set every row to this currency">
          Apply to all rows
        </button>
      </div>
      <button type="button" class="btn btn-sm btn-light border js-sv-laundry-add-price">
        <i class="fa fa-plus"></i> Add item
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0">
        <thead>
          <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Unit</th>
            <th style="width:100px;">Currency</th>
            <th style="width:120px;">Price</th>
            <th style="width:56px;">On</th>
            <th style="width:44px;"></th>
          </tr>
        </thead>
        <tbody class="js-sv-laundry-prices-body">
          @forelse($laundryPriceRows as $i => $price)
            @include('hotel.pms.services.partials._laundry-price-row', [
              'index' => $i,
              'price' => $price,
              'categories' => $categories,
              'packages' => $packages,
              'currencies' => $currencies,
              'garmentNames' => $garmentNames,
              'defaultCurrency' => $price->currency ?: $defaultCurrency,
              'defaultPackageId' => $defaultPackageId,
            ])
          @empty
            <tr class="js-sv-laundry-prices-empty">
              <td colspan="7" class="text-muted text-center py-3">No items yet. Click <strong>Add item</strong>.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;">
        <i class="fa fa-save"></i> Save Prices
      </button>
    </div>
  </form>

  @if($garmentNames->isNotEmpty())
    <datalist id="svLaundryGarmentList">
      @foreach($garmentNames as $name)
        <option value="{{ $name }}"></option>
      @endforeach
    </datalist>
  @endif
</div>

<template id="svLaundryPriceRowTemplate">
  @include('hotel.pms.services.partials._laundry-price-row', [
    'index' => '__INDEX__',
    'price' => null,
    'categories' => $categories,
    'packages' => $packages,
    'currencies' => $currencies,
    'garmentNames' => $garmentNames,
    'defaultCurrency' => $defaultCurrency,
    'defaultPackageId' => $defaultPackageId,
    'isTemplate' => true,
  ])
</template>

@push('scripts')
  <script>
    (function () {
      var tbody = document.querySelector('.js-sv-laundry-prices-body');
      var template = document.getElementById('svLaundryPriceRowTemplate');
      var addBtn = document.querySelector('.js-sv-laundry-add-price');
      var defaultCurrencySelect = document.querySelector('.js-sv-laundry-default-currency');
      var nextIndex = {{ max($laundryPriceRows->count(), 0) }};

      function reindexRows() {
        if (!tbody) return;
        tbody.querySelectorAll('.js-sv-laundry-price-row').forEach(function (row, index) {
          row.querySelectorAll('[data-field]').forEach(function (el) {
            var field = el.getAttribute('data-field');
            if (field) el.name = 'prices[' + index + '][' + field + ']';
          });
        });
      }

      function applyDefaultCurrencyToRow(row) {
        if (!defaultCurrencySelect || !row) return;
        var sel = row.querySelector('.js-sv-laundry-row-currency');
        if (sel && !sel.dataset.userChanged) {
          sel.value = defaultCurrencySelect.value;
        }
      }

      var applyCurrencyBtn = document.querySelector('.js-sv-laundry-apply-currency');

      if (defaultCurrencySelect) {
        defaultCurrencySelect.addEventListener('change', function () {
          tbody.querySelectorAll('.js-sv-laundry-price-row').forEach(function (row) {
            var sel = row.querySelector('.js-sv-laundry-row-currency');
            if (sel && !sel.dataset.userChanged) sel.value = defaultCurrencySelect.value;
          });
        });
      }

      if (applyCurrencyBtn && defaultCurrencySelect && tbody) {
        applyCurrencyBtn.addEventListener('click', function () {
          tbody.querySelectorAll('.js-sv-laundry-row-currency').forEach(function (sel) {
            sel.value = defaultCurrencySelect.value;
            sel.dataset.userChanged = '1';
          });
        });
      }

      if (addBtn && template && tbody) {
        addBtn.addEventListener('click', function () {
          var empty = tbody.querySelector('.js-sv-laundry-prices-empty');
          if (empty) empty.remove();

          var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
          var wrap = document.createElement('tbody');
          wrap.innerHTML = html.trim();
          var row = wrap.firstElementChild;
          tbody.appendChild(row);
          applyDefaultCurrencyToRow(row);
          reindexRows();

          var cur = row.querySelector('.js-sv-laundry-row-currency');
          if (cur) {
            cur.addEventListener('change', function () { cur.dataset.userChanged = '1'; });
          }
        });
      }

      if (tbody) {
        tbody.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-sv-laundry-remove-price');
          if (!btn) return;
          btn.closest('.js-sv-laundry-price-row').remove();
          reindexRows();
          if (!tbody.querySelector('.js-sv-laundry-price-row')) {
            var tr = document.createElement('tr');
            tr.className = 'js-sv-laundry-prices-empty';
            tr.innerHTML = '<td colspan="7" class="text-muted text-center py-3">No items yet. Click <strong>Add item</strong>.</td>';
            tbody.appendChild(tr);
          }
        });

        tbody.querySelectorAll('.js-sv-laundry-row-currency').forEach(function (sel) {
          sel.addEventListener('change', function () { sel.dataset.userChanged = '1'; });
        });
      }
    })();
  </script>
@endpush
