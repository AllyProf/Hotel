@php
  $defaultCurrency = $defaultCurrency ?? $service->displayCurrency($hotel);
  $categories = $service->serviceCategories->where('is_active', true);
  $packages = $service->servicePackages->where('is_active', true);
  $currencies = config('currencies', []);
  $itemNames = collect($service->menuItemSettings()['items'] ?? [])->pluck('name')->filter()->values();
  $defaultPackageId = app(\App\Services\PmsServiceItemDataService::class)->defaultPackageId($service);
  $sectionTitle = $service->isBar() ? 'Register bar items' : 'Register menu items';
  $datalistId = 'svCatalogItemNames';
  $formAction = $formAction ?? route('hotel.bar.items.update', $service);
  $hideStepBanner = $hideStepBanner ?? true;
@endphp

<div class="sv-section {{ ($hideStepBanner ?? true) ? 'border-top' : '' }}" id="menu-items" style="padding: 20px;">
  @if(empty($hideStepBanner))
    <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:13px;">
      <strong>Start here.</strong> Register each product with price, category, and unit (glass, bottle, etc.).
      Use <strong>Categories &amp; units</strong> above to import Beers, Wines, Glass, Bottle, and similar templates.
    </div>
  @endif

  @if($hideStepBanner)
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
    <h4 class="sv-section-title mb-0"><i class="fa fa-glass"></i> {{ $sectionTitle }}</h4>
  </div>
  @endif

  @if($packages->isEmpty())
    <p class="text-muted small mb-2">
      No <strong>units</strong> yet — scroll to <strong>Packages</strong> below and click <strong>Import Packages</strong> (e.g. Glass, Bottle).
    </p>
  @endif
  @if($categories->isEmpty())
    <p class="text-muted small mb-2">
      No <strong>categories</strong> yet — import categories (Beers, Wines, etc.) below or pick a category after import.
    </p>
  @endif

  <form method="POST" action="{{ $formAction }}" id="svCatalogItemsForm">
    @csrf
    @method('PUT')

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <div class="d-flex align-items-center gap-2">
        <label class="control-label mb-0 small text-muted">Default currency for new rows</label>
        <select class="form-control form-control-sm js-sv-catalog-default-currency" style="width:220px;">
          @foreach($currencies as $code => $label)
            <option value="{{ $code }}" {{ $defaultCurrency === $code ? 'selected' : '' }}>{{ $code }} — {{ $label }}</option>
          @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary js-sv-catalog-apply-currency" title="Set every row to this currency">
          Apply to all rows
        </button>
      </div>
      <button type="button" class="btn btn-sm btn-light border js-sv-catalog-add-item">
        <i class="fa fa-plus"></i> Add item
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0">
        <thead>
          <tr>
            <th>Item name</th>
            <th>Category</th>
            <th>Unit</th>
            <th style="width:100px;">Currency</th>
            <th style="width:120px;">Price</th>
            <th style="width:56px;">On</th>
            <th style="width:44px;"></th>
          </tr>
        </thead>
        <tbody class="js-sv-catalog-items-body">
          @forelse($catalogItemRows as $i => $item)
            @include('hotel.pms.services.partials._catalog-item-row', [
              'index' => $i,
              'item' => $item,
              'categories' => $categories,
              'packages' => $packages,
              'currencies' => $currencies,
              'defaultCurrency' => $item->currency ?: $defaultCurrency,
              'defaultPackageId' => $defaultPackageId,
              'formPrefix' => 'items',
              'datalistId' => $datalistId,
            ])
          @empty
            <tr class="js-sv-catalog-items-empty">
              <td colspan="7" class="text-muted text-center py-3">No items yet. Click <strong>Add item</strong> to register your bar menu.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;">
        <i class="fa fa-save"></i> Save items
      </button>
    </div>
  </form>

  @if($itemNames->isNotEmpty())
    <datalist id="{{ $datalistId }}">
      @foreach($itemNames as $name)
        <option value="{{ $name }}"></option>
      @endforeach
    </datalist>
  @endif
</div>

<template id="svCatalogItemRowTemplate">
  @include('hotel.pms.services.partials._catalog-item-row', [
    'index' => '__INDEX__',
    'item' => null,
    'categories' => $categories,
    'packages' => $packages,
    'currencies' => $currencies,
    'defaultCurrency' => $defaultCurrency,
    'defaultPackageId' => $defaultPackageId,
    'formPrefix' => 'items',
    'datalistId' => $datalistId,
    'isTemplate' => true,
  ])
</template>

@push('scripts')
  <script>
    (function () {
      var tbody = document.querySelector('.js-sv-catalog-items-body');
      var template = document.getElementById('svCatalogItemRowTemplate');
      var addBtn = document.querySelector('.js-sv-catalog-add-item');
      var defaultCurrencySelect = document.querySelector('.js-sv-catalog-default-currency');
      var nextIndex = {{ max($catalogItemRows->count(), 0) }};

      function reindexRows() {
        if (!tbody) return;
        tbody.querySelectorAll('.js-sv-catalog-item-row').forEach(function (row, index) {
          row.querySelectorAll('[data-field]').forEach(function (el) {
            var field = el.getAttribute('data-field');
            if (field) el.name = 'items[' + index + '][' + field + ']';
          });
        });
      }

      function applyDefaultCurrencyToRow(row) {
        if (!defaultCurrencySelect || !row) return;
        var sel = row.querySelector('.js-sv-catalog-row-currency');
        if (sel && !sel.dataset.userChanged) {
          sel.value = defaultCurrencySelect.value;
        }
      }

      var applyCurrencyBtn = document.querySelector('.js-sv-catalog-apply-currency');

      if (defaultCurrencySelect) {
        defaultCurrencySelect.addEventListener('change', function () {
          tbody.querySelectorAll('.js-sv-catalog-item-row').forEach(function (row) {
            var sel = row.querySelector('.js-sv-catalog-row-currency');
            if (sel && !sel.dataset.userChanged) sel.value = defaultCurrencySelect.value;
          });
        });
      }

      if (applyCurrencyBtn && defaultCurrencySelect && tbody) {
        applyCurrencyBtn.addEventListener('click', function () {
          tbody.querySelectorAll('.js-sv-catalog-row-currency').forEach(function (sel) {
            sel.value = defaultCurrencySelect.value;
            sel.dataset.userChanged = '1';
          });
        });
      }

      if (addBtn && template && tbody) {
        addBtn.addEventListener('click', function () {
          var empty = tbody.querySelector('.js-sv-catalog-items-empty');
          if (empty) empty.remove();

          var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
          var wrap = document.createElement('tbody');
          wrap.innerHTML = html.trim();
          var row = wrap.firstElementChild;
          tbody.appendChild(row);
          applyDefaultCurrencyToRow(row);
          reindexRows();

          var cur = row.querySelector('.js-sv-catalog-row-currency');
          if (cur) {
            cur.addEventListener('change', function () { cur.dataset.userChanged = '1'; });
          }
        });
      }

      if (tbody) {
        tbody.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-sv-catalog-remove-item');
          if (!btn) return;
          btn.closest('.js-sv-catalog-item-row').remove();
          reindexRows();
          if (!tbody.querySelector('.js-sv-catalog-item-row')) {
            var tr = document.createElement('tr');
            tr.className = 'js-sv-catalog-items-empty';
            tr.innerHTML = '<td colspan="7" class="text-muted text-center py-3">No items yet. Click <strong>Add item</strong> to register your bar menu.</td>';
            tbody.appendChild(tr);
          }
        });

        tbody.querySelectorAll('.js-sv-catalog-row-currency').forEach(function (sel) {
          sel.addEventListener('change', function () { sel.dataset.userChanged = '1'; });
        });
      }
    })();
  </script>
@endpush
