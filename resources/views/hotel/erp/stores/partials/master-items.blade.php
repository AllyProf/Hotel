@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $usageTypes = $usageTypes ?? config('hotel_stores.usage_types', []);
@endphp

<div class="stores-toolbar">
  <form method="GET" action="{{ route('hotel.stores.index') }}" id="storesMasterFilterForm" class="stores-toolbar-form stores-master-filter-form">
    <input type="hidden" name="tab" value="settings">
    <input type="hidden" name="settings" value="master-items">

    <div class="stores-toolbar-row">
      <div class="stores-filter-fields">
        <div class="stores-filter-field">
          <label class="control-label" for="storesFilterCategory">{{ $ui['department_store_label'] ?? 'Department' }}</label>
          <select name="category_id" id="storesFilterCategory" class="form-control" onchange="this.form.submit()">
            <option value="0">{{ $ui['all'] ?? 'All' }}</option>
            @foreach($masterData['categories'] as $cat)
              <option value="{{ $cat->id }}" @selected($filters['category_id'] === $cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="stores-toolbar-actions">
        <div class="stores-toolbar-action-item">
          <label class="control-label stores-toolbar-field-label">&nbsp;</label>
          <button type="button" class="btn btn-stores-outline btn-stores-add-item" data-toggle="modal" data-target="#storesItemModal"
            @if($masterData['categories']->isEmpty()) disabled title="Add departments under Settings → Department first" @endif>
            {{ $ui['add_item'] ?? 'Add Item' }}
          </button>
        </div>
        <div class="stores-search-field">
          <label class="control-label stores-search-label" for="storesFilterSearch">{{ $ui['search_placeholder'] ?? 'Search Item…' }}</label>
          <div class="input-group stores-search-input">
            <input type="search" name="search" id="storesFilterSearch" class="form-control"
              placeholder="{{ $ui['search_placeholder'] ?? 'Search Item…' }}" value="{{ $filters['search'] }}">
            <div class="input-group-append">
              <button type="submit" class="btn btn-light border" aria-label="Search"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="stores-total-value">
  <strong>{{ $ui['total_value'] ?? 'Total Value' }}:</strong>
  {{ $storeItemService->formatMoney((float) $masterData['total_value'], $hotel) }}
</div>

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        <th>Name</th>
        <th>{{ $ui['department_store_label'] ?? 'Department' }}</th>
        <th>{{ $ui['item_service_category'] ?? 'Category' }}</th>
        <th class="text-right">{{ $ui['item_price'] ?? 'Buying price' }}</th>
        <th>Usage</th>
        <th>Tax group</th>
        <th>Tax Inclusive</th>
        <th>Inventoried</th>
        <th>Capital Item</th>
        <th class="text-right">Stock</th>
        <th>Unit &amp; packaging</th>
        <th class="text-right">Value</th>
        <th style="width:72px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($masterData['items'] as $item)
        <tr>
          <td>{{ $item->name }}</td>
          <td>{{ $item->category?->name ?? '—' }}</td>
          <td>{{ $item->pmsServiceCategory?->name ?? '—' }}</td>
          <td class="text-right">{{ $storeItemService->formatMoney((float) $item->rate, $hotel) }}</td>
          <td>{{ $usageTypes[$item->usage_type] ?? $item->usage_type ?? '—' }}</td>
          <td>{{ $item->taxGroup?->name ?? $item->tax_group ?? '—' }}</td>
          <td>{{ $item->tax_inclusive ? 'Yes' : 'No' }}</td>
          <td>{{ $item->inventoried ? 'Yes' : 'No' }}</td>
          <td>{{ $item->capital_item ? 'Yes' : 'No' }}</td>
          <td class="text-right">{{ number_format((float) $item->stock, ((float) $item->stock == (int) $item->stock ? 0 : 2)) }}</td>
          <td>
            @if($item->storePackage)
              <span class="d-block">Recv: {{ $item->storePackage->displayLabel() }}
              @if((float) $item->pieces_per_receive_unit > 0 && (float) $item->pieces_per_receive_unit !== 1.0)
                <span class="text-muted small">· {{ rtrim(rtrim(number_format((float) $item->pieces_per_receive_unit, 2), '0'), '.') }} pcs/{{ $item->storePackage->unitLabel() }}</span>
              @endif
              </span>
            @else
              <span class="d-block">Recv: {{ $item->unit ?? '—' }}</span>
            @endif
            @if($item->sellOptions->isNotEmpty())
              @foreach($item->sellOptions as $opt)
                @php $uPkg = $opt->storePackage; @endphp
                <span class="d-block text-muted small">
                  {{ ($item->usage_type ?? '') === 'sale' ? 'Sold' : 'Use' }}:
                  {{ $uPkg?->displayLabel() ?? '—' }}
                  @if((float) $opt->pieces_per_sell_unit > 0 && (float) $opt->pieces_per_sell_unit !== 1.0)
                    · {{ rtrim(rtrim(number_format((float) $opt->pieces_per_sell_unit, 2), '0'), '.') }} pcs/{{ $uPkg?->unitLabel() ?? 'unit' }}
                  @endif
                </span>
              @endforeach
            @endif
          </td>
          <td class="text-right">{{ $storeItemService->formatMoney($item->lineValue(), $hotel) }}</td>
          <td class="text-nowrap">
            <button type="button" class="stores-icon-btn text-primary js-stores-edit-item" title="Edit"
              data-id="{{ $item->id }}"
              data-name="{{ e($item->name) }}"
              data-category-id="{{ $item->hotel_store_category_id }}"
              data-pms-category-id="{{ $item->hotel_pms_service_category_id ?? '' }}"
              data-vendor-id="{{ $item->hotel_vendor_id ?? '' }}"
              data-rate="{{ (float) $item->rate }}"
              data-usage="{{ $item->usage_type ?? 'internal' }}"
              data-tax-group-id="{{ $item->hotel_store_tax_group_id ?? '' }}"
              data-package-id="{{ $item->hotel_store_package_id ?? '' }}"
              data-pieces="{{ (float) ($item->pieces_per_receive_unit ?? 1) }}"
              data-tax-inclusive="{{ $item->tax_inclusive ? '1' : '0' }}"
              data-inventoried="{{ $item->inventoried ? '1' : '0' }}"
              data-capital="{{ $item->capital_item ? '1' : '0' }}"
              data-stock="{{ (float) $item->stock }}"
              data-sell-options="{{ e(json_encode($storeItemService->sellOptionRowsForForm($item))) }}">
              <i class="fa fa-pencil"></i>
            </button>
            <form method="POST" action="{{ route('hotel.stores.items.destroy', $item) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="stores-icon-btn text-danger" title="Delete"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="13" class="text-center text-muted py-4">
            @if($masterData['categories']->isEmpty())
              No departments yet. Open <strong>Settings → Department</strong>, link a PMS service, then add master items here.
            @else
              No items match your filters.
            @endif
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('hotel.erp.stores.partials.item-modal')

@php
  $storesDefaultPackageOptions = ($storePackages ?? collect())->map(fn ($p) => [
    'id' => $p->id,
    'label' => $p->displayLabel(),
    'unit_label' => $p->unitLabel(),
  ])->values();
@endphp

@push('scripts')
<script>
(function () {
  var catalogUrlTemplate = @json(url('/hotel/stores/categories/__ID__/catalog-options'));
  var sellPriceRecUrl = @json(route('hotel.stores.items.sell-price-recommendations'));
  var defaultPackages = @json($storesDefaultPackageOptions);
  var departmentCatalogRequest = null;
  var sellPriceRecRequest = null;
  var sellPriceRecTimer = null;
  window._storesSellPriceHints = { by_package: {}, options: [], source: null };

  function packageOptionsHtml(packages) {
    var html = '<option value="">Select unit</option>';
    (packages || []).forEach(function (pkg) {
      var label = (pkg.label || '').replace(/"/g, '&quot;');
      var unit = (pkg.unit_label || '').replace(/"/g, '&quot;');
      html += '<option value="' + pkg.id + '" data-unit-label="' + unit + '">' + label + '</option>';
    });
    return html;
  }

  function applyPackageOptions(packages, selectedReceiveId) {
    var html = packageOptionsHtml(packages);
    var $recv = $('#storesItemPackage');
    $recv.html(html);
    if (selectedReceiveId) {
      $recv.val(String(selectedReceiveId));
    }
    $('.js-stores-sell-options-body .js-stores-sell-package').each(function () {
      var $sel = $(this);
      var val = $sel.val();
      $sel.html(html);
      if (val) {
        $sel.val(val);
      }
    });
    var $template = $('.js-stores-sell-option-template');
    if ($template.length) {
      $template.find('.js-stores-sell-package').html(html);
    }
    updateStoresReceiveLabel();
    $('.js-stores-sell-options-body .js-stores-sell-option-row').each(function () {
      updateStoresSellRowHint($(this));
    });
  }

  function setItemCategoryField(mode, categories, selectedId) {
    var $wrap = $('.js-stores-item-category-wrap');
    var $sel = $('#storesItemPmsCategory');
    var $hint = $('.js-stores-item-category-hint');
    var $req = $('.js-stores-item-category-required');

    if (mode === 'hidden') {
      $wrap.hide();
      $hint.hide().text('');
      $sel.prop('required', false).prop('disabled', true).html('<option value="">Select category…</option>');
      return;
    }

    $wrap.show();
    $sel.prop('disabled', false);

    if (mode === 'unlinked') {
      $sel.prop('required', false).html('<option value="">—</option>');
      $req.hide();
      $hint.text('This department has no PMS service — category is not used.').show();
      return;
    }

    $req.show();
    if (!categories || !categories.length) {
      $sel.prop('required', false).html('<option value="">No categories on service yet</option>');
      $hint.text('Add categories under PMS → Services for this department’s service.').show();
      return;
    }

    var html = '<option value="">Select category…</option>';
    categories.forEach(function (row) {
      html += '<option value="' + row.id + '">' + (row.name || '') + '</option>';
    });
    $sel.html(html).prop('required', true);
    $hint.hide().text('');
    if (selectedId) {
      $sel.val(String(selectedId));
    }
  }

  function syncStoresTaxGroupField() {
    var inclusive = $('#storesItemTaxInclusive').val() === '1';
    var $wrap = $('.js-stores-tax-group-wrap');
    var $sel = $('#storesItemTaxGroup');
    if (inclusive) {
      $wrap.show();
      $sel.prop('disabled', false);
    } else {
      $wrap.hide();
      $sel.prop('disabled', true).val('');
    }
  }

  function loadDepartmentCatalog(categoryId, selectedPmsCategoryId, selectedReceiveId) {
    if (!categoryId) {
      setItemCategoryField('hidden', [], null);
      applyPackageOptions(defaultPackages, selectedReceiveId);
      return;
    }
    if (departmentCatalogRequest) {
      departmentCatalogRequest.abort();
    }
    departmentCatalogRequest = $.getJSON(catalogUrlTemplate.replace('__ID__', categoryId))
      .done(function (payload) {
        if (payload.linked) {
          setItemCategoryField('linked', payload.pms_categories || [], selectedPmsCategoryId);
          applyPackageOptions(payload.packages || [], selectedReceiveId);
        } else {
          setItemCategoryField('unlinked', [], null);
          applyPackageOptions(defaultPackages, selectedReceiveId);
        }
        scheduleStoresSellPriceRecommendations();
      })
      .fail(function () {
        setItemCategoryField('hidden', [], null);
        applyPackageOptions(defaultPackages, selectedReceiveId);
      });
  }

  $('#storesItemModal').on('show.bs.modal', function (e) {
    var $trigger = $(e.relatedTarget);
    if ($trigger.length && $trigger.is('.btn-stores-add-item')) {
      resetItemModal();
    }
  });

  function packageUnitLabel($select) {
    var label = $select.find('option:selected').data('unitLabel');
    if (!label) {
      label = $.trim($select.find('option:selected').text());
    }
    return label || 'unit';
  }

  function updateStoresReceiveLabel() {
    var $recv = $('#storesItemPackage');
    if (!$recv.length) return;
    var unit = packageUnitLabel($recv);
    $('#storesItemModal .js-stores-unit-label').text(unit);
  }

  function formatStoresRecommendedPrice(amount) {
    var n = parseFloat(amount);
    if (isNaN(n)) return '';
    return n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function storesSellRecommendationForPackage(pkgId) {
    var hints = window._storesSellPriceHints || {};
    var byPkg = hints.by_package || {};
    var key = pkgId ? String(pkgId) : '';
    if (key && (byPkg[key] || byPkg[parseInt(key, 10)])) {
      return byPkg[key] || byPkg[parseInt(key, 10)];
    }
    var opts = hints.options || [];
    var i;
    if (key) {
      for (i = 0; i < opts.length; i++) {
        if (String(opts[i].package_id) === key) {
          return {
            unit_price: opts[i].unit_price,
            pieces_per_sell_unit: opts[i].pieces_per_sell_unit,
          };
        }
      }
    }
    if (hints.primary && hints.primary.unit_price != null) {
      return {
        unit_price: hints.primary.unit_price,
        pieces_per_sell_unit: hints.primary.pieces_per_sell_unit,
      };
    }
    if (opts.length >= 1) {
      return {
        unit_price: opts[0].unit_price,
        pieces_per_sell_unit: opts[0].pieces_per_sell_unit,
        package_id: opts[0].package_id,
      };
    }
    return null;
  }

  function storesEditItemIdFromForm() {
    var action = $('#storesItemForm').attr('action') || '';
    var match = action.match(/\/items\/(\d+)\/?$/);
    return match ? match[1] : '';
  }

  function storesSellUnitsFromForm() {
    var units = [];
    $('.js-stores-sell-options-body .js-stores-sell-option-row').each(function () {
      var $row = $(this);
      units.push({
        package_id: $row.find('.js-stores-sell-package').val() || '',
        pieces_per_sell_unit: $row.find('[name*="pieces_per_sell_unit"], [data-field="pieces_per_sell_unit"]').val() || '1',
      });
    });
    return units;
  }

  function applyStoresSellPriceRowRecommendation($row, fillEmpty) {
    if ($('#storesItemUsage').val() !== 'sale') {
      $row.find('.js-stores-sell-price-hint').text('');
      return;
    }
    var pkgId = $row.find('.js-stores-sell-package').val();
    var rec = storesSellRecommendationForPackage(pkgId);
    var $price = $row.find('.js-stores-sell-unit-price');
    var $hint = $row.find('.js-stores-sell-price-hint');
    if (!rec || rec.unit_price == null || rec.unit_price === '') {
      $hint.text('');
      return;
    }
    var label = formatStoresRecommendedPrice(rec.unit_price);
    var source = (window._storesSellPriceHints && window._storesSellPriceHints.source) ? window._storesSellPriceHints.source : 'catalog';
    $hint.text('Recommended (' + source + '): ' + label);
    $price.attr('placeholder', label);
    if (fillEmpty !== false && ($price.val() === '' || $price.val() == null)) {
      $price.val(rec.unit_price);
      if (rec.pieces_per_sell_unit != null && rec.pieces_per_sell_unit !== '') {
        var $pieces = $row.find('[name*="pieces_per_sell_unit"], [data-field="pieces_per_sell_unit"]');
        if ($pieces.val() === '' || $pieces.val() === '1') {
          $pieces.val(rec.pieces_per_sell_unit);
        }
      }
    }
  }

  function applyStoresSellPriceRecommendations(fillEmpty) {
    if ($('#storesItemUsage').val() !== 'sale') {
      $('.js-stores-sell-price-hint').text('');
      return;
    }
    $('.js-stores-sell-options-body .js-stores-sell-option-row').each(function () {
      applyStoresSellPriceRowRecommendation($(this), fillEmpty);
    });
  }

  function maybePrefillSellRowsFromRecommendations() {
    if ($('#storesItemUsage').val() !== 'sale') return;
    var opts = (window._storesSellPriceHints && window._storesSellPriceHints.options) || [];
    if (!opts.length) return;
    var $rows = $('.js-stores-sell-options-body .js-stores-sell-option-row');
    if ($rows.length !== 1) return;
    var $row = $rows.first();
    if ($row.find('.js-stores-sell-package').val()) return;
    var primary = opts[0];
    if (primary.package_id && parseInt(primary.package_id, 10) > 0) {
      $row.find('.js-stores-sell-package').val(String(primary.package_id));
    }
    applyStoresSellPriceRowRecommendation($row, true);
  }

  function scheduleStoresSellPriceRecommendations() {
    clearTimeout(sellPriceRecTimer);
    sellPriceRecTimer = setTimeout(fetchStoresSellPriceRecommendations, 350);
  }

  function fetchStoresSellPriceRecommendations() {
    var name = $.trim($('#storesItemName').val());
    var categoryId = $('#storesItemCategory').val();
    if (!name || !categoryId || $('#storesItemUsage').val() !== 'sale') {
      window._storesSellPriceHints = { by_package: {}, options: [], source: null };
      $('.js-stores-sell-price-hint').text('');
      return;
    }
    if (sellPriceRecRequest) {
      sellPriceRecRequest.abort();
    }
    sellPriceRecRequest = $.getJSON(sellPriceRecUrl, {
      name: name,
      category_id: categoryId,
      item_id: storesEditItemIdFromForm(),
      rate: $('#storesItemPrice').val(),
      pieces_per_receive_unit: $('#storesItemPiecesPerUnit').val(),
      sell_units: storesSellUnitsFromForm(),
    })
      .done(function (payload) {
        window._storesSellPriceHints = payload || { by_package: {}, options: [], source: null, primary: null };
        maybePrefillSellRowsFromRecommendations();
        applyStoresSellPriceRecommendations(true);
      })
      .fail(function () {
        window._storesSellPriceHints = { by_package: {}, options: [], source: null, primary: null };
      })
      .always(function () {
        sellPriceRecRequest = null;
      });
  }

  function updateStoresSellRowHint($row) {
    applyStoresSellPriceRowRecommendation($row, true);
  }

  function reindexStoresSellOptions() {
    var $body = $('.js-stores-sell-options-body');
    $body.find('.js-stores-sell-option-row').each(function (index) {
      $(this).find('[name]').each(function () {
        var name = $(this).attr('name');
        if (!name) return;
        $(this).attr('name', name.replace(/selling_options\[\d+]/, 'selling_options[' + index + ']'));
      });
    });
    var count = $body.find('.js-stores-sell-option-row').length;
    $body.find('.js-stores-remove-sell-option').prop('disabled', count <= 1);
  }

  function resetStoresSellOptions(rows) {
    var $block = $('.js-stores-sell-options');
    var $body = $block.find('.js-stores-sell-options-body');
    var template = $block.find('.js-stores-sell-option-template').html();
    if (!rows || !rows.length) {
      rows = [{ package_id: '', pieces_per_sell_unit: '1', unit_price: '' }];
    }
    $body.empty();
    rows.forEach(function (row, index) {
      var $row = $(template);
      $row.find('[data-field="package_id"]').attr('name', 'selling_options[' + index + '][package_id]');
      $row.find('[data-field="pieces_per_sell_unit"]').attr('name', 'selling_options[' + index + '][pieces_per_sell_unit]');
      $row.find('[data-field="unit_price"]').attr('name', 'selling_options[' + index + '][unit_price]');
      $row.find('.js-stores-sell-package').val(row.package_id ? String(row.package_id) : '');
      $row.find('[name*="pieces_per_sell_unit"]').val(row.pieces_per_sell_unit != null ? row.pieces_per_sell_unit : '1');
      $row.find('[name*="unit_price"], [data-field="unit_price"]').val(row.unit_price != null && row.unit_price !== '' ? row.unit_price : '');
      $body.append($row);
      updateStoresSellRowHint($row);
    });
    reindexStoresSellOptions();
    scheduleStoresSellPriceRecommendations();
  }

  function syncStoresUsagePackaging() {
    var usage = $('#storesItemUsage').val();
    var isSale = usage === 'sale';
    var $sell = $('.js-stores-sell-options');
    $sell.show();
    $sell.find(':input').prop('disabled', false);

    var packageLabel = isSale ? 'Sale unit' : 'Usage unit';
    var piecesLabel = isSale ? 'Pieces per sale unit' : 'Pieces per usage unit';
    var packagesRequired = defaultPackages.length > 0;

    function setPackagingLabel($label, text) {
      $label.empty().text(text);
      if (packagesRequired) {
        $label.append(' ').append($('<span class="text-danger">*</span>'));
      }
    }

    $sell.find('.js-stores-unit-options-package-label').each(function () {
      setPackagingLabel($(this), packageLabel);
    });
    $sell.find('.js-stores-unit-options-pieces-label').each(function () {
      setPackagingLabel($(this), piecesLabel);
    });

    if (isSale) {
      $sell.find('.js-stores-sell-add-wrap').show();
      $sell.find('.js-stores-sell-price-wrap').show();
      $sell.find('.js-stores-sell-unit-price').prop('disabled', false);
      $sell.find('.stores-packaging-remove-btn').show().prop('disabled', $sell.find('.js-stores-sell-option-row').length <= 1);
    } else {
      $sell.find('.js-stores-sell-price-wrap').hide();
      $sell.find('.js-stores-sell-unit-price').prop('disabled', true);
      $sell.find('.js-stores-sell-add-wrap').hide();
      while ($sell.find('.js-stores-sell-option-row').length > 1) {
        $sell.find('.js-stores-sell-option-row').last().remove();
      }
      reindexStoresSellOptions();
      $sell.find('.stores-packaging-remove-btn').hide();
      window._storesSellPriceHints = { by_package: {}, options: [], source: null };
      $('.js-stores-sell-price-hint').text('');
    }
    if (isSale) {
      scheduleStoresSellPriceRecommendations();
    }
  }

  function resetItemModal() {
    $('#storesItemForm').attr('action', '{{ route('hotel.stores.items.store') }}');
    $('#storesItemMethod').val('');
    $('#storesItemModalTitle').text('Add Item');
    $('#storesItemForm')[0].reset();
    $('#storesItemUsage').val('internal');
    $('#storesItemTaxInclusive').val('1');
    $('#storesItemInventoried').val('1');
    $('#storesItemCapital').val('0');
    $('#storesItemStock').val('0');
    $('#storesItemPiecesPerUnit').val('1');
    $('#storesItemVendor').val('');
    $('#storesItemTaxGroup').val('');
    $('#storesItemPackage').val('');
    resetStoresSellOptions([]);
    setItemCategoryField('hidden', [], null);
    applyPackageOptions(defaultPackages, null);
    syncStoresUsagePackaging();
    syncStoresTaxGroupField();
    window._storesSellPriceHints = { by_package: {}, options: [], source: null };
  }

  $(document).on('change', '#storesItemCategory', function () {
    loadDepartmentCatalog($(this).val(), null, null);
    scheduleStoresSellPriceRecommendations();
  });

  $(document).on('input', '#storesItemName', scheduleStoresSellPriceRecommendations);
  $(document).on('input change', '#storesItemPrice, #storesItemPiecesPerUnit', scheduleStoresSellPriceRecommendations);
  $(document).on('input change', '[name*="pieces_per_sell_unit"], [data-field="pieces_per_sell_unit"]', scheduleStoresSellPriceRecommendations);

  $(document).on('change', '#storesItemTaxInclusive', syncStoresTaxGroupField);
  $(document).on('change', '#storesItemUsage', syncStoresUsagePackaging);
  $(document).on('input', '.js-stores-sell-unit-price', function () {
    var $row = $(this).closest('.js-stores-sell-option-row');
    applyStoresSellPriceRowRecommendation($row, false);
  });
  $(document).on('change', '.js-stores-receive-package', updateStoresReceiveLabel);
  $(document).on('change', '.js-stores-sell-package', function () {
    updateStoresSellRowHint($(this).closest('.js-stores-sell-option-row'));
  });
  $(document).on('click', '.js-stores-add-sell-option', function () {
    var $block = $(this).closest('.js-stores-sell-options');
    var $body = $block.find('.js-stores-sell-options-body');
    var html = $block.find('.js-stores-sell-option-template').html();
    var index = $body.find('.js-stores-sell-option-row').length;
    var $row = $(html);
    $row.find('[data-field="package_id"]').attr('name', 'selling_options[' + index + '][package_id]');
    $row.find('[data-field="pieces_per_sell_unit"]').attr('name', 'selling_options[' + index + '][pieces_per_sell_unit]');
    $row.find('[data-field="unit_price"]').attr('name', 'selling_options[' + index + '][unit_price]');
    $body.append($row);
    syncStoresUsagePackaging();
    reindexStoresSellOptions();
    applyStoresSellPriceRowRecommendation($row, true);
  });
  $(document).on('click', '.js-stores-remove-sell-option', function () {
    $(this).closest('.js-stores-sell-option-row').remove();
    reindexStoresSellOptions();
  });

  function fillItemModalFromEditButton($btn) {
    $('#storesItemForm').attr('action', '{{ url('/hotel/stores/items') }}/' + $btn.attr('data-id'));
    $('#storesItemMethod').val('PUT');
    $('#storesItemModalTitle').text('Edit Item');
    $('#storesItemName').val($btn.attr('data-name') || '');
    var categoryId = $btn.attr('data-category-id') || '';
    var pmsCategoryId = $btn.attr('data-pms-category-id') || '';
    var receivePackageId = $btn.attr('data-package-id') || '';
    $('#storesItemCategory').val(categoryId);
    $('#storesItemVendor').val($btn.attr('data-vendor-id') || '');
    $('#storesItemPrice').val($btn.attr('data-rate') ?? '0');
    $('#storesItemUsage').val($btn.attr('data-usage') || 'internal');
    $('#storesItemTaxGroup').val($btn.attr('data-tax-group-id') || '');
    $('#storesItemPiecesPerUnit').val($btn.attr('data-pieces') || '1');
    $('#storesItemTaxInclusive').val($btn.attr('data-tax-inclusive') === '1' ? '1' : '0');
    $('#storesItemInventoried').val($btn.attr('data-inventoried') === '1' ? '1' : '0');
    $('#storesItemCapital').val($btn.attr('data-capital') === '1' ? '1' : '0');
    $('#storesItemStock').val($btn.attr('data-stock') ?? '0');

    var sellRows = [];
    var sellJson = $btn.attr('data-sell-options');
    if (sellJson) {
      try {
        sellRows = JSON.parse(sellJson);
      } catch (err) {
        sellRows = [];
      }
    }
    resetStoresSellOptions(sellRows);
    loadDepartmentCatalog(categoryId, pmsCategoryId, receivePackageId);
    syncStoresUsagePackaging();
    syncStoresTaxGroupField();
    scheduleStoresSellPriceRecommendations();
  }

  $(document).on('click', '.js-stores-edit-item', function () {
    fillItemModalFromEditButton($(this));
    $('#storesItemModal').modal('show');
  });

  updateStoresReceiveLabel();
  syncStoresUsagePackaging();
  syncStoresTaxGroupField();
})();
</script>
@endpush
