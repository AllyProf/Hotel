@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $storeIndentsList = $storeIndentsList ?? null;
  $poSearch = $poSearch ?? '';
  $poFrom = $poFrom ?? now()->subDays(365)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $indentStatus = $indentStatus ?? 'all';
  $indentDepartmentFilter = (int) ($indentDepartmentFilter ?? 0);
  $indentStatusFilters = $indentStatusFilters ?? config('hotel_stores.indent_status_filters', []);
  $storeIndentPermissions = $storeIndentPermissions ?? ['create' => true, 'approve' => true, 'edit' => true, 'delete' => true];
  $indentIndexRoute = $indentIndexRoute ?? route('hotel.stores.index');
  $indentIndexTab = $indentIndexTab ?? 'indent';
  $indentRoutes = $indentRoutes ?? [
    'store' => route('hotel.stores.indents.store'),
    'update' => url('/hotel/stores/indents/__ID__'),
    'destroy' => url('/hotel/stores/indents/__ID__'),
    'approve' => url('/hotel/stores/indents/__ID__/approve'),
    'receive' => url('/hotel/stores/indents/__ID__/receive'),
  ];
  $indentShowApprove = $indentShowApprove ?? true;
  $indentCreateButtonLabel = $indentCreateButtonLabel ?? 'Create';
  $indentEmptyMessage = $indentEmptyMessage ?? 'No indents in this period. Click <strong>Create</strong> to add one.';
  $indentModalTitleCreate = $indentModalTitleCreate ?? 'Create Indent';
  $indentModalTitleEdit = $indentModalTitleEdit ?? 'Edit Indent';
  $indentDefaultDepartmentId = $hkDefaultDepartmentId ?? null;
  $indentAllowDepartmentReceive = $indentAllowDepartmentReceive ?? false;
  $indentDetailRoute = $indentDetailRoute ?? route('hotel.stores.indents.show', ['indent' => '__ID__']);
  $indentAutoOpenId = (int) ($indentAutoOpenId ?? 0);
  $indentFilterItemsByDepartment = (bool) ($indentFilterItemsByDepartment ?? false);
  $indentCatalogByDepartment = $indentCatalogByDepartment ?? [];
  $indentRequesterUsesStoreCategory = (bool) ($indentRequesterUsesStoreCategory ?? false);
  $indentCategories = $indentCategories ?? collect();
  $currency = strtoupper(trim((string) ($hotel->currency ?? 'TZS'))) ?: 'TZS';
  $indentMoneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;
  $indentEditPayload = [];
  if ($storeIndentsList) {
    foreach ($storeIndentsList as $ind) {
      $ind->loadMissing('items.masterItem');
      $requesterStoreCategoryId = null;
      if ($indentRequesterUsesStoreCategory) {
        $requesterStoreCategoryId = (int) ($storeItemService->requesterStoreCategoryIdForIndent($hotel, $ind) ?? 0);
      }
      $indentEditPayload[$ind->id] = [
        'department_id' => $ind->hotel_department_id,
        'requester_store_category_id' => $requesterStoreCategoryId > 0 ? $requesterStoreCategoryId : null,
        'items' => $ind->items->map(fn ($line) => [
          'master_item_id' => $line->hotel_store_master_item_id,
          'name' => $line->name,
          'quantity' => (float) $line->quantity,
          'unit' => $line->unit,
          'comments' => $line->comments,
        ])->values()->all(),
      ];
    }
  }
@endphp

<form method="GET" action="{{ $indentIndexRoute }}" class="stores-toolbar stores-po-filter-bar">
  <input type="hidden" name="tab" value="{{ $indentIndexTab }}">
  <div class="stores-po-filter-row">
    <div class="stores-filter-fields">
      <div class="stores-filter-field">
        <label class="control-label" for="storesIndentFrom">Start Date :</label>
        <input type="date" name="po_from" id="storesIndentFrom" class="form-control" value="{{ $poFrom }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field">
        <label class="control-label" for="storesIndentTo">End Date :</label>
        <input type="date" name="po_to" id="storesIndentTo" class="form-control" value="{{ $poTo }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field stores-filter-field--status">
        <label class="control-label" for="storesIndentStatus">Status :</label>
        <select name="indent_status" id="storesIndentStatus" class="form-control" onchange="this.form.submit()">
          @foreach($indentStatusFilters as $value => $label)
            <option value="{{ $value }}" @selected($indentStatus === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      @if($indentRequesterUsesStoreCategory && ($indentCategories ?? collect())->isNotEmpty())
        <div class="stores-filter-field">
          <label class="control-label" for="storesIndentDepartmentFilter">Department :</label>
          <select name="indent_department" id="storesIndentDepartmentFilter" class="form-control" onchange="this.form.submit()">
            <option value="0">All departments</option>
            @foreach($indentCategories as $cat)
              @php
                $filterDeptId = (int) ($cat->hotel_department_id ?? 0);
                if ($filterDeptId <= 0 && isset($storeItemService, $hotel)) {
                  try {
                    $filterDeptId = $storeItemService->resolveHotelDepartmentIdForStoreCategory($hotel, (int) $cat->id);
                  } catch (\Throwable $e) {
                    $filterDeptId = 0;
                  }
                }
              @endphp
              @if($filterDeptId > 0)
                <option value="{{ $filterDeptId }}" @selected($indentDepartmentFilter === $filterDeptId)>{{ $cat->name }}</option>
              @endif
            @endforeach
          </select>
        </div>
      @endif
    </div>
    <div class="stores-po-inline-actions">
      @if($storeIndentPermissions['create'])
        <button type="button" class="btn btn-stores-po-create" data-toggle="modal" data-target="#storesCreateIndentModal"
          @if($indentRequesterUsesStoreCategory ? $indentCategories->isEmpty() : ($indentDepartments ?? collect())->isEmpty()) disabled title="Add store departments under Item Category first" @endif>
          {{ $indentCreateButtonLabel }}
        </button>
      @endif
      <input type="search" name="po_search" class="form-control stores-po-inline-search @if(! $storeIndentPermissions['create']) stores-po-inline-search--solo @endif" placeholder="Search…" value="{{ $poSearch }}">
    </div>
  </div>
</form>

<div class="stores-table-wrap table-responsive stores-po-table-wrap">
  <table class="table table-bordered table-hover stores-po-list-table mb-0">
    <thead>
      <tr>
        <th>Indent #</th>
        <th>Date</th>
        <th>Status</th>
        <th>Department</th>
        <th class="stores-po-actions-col">Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse($storeIndentsList ?? [] as $ind)
        <tr>
          <td>{{ $ind->indent_number }}</td>
          <td class="text-nowrap">{{ $ind->dateTimeLabel() }}</td>
          <td>{{ $ind->statusLabel() }}</td>
          <td>
            {{ $storeItemService->requesterLabelForIndent($hotel, $ind) }}
            @if($ind->department && strcasecmp(trim($storeItemService->requesterLabelForIndent($hotel, $ind)), trim($ind->department->name)) !== 0)
              <span class="text-muted small d-block">HR: {{ $ind->department->name }}</span>
            @endif
          </td>
          <td class="stores-po-actions-cell">
            @if($ind->isApplied())
              @if($indentShowApprove && ($storeIndentPermissions['approve'] ?? false))
                <form method="POST" action="{{ str_replace('__ID__', $ind->id, $indentRoutes['approve'] ?? route('hotel.stores.indents.approve', $ind)) }}" class="d-inline js-swal-confirm">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-stores-po-approve"
                    data-title="Approve indent?"
                    data-text="Issue {{ $ind->indent_number }} from main store for {{ $ind->department?->name }} to receive?"
                    data-confirm="Approve">Approve</button>
                </form>
              @endif
              @if($storeIndentPermissions['edit'])
                <button type="button" class="btn btn-sm stores-icon-btn text-primary js-stores-indent-edit" data-id="{{ $ind->id }}" title="Edit">
                  <i class="fa fa-pencil"></i>
                </button>
              @endif
              @if($storeIndentPermissions['delete'])
                <form method="POST" action="{{ str_replace('__ID__', $ind->id, $indentRoutes['destroy']) }}" class="d-inline js-swal-delete">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="stores-icon-btn text-danger" title="Delete"><i class="fa fa-trash"></i></button>
                </form>
              @endif
            @elseif($ind->isIssued())
              @if($indentAllowDepartmentReceive && ($storeIndentPermissions['receive'] ?? false))
                <form method="POST" action="{{ str_replace('__ID__', $ind->id, $indentRoutes['receive']) }}" class="d-inline js-swal-confirm">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-stores-po-approve"
                    data-title="Receive into department stock?"
                    data-text="Add items from {{ $ind->indent_number }} to {{ $ind->department?->name }} stock?"
                    data-confirm="Receive">Receive</button>
                </form>
              @else
                <span class="btn btn-sm btn-stores-indent-awaiting" role="status" title="Issued from main store — awaiting department to receive">Waiting for receiver</span>
              @endif
              <a href="#" class="stores-icon-btn text-info js-indent-view" data-indent-id="{{ $ind->id }}" title="View"><i class="fa fa-eye"></i></a>
            @elseif($ind->isReceived())
              <span class="btn btn-sm btn-stores-indent-received" role="status" aria-label="Received">Received</span>
              <a href="#" class="stores-icon-btn text-info js-indent-view" data-indent-id="{{ $ind->id }}" title="View"><i class="fa fa-eye"></i></a>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center text-muted py-4">{!! $indentEmptyMessage !!}</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($storeIndentsList && method_exists($storeIndentsList, 'hasPages') && $storeIndentsList->hasPages())
  <div class="px-3 pb-3">{{ $storeIndentsList->links() }}</div>
@endif

@include('hotel.erp.stores.partials.create-indent-modal')
@include('hotel.erp.stores.partials.indent-view-modal-shell')

@push('scripts')
<script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
<script>
(function ($) {
  var catalog = @json($indentMasterItemsPayload ?? []);
  var catalogByDept = @json($indentCatalogByDepartment);
  var indentFilterItemsByDepartment = @json($indentFilterItemsByDepartment);
  var indentRequesterUsesStoreCategory = @json($indentRequesterUsesStoreCategory);
  var indentEditPayload = @json($indentEditPayload);
  var indentMoneyDecimals = @json($indentMoneyDecimals);
  var indentItems = [];
  var indentIdx = 0;

  function money(v) {
    v = parseFloat(v) || 0;
    var factor = Math.pow(10, indentMoneyDecimals);
    v = Math.round(v * factor) / factor;
    var parts = v.toFixed(indentMoneyDecimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return indentMoneyDecimals > 0 ? parts.join('.') : parts[0];
  }

  function moneyRaw(v) {
    v = parseFloat(v) || 0;
    var factor = Math.pow(10, indentMoneyDecimals);
    return String(Math.round(v * factor) / factor);
  }

  function activeDepartmentId() {
    return parseInt($('#storesIndentDepartment').val(), 10) || 0;
  }

  function deptCatalog() {
    if (!indentFilterItemsByDepartment) {
      return catalog;
    }
    var deptId = activeDepartmentId();
    if (deptId <= 0) {
      return [];
    }
    return catalogByDept[deptId] || catalogByDept[String(deptId)] || [];
  }

  function filteredCatalog() {
    var $catEl = $('#storesIndentCategory');
    var cat = $catEl.length ? (parseInt($catEl.val(), 10) || 0) : 0;
    return deptCatalog().filter(function (row) {
      if (cat > 0 && row.category_id !== cat) return false;
      return true;
    });
  }

  function findCatalogRow(masterId, deptId) {
    masterId = parseInt(masterId, 10);
    if (!masterId) return null;
    var list = indentFilterItemsByDepartment
      ? (catalogByDept[deptId] || catalogByDept[String(deptId)] || [])
      : catalog;
    var row = list.find(function (r) { return r.id === masterId; });
    if (row) return row;
    if (!indentFilterItemsByDepartment) return null;
    var keys = Object.keys(catalogByDept || {});
    for (var k = 0; k < keys.length; k++) {
      var bucket = catalogByDept[keys[k]] || [];
      row = bucket.find(function (r) { return r.id === masterId; });
      if (row) return row;
    }
    return null;
  }

  function selectedCatalogItem() {
    var id = parseInt($('#storesIndentItemSelect').val(), 10);
    if (!id) return null;
    return filteredCatalog().find(function (r) { return r.id === id; }) || null;
  }

  function rebuildIndentItemSelect() {
    var $sel = $('#storesIndentItemSelect');
    var prev = $sel.val();
    var deptId = activeDepartmentId();
    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
    $sel.empty();
    if (indentFilterItemsByDepartment && deptId <= 0) {
      $sel.prop('disabled', true).append($('<option value="">Select department first</option>'));
      return;
    }
    var list = filteredCatalog();
    $sel.prop('disabled', false).append($('<option value="">Select item</option>'));
    list.forEach(function (row) {
      var label = row.name;
      if (row.usage_type === 'sale') {
        var primary = primarySellPrice(row);
        if (primary) {
          label += ' · Sell ' + formatSellPriceLabel(primary.price, primary.unit);
        }
      }
      $sel.append($('<option></option>').val(row.id).text(label));
    });
    $sel.select2({ width: '100%', placeholder: 'Select item', allowClear: true, dropdownParent: $('#storesCreateIndentModal') });
    if (prev && $sel.find('option[value="' + prev + '"]').length) $sel.val(prev).trigger('change.select2');
    else $sel.val('').trigger('change.select2');
  }

  function indentQtyInCart(masterId) {
    if (!masterId) return 0;
    return indentItems.reduce(function (sum, row) {
      if (row.master_item_id === masterId) return sum + row.quantity;
      return sum;
    }, 0);
  }

  function formatQtyWithUnit(qty, unit) {
    var q = parseFloat(qty) || 0;
    var display = q % 1 === 0 ? String(q) : money(q);
    return unit ? display + ' ' + unit : display;
  }

  function remainingStockForItem(item) {
    if (!item || !item.inventoried) return null;
    var inCart = indentQtyInCart(item.id);
    return Math.max(0, (parseFloat(item.stock) || 0) - inCart);
  }

  function selectedUnitOption(item) {
    if (!item || !item.unit_options || !item.unit_options.length) return null;
    var $unit = $('.js-stores-indent-unit');
    var key = $unit.length ? String($unit.val() || '') : '';
    if (key === '') return item.unit_options[0];
    return item.unit_options.find(function (o) { return o.value === key; }) || item.unit_options[0];
  }

  function primarySellPrice(item) {
    if (!item || item.usage_type !== 'sale') return null;
    var price = parseFloat(item.primary_sell_price);
    if (isFinite(price) && price > 0) {
      return { price: price, unit: item.primary_sell_unit || '' };
    }
    if (!item.unit_options || !item.unit_options.length) return null;
    var usage = null;
    item.unit_options.forEach(function (o) {
      var p = parseFloat(o.unit_price);
      if (!isFinite(p) || p <= 0) return;
      if (!usage || (parseFloat(o.pieces_per_sell_unit) || 999) < (parseFloat(usage.pieces_per_sell_unit) || 999)) {
        usage = o;
      }
    });
    if (!usage) return null;
    return { price: parseFloat(usage.unit_price), unit: usage.unit || '' };
  }

  function sellPriceForUnitOption(item, opt) {
    if (!item || item.usage_type !== 'sale') return null;
    if (opt && (opt.kind === 'usage' || String(opt.value || '').indexOf('usage:') === 0)) {
      var price = parseFloat(opt.unit_price);
      if (isFinite(price) && price > 0) {
        return { price: price, unit: opt.unit || '' };
      }
    }
    return primarySellPrice(item);
  }

  function formatSellPriceLabel(price, unit) {
    if (price == null || !isFinite(price) || price <= 0) return '—';
    return money(price) + (unit ? ' / ' + unit : '');
  }

  function updateIndentSellPriceHint() {
    var $hint = $('#storesIndentSellPriceHint');
    if (!$hint.length) return;
    var item = selectedCatalogItem();
    if (!item || item.usage_type !== 'sale') {
      $hint.text('').removeClass('text-success');
      return;
    }
    var opt = selectedUnitOption(item);
    var sell = sellPriceForUnitOption(item, opt);
    if (!sell) {
      $hint.text('Selling price not set on bar/PMS catalog for this item.').removeClass('text-success');
      return;
    }
    var issueUnit = opt && opt.unit ? opt.unit : '';
    var hint = 'Selling price: ' + formatSellPriceLabel(sell.price, sell.unit);
    if (issueUnit && sell.unit && issueUnit !== sell.unit) {
      hint += ' (issue qty is in ' + issueUnit + ')';
    }
    $hint.text(hint).addClass('text-success');
  }

  function availableQtyForIssueUnit(item) {
    if (!item || !item.inventoried) return null;
    var stockReceive = remainingStockForItem(item);
    var opt = selectedUnitOption(item);
    if (!opt) {
      return { qty: stockReceive, unit: item.stock_unit || item.unit || '' };
    }
    if (opt.kind === 'receive' || opt.value === 'receive' || opt.value === 'default') {
      return { qty: stockReceive, unit: opt.unit };
    }
    var receivePieces = parseFloat(item.pieces_per_receive_unit) || 1;
    var sellPieces = parseFloat(opt.pieces_per_sell_unit) || 1;
    var piecesAvailable = stockReceive * receivePieces;
    var maxQty = sellPieces > 0 ? piecesAvailable / sellPieces : 0;
    return { qty: maxQty, unit: opt.unit };
  }

  function updateIndentQtyHint() {
    var item = selectedCatalogItem();
    var $hint = $('#storesIndentQtyHint');
    var $qty = $('.js-stores-indent-qty');
    if (!item) {
      $hint.text('').removeClass('text-danger');
      $qty.removeClass('is-invalid');
      return;
    }
    if (!item.inventoried) {
      $hint.text('').removeClass('text-danger');
      $qty.removeClass('is-invalid');
      return;
    }
    var avail = availableQtyForIssueUnit(item);
    var remaining = avail.qty;
    var displayUnit = avail.unit;
    var qty = parseFloat($qty.val()) || 0;
    if (qty > remaining + 0.0001) {
      $hint.text('Only ' + formatQtyWithUnit(remaining, displayUnit) + ' available.').addClass('text-danger');
      $qty.addClass('is-invalid');
    } else {
      $hint.text('Available: ' + formatQtyWithUnit(remaining, displayUnit) + '.').removeClass('text-danger');
      $qty.removeClass('is-invalid');
    }
  }

  function rebuildIndentUnitSelect(item) {
    var $unit = $('.js-stores-indent-unit');
    if (!$unit.length) return;
    var prevKey = $unit.val();
    $unit.empty();
    if (!item || !item.unit_options || !item.unit_options.length) {
      $unit.prop('disabled', true).append($('<option value="">Select item first</option>'));
      return;
    }
    $unit.prop('disabled', false);
    item.unit_options.forEach(function (opt) {
      $unit.append(
        $('<option></option>')
          .val(opt.value)
          .attr('data-unit', opt.unit)
          .text(opt.label)
      );
    });
    var defaultKey = null;
    item.unit_options.forEach(function (opt) {
      if (!defaultKey && String(opt.unit) === String(item.unit)) defaultKey = opt.value;
    });
    if (!defaultKey) {
      item.unit_options.forEach(function (opt) {
        if (!defaultKey && opt.kind === 'usage') defaultKey = opt.value;
      });
    }
    if (!defaultKey) defaultKey = item.unit_options[0].value;
    if (prevKey && $unit.find('option[value="' + prevKey + '"]').length) {
      $unit.val(prevKey);
    } else {
      $unit.val(defaultKey);
    }
  }

  function selectedIndentUnit() {
    var $unit = $('.js-stores-indent-unit');
    if ($unit.length && !$unit.prop('disabled')) {
      var $opt = $unit.find('option:selected');
      return ($opt.attr('data-unit') || '').trim();
    }
    var item = selectedCatalogItem();
    return item ? (item.unit || '') : '';
  }

  function recalcIndentEntry() {
    var item = selectedCatalogItem();
    rebuildIndentUnitSelect(item);
    updateIndentQtyHint();
    updateIndentSellPriceHint();
  }

  function renderIndentTable() {
    var $body = $('#storesIndentItemsBody');
    $body.empty();
    var sumQty = 0;
    indentItems.forEach(function (item) {
      sumQty += item.quantity;
      var $tr = $('<tr></tr>');
      $tr.append($('<td></td>').text(item.name).append(
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][name]').val(item.name),
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][master_item_id]').val(item.master_item_id || ''),
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][unit]').val(item.unit || '')
      ));
      $tr.append($('<td></td>').text(formatQtyWithUnit(item.quantity, item.unit)).append(
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][quantity]').val(item.quantity)
      ));
      $tr.append($('<td></td>').text(
        item.sell_price != null && item.sell_price > 0
          ? formatSellPriceLabel(item.sell_price, item.sell_unit || item.unit)
          : '—'
      ));
      $tr.append($('<td></td>').text(item.comments || '').append(
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][comments]').val(item.comments || '')
      ));
      $tr.append($('<td class="text-center"></td>').append(
        $('<button type="button" class="stores-icon-btn text-danger"><i class="fa fa-trash"></i></button>').on('click', function () {
          indentItems = indentItems.filter(function (r) { return r.i !== item.i; });
          renderIndentTable();
        })
      ));
      $body.append($tr);
    });
    $('#storesIndentSumQty').text(sumQty % 1 === 0 ? sumQty : money(sumQty));
    $('#storesIndentSubmit').prop('disabled', indentItems.length === 0);
    updateIndentQtyHint();
  }

  var indentStoreUrl = @json($indentRoutes['store']);
  var indentUpdateTemplate = @json($indentRoutes['update']);
  var indentDefaultDepartmentId = @json($indentDefaultDepartmentId);
  var indentModalTitleCreate = @json($indentModalTitleCreate);
  var indentModalTitleEdit = @json($indentModalTitleEdit);

  function loadIndentItemsFromRows(rows) {
    indentItems = [];
    indentIdx = 0;
    var deptId = activeDepartmentId();
    (rows || []).forEach(function (row) {
      var catRow = findCatalogRow(row.master_item_id, deptId);
      var sellPrice = null;
      var sellUnit = (catRow && catRow.unit) ? catRow.unit : (row.unit || '');
      if (catRow && catRow.usage_type === 'sale') {
        var primary = primarySellPrice(catRow);
        if (primary) {
          sellPrice = primary.price;
          sellUnit = primary.unit || sellUnit;
        }
      }
      indentItems.push({
        i: indentIdx++,
        master_item_id: row.master_item_id,
        name: row.name,
        quantity: parseFloat(row.quantity) || 0,
        unit: sellUnit,
        sell_price: sellPrice,
        sell_unit: sellUnit,
        comments: row.comments || ''
      });
    });
    renderIndentTable();
  }

  function resetIndentModal() {
    indentItems = [];
    indentIdx = 0;
    $('#storesIndentForm').attr('action', indentStoreUrl);
    $('#storesIndentMethod').val('');
    $('#storesIndentModalTitle').text(indentModalTitleCreate);
    $('#storesIndentForm')[0].reset();
    if (indentDefaultDepartmentId) {
      $('#storesIndentDepartment').val(String(indentDefaultDepartmentId));
    }
    if ($('#storesIndentCategory').length) $('#storesIndentCategory').val('0');
    $('.js-stores-indent-qty').val('0');
    rebuildIndentItemSelect();
    recalcIndentEntry();
    renderIndentTable();
    $('#storesIndentQtyHint').text('').removeClass('text-danger');
  }

  var indentModalEditMode = false;
  $('#storesCreateIndentModal').on('show.bs.modal', function (e) {
    if (indentModalEditMode) { indentModalEditMode = false; return; }
    if (!$(e.relatedTarget).hasClass('js-stores-indent-edit')) resetIndentModal();
  });

  $('.js-stores-indent-edit').on('click', function () {
    var id = $(this).data('id');
    var payload = indentEditPayload[id];
    if (!payload) return;
    indentModalEditMode = true;
    resetIndentModal();
    $('#storesIndentForm').attr('action', indentUpdateTemplate.replace('__ID__', id));
    $('#storesIndentMethod').val('PUT');
    $('#storesIndentModalTitle').text(indentModalTitleEdit);
    if (indentRequesterUsesStoreCategory) {
      if (payload.requester_store_category_id) {
        $('#storesIndentDepartment').val(String(payload.requester_store_category_id));
      }
    } else {
      $('#storesIndentDepartment').val(String(payload.department_id));
    }
    rebuildIndentItemSelect();
    loadIndentItemsFromRows(payload.items);
    $('#storesCreateIndentModal').modal('show');
  });

  if ($('#storesIndentCategory').is('select')) {
    $('#storesIndentCategory').on('change', rebuildIndentItemSelect);
  }
  $('#storesIndentDepartment').on('change', function () {
    rebuildIndentItemSelect();
    recalcIndentEntry();
  });
  $('#storesIndentItemSelect').on('change', recalcIndentEntry);
  $(document).on('change', '.js-stores-indent-unit', function () {
    updateIndentQtyHint();
    updateIndentSellPriceHint();
  });
  $('.js-stores-indent-qty').on('input', updateIndentQtyHint);

  $('.js-stores-indent-add').on('click', function () {
    var item = selectedCatalogItem();
    var qty = parseFloat($('.js-stores-indent-qty').val()) || 0;
    if (!item || qty <= 0) return;
    if (item.inventoried) {
      var avail = availableQtyForIssueUnit(item);
      if (qty > avail.qty + 0.0001) {
        updateIndentQtyHint();
        return;
      }
    }
    var unitOpt = selectedUnitOption(item);
    var sell = sellPriceForUnitOption(item, unitOpt);
    indentItems.push({
      i: indentIdx++,
      master_item_id: item.id,
      name: item.name,
      quantity: qty,
      unit: selectedIndentUnit() || item.unit || '',
      sell_price: sell ? sell.price : null,
      sell_unit: sell ? sell.unit : '',
      comments: ($('.js-stores-indent-line-comment').val() || '').trim()
    });
    $('#storesIndentItemSelect').val(null).trigger('change');
    $('.js-stores-indent-qty').val('0');
    $('.js-stores-indent-line-comment').val('');
    recalcIndentEntry();
    renderIndentTable();
    updateIndentQtyHint();
  });

  rebuildIndentItemSelect();

  var indentDetailRoute = @json($indentDetailRoute);
  var indentAutoOpenId = @json($indentAutoOpenId);
  var indentDetailLoading = false;

  function stripIndentViewFromUrl() {
    if (!window.history.replaceState) return;
    var url = new URL(window.location.href);
    if (!url.searchParams.has('view_indent')) return;
    url.searchParams.delete('view_indent');
    window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash);
  }

  function openIndentDetail(id) {
    id = parseInt(id, 10);
    if (!id || indentDetailLoading) return;
    indentDetailLoading = true;
    var url = indentDetailRoute.replace('__ID__', String(id));
    var $modal = $('#storesIndentDetailModal');
    var $mount = $('#storesIndentDetailModalMount');
    $mount.html('<div class="modal-content"><div class="modal-body text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x"></i></div></div>');
    $modal.modal('show');
    $.get(url).done(function (html) {
      $mount.html(html);
    }).fail(function () {
      $mount.html('<div class="modal-content"><div class="modal-body"><div class="alert alert-danger mb-0">Could not load indent details.</div></div><div class="modal-footer"><button type="button" class="btn btn-stores-cancel" data-dismiss="modal">Close</button></div></div>');
    }).always(function () {
      indentDetailLoading = false;
    });
  }

  $(document).on('click', '.js-indent-view', function (e) {
    e.preventDefault();
    openIndentDetail($(this).data('indent-id'));
  });

  $('#storesIndentDetailModal').on('hidden.bs.modal', function () {
    stripIndentViewFromUrl();
    $('#storesIndentDetailModalMount').html('<div class="modal-content"><div class="modal-body text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x"></i></div></div>');
  });

  if (indentAutoOpenId > 0) {
    openIndentDetail(indentAutoOpenId);
  }
})(jQuery);
</script>
@endpush
