@php
  $consumptionData = $consumptionData ?? ['departments' => collect(), 'rows' => collect()];
  $storeConsumptionsList = $storeConsumptionsList ?? null;
  $poSearch = $poSearch ?? '';
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $consumptionDeptFilter = $consumptionDeptFilter ?? 0;
  $storeConsumptionPermissions = $storeConsumptionPermissions ?? ['create' => true];
  $consumptionIndexRoute = $consumptionIndexRoute ?? route('hotel.stores.index');
  $consumptionIndexTab = $consumptionIndexTab ?? 'consumption';
  $consumptionCreateButtonLabel = $consumptionCreateButtonLabel ?? 'Create';
  $consumptionEmptyMessage = $consumptionEmptyMessage ?? 'No consumption records in this period. Click <strong>Create</strong> to record items used by a department.';
  $consumptionHideDeptStockSection = $consumptionHideDeptStockSection ?? false;
  $consumptionDeptFilterParam = $consumptionDeptFilterParam ?? 'department_id';
  $consumptionShowRoomDistribution = $consumptionShowRoomDistribution ?? false;
  $consumptionRoomOptions = $consumptionRoomOptions ?? [];
  $consumptionPreserveFilter = $consumptionPreserveFilter ?? false;
  $consumptionListFilter = $consumptionPreserveFilter ? request()->query('filter') : null;
  $consumptionDetailRoute = $consumptionDetailRoute ?? route('hotel.stores.consumptions.show', ['consumption' => '__ID__']);
  $consumptionAutoOpenId = (int) ($consumptionAutoOpenId ?? 0);
@endphp

<form method="GET" action="{{ $consumptionIndexRoute }}" class="stores-toolbar stores-po-filter-bar">
  <input type="hidden" name="tab" value="{{ $consumptionIndexTab }}">
  <div class="stores-po-filter-row">
    <div class="stores-filter-fields">
      <div class="stores-filter-field">
        <label class="control-label" for="storesConsumptionFrom">Start Date :</label>
        <input type="date" name="po_from" id="storesConsumptionFrom" class="form-control" value="{{ $poFrom }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field">
        <label class="control-label" for="storesConsumptionTo">End Date :</label>
        <input type="date" name="po_to" id="storesConsumptionTo" class="form-control" value="{{ $poTo }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field stores-filter-field--status">
        <label class="control-label" for="storesConsumptionDeptFilter">Department :</label>
        <select name="{{ $consumptionDeptFilterParam }}" id="storesConsumptionDeptFilter" class="form-control" onchange="this.form.submit()">
          <option value="0">All</option>
          @foreach($consumptionDepartments ?? [] as $dept)
            <option value="{{ $dept->id }}" @selected($consumptionDeptFilter === $dept->id)>{{ $dept->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="stores-po-inline-actions">
      @if($storeConsumptionPermissions['create'])
        <button type="button" class="btn btn-stores-po-create" data-toggle="modal" data-target="#storesCreateConsumptionModal"
          @if(($consumptionDepartments ?? collect())->isEmpty()) disabled title="Add departments under Settings → Department first" @endif>
          {{ $consumptionCreateButtonLabel }}
        </button>
      @endif
      <input type="search" name="po_search" class="form-control stores-po-inline-search @if(! $storeConsumptionPermissions['create']) stores-po-inline-search--solo @endif" placeholder="Search…" value="{{ $poSearch }}">
    </div>
  </div>
</form>

<div class="stores-table-wrap table-responsive stores-po-table-wrap">
  <table class="table table-bordered table-hover stores-po-list-table mb-0">
    <thead>
      <tr>
        <th>Consumption #</th>
        <th>Date</th>
        <th>Department</th>
        @if($consumptionShowRoomDistribution)
          <th>Room(s)</th>
        @endif
        <th>Items</th>
        <th style="width:48px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($storeConsumptionsList ?? [] as $con)
        <tr>
          <td>
            <a href="#" class="text-dark font-weight-bold js-consumption-view" data-consumption-id="{{ $con->id }}">{{ $con->consumption_number }}</a>
          </td>
          <td class="text-nowrap">{{ $con->dateTimeLabel() }}</td>
          <td>{{ $con->department?->name ?? '—' }}</td>
          @if($consumptionShowRoomDistribution)
            @php
              $roomLabels = collect();
              foreach ($con->items as $line) {
                foreach ($line->distributedRoomLabels() as $label) {
                  $roomLabels->push($label);
                }
              }
              $roomLabels = $roomLabels->unique()->values();
            @endphp
            <td>{{ $roomLabels->isNotEmpty() ? $roomLabels->join(', ') : '—' }}</td>
          @endif
          <td>
            @php
              $itemCount = $con->itemCount();
              $totalQty = $con->totalQuantity();
              $lineSummaries = $con->itemLineSummaries();
            @endphp
            <strong>{{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }}</strong>
            <span class="text-muted"> · {{ \App\Models\HotelStoreConsumption::formatQuantity($totalQty) }} total qty</span>
            @if($lineSummaries !== [])
              <div class="text-muted small mt-1">{{ implode(' · ', $lineSummaries) }}</div>
            @endif
          </td>
          <td class="text-center">
            <a href="#" class="stores-icon-btn text-info js-consumption-view" data-consumption-id="{{ $con->id }}" title="View details"><i class="fa fa-eye"></i></a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="{{ $consumptionShowRoomDistribution ? 6 : 5 }}" class="text-center text-muted py-4">{!! $consumptionEmptyMessage !!}</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($storeConsumptionsList && method_exists($storeConsumptionsList, 'hasPages') && $storeConsumptionsList->hasPages())
  <div class="px-3 pb-3">{{ $storeConsumptionsList->links() }}</div>
@endif

@if(! $consumptionHideDeptStockSection)
  <h6 class="px-3 pt-3 mb-2 font-weight-bold">Department stock on hand</h6>
  <p class="px-3 text-muted small mb-2">Updated when indents are received and when consumption is recorded.</p>

  <div class="stores-table-wrap table-responsive px-3 pb-3">
    <table class="table table-bordered table-hover stores-master-table mb-0">
      <thead>
        <tr>
          <th>Department</th>
          <th>Item</th>
          <th>Category</th>
          <th class="text-right">Quantity</th>
          <th>Unit</th>
        </tr>
      </thead>
      <tbody>
        @forelse($consumptionData['rows'] as $row)
          @php
            $item = $row->masterItem;
            $qty = (float) $row->quantity;
            $qtyDisplay = abs($qty - round($qty)) < 0.001 ? (string) (int) round($qty) : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
            $unit = $item?->storePackage?->name ?: '—';
          @endphp
          <tr>
            <td>{{ $row->department?->name ?? '—' }}</td>
            <td>{{ $item?->name ?? '—' }}</td>
            <td>{{ $item?->category?->name ?? '—' }}</td>
            <td class="text-right"><strong>{{ $qtyDisplay }}</strong></td>
            <td>{{ $unit }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">No department stock. Receive a store request first.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif

@include('hotel.erp.stores.partials.create-consumption-modal')
@include('hotel.erp.stores.partials.consumption-view-modal')

@push('scripts')
<script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
<script>
(function ($) {
  var catalogByDept = @json($consumptionStockCatalog ?? []);
  var consumptionShowRoom = @json($consumptionShowRoomDistribution);
  @php
    $consumptionRoomLabelMap = collect($consumptionRoomOptions)->mapWithKeys(function ($r) {
        return [(string) $r['value'] => ($r['consumption_label'] ?? $r['label'])];
    });
  @endphp
  var consumptionRoomLabels = @json($consumptionRoomLabelMap);
  var consumptionItems = [];
  var consumptionIdx = 0;

  function roomLabelsForIds(roomIds) {
    return (roomIds || []).map(function (id) {
      return consumptionRoomLabels[String(id)] || consumptionRoomLabels[id] || ('Room #' + id);
    });
  }

  function initConsumptionRoomSelect() {
    var $sel = $('#storesConsumptionRoomSelect');
    if (!$sel.length || !consumptionShowRoom) return;
    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
    $sel.select2({
      width: '100%',
      placeholder: 'Select room(s) or use All rooms',
      allowClear: true,
      dropdownParent: $('#storesCreateConsumptionModal')
    });
  }

  function syncConsumptionAllRoomsUi() {
    var all = $('#storesConsumptionAllRooms').prop('checked');
    var $sel = $('#storesConsumptionRoomSelect');
    if (!$sel.length) return;
    $sel.prop('disabled', all);
    if (all) {
      $sel.val(null).trigger('change.select2');
    }
  }

  function deptCatalog() {
    var deptId = parseInt($('#storesConsumptionDepartment').val(), 10) || 0;
    return catalogByDept[deptId] || catalogByDept[String(deptId)] || [];
  }

  function filteredCatalog() {
    var $catEl = $('#storesConsumptionCategory');
    var cat = $catEl.length ? (parseInt($catEl.val(), 10) || 0) : 0;
    return deptCatalog().filter(function (row) {
      if (cat > 0 && row.category_id !== cat) return false;
      return true;
    });
  }

  function selectedItem() {
    var id = parseInt($('#storesConsumptionItemSelect').val(), 10);
    if (!id) return null;
    return deptCatalog().find(function (r) { return r.id === id; }) || null;
  }

  function qtyInCart(masterId) {
    return consumptionItems.reduce(function (sum, row) {
      return row.master_item_id === masterId ? sum + row.quantity : sum;
    }, 0);
  }

  function formatQtyWithUnit(qty, unit) {
    var q = parseFloat(qty) || 0;
    var display = q % 1 === 0 ? String(q) : String(q);
    return unit ? display + ' ' + unit : display;
  }

  function remainingForItem(item) {
    if (!item) return 0;
    return Math.max(0, (parseFloat(item.stock) || 0) - qtyInCart(item.id));
  }

  function updateQtyHint() {
    var item = selectedItem();
    var $hint = $('#storesConsumptionQtyHint');
    var $qty = $('.js-stores-consumption-qty');
    if (!item) {
      $hint.text('');
      $qty.removeClass('is-invalid');
      return;
    }
    var remaining = remainingForItem(item);
    var qty = parseFloat($qty.val()) || 0;
    if (qty > remaining) {
      $hint.text('Only ' + formatQtyWithUnit(remaining, item.unit) + ' available.').addClass('text-danger');
      $qty.addClass('is-invalid');
    } else {
      $hint.text('Available: ' + formatQtyWithUnit(remaining, item.unit) + '.').removeClass('text-danger');
      $qty.removeClass('is-invalid');
    }
  }

  function rebuildItemSelect() {
    var $sel = $('#storesConsumptionItemSelect');
    var deptId = parseInt($('#storesConsumptionDepartment').val(), 10) || 0;
    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
    $sel.empty();
    if (deptId <= 0) {
      $sel.prop('disabled', true).append($('<option value="">Select department first</option>'));
      return;
    }
    var list = filteredCatalog();
    $sel.prop('disabled', false).append($('<option value="">Select item</option>'));
    list.forEach(function (row) {
      $sel.append($('<option></option>').val(row.id).text(row.name));
    });
    $sel.select2({ width: '100%', placeholder: 'Select item', allowClear: true, dropdownParent: $('#storesCreateConsumptionModal') });
    $sel.val('').trigger('change.select2');
  }

  function renderTable() {
    var $body = $('#storesConsumptionItemsBody');
    $body.empty();
    var sumQty = 0;
    consumptionItems.forEach(function (item) {
      sumQty += item.quantity;
      var $tr = $('<tr></tr>');
      $tr.append($('<td></td>').text(item.name).append(
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][name]').val(item.name),
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][master_item_id]').val(item.master_item_id),
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][unit]').val(item.unit || '')
      ));
      $tr.append($('<td></td>').text(formatQtyWithUnit(item.quantity, item.unit)).append(
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][quantity]').val(item.quantity)
      ));
      if (consumptionShowRoom) {
        var roomText = item.room_all ? 'All rooms' : (item.room_labels && item.room_labels.length ? item.room_labels.join(', ') : '—');
        var $roomCell = $('<td></td>').text(roomText);
        $roomCell.append($('<input type="hidden">').attr('name', 'items[' + item.i + '][room_all]').val(item.room_all ? '1' : '0'));
        (item.room_unit_ids || []).forEach(function (rid) {
          $roomCell.append($('<input type="hidden">').attr('name', 'items[' + item.i + '][room_unit_ids][]').val(rid));
        });
        $tr.append($roomCell);
      }
      $tr.append($('<td></td>').text(item.comments || '').append(
        $('<input type="hidden">').attr('name', 'items[' + item.i + '][comments]').val(item.comments || '')
      ));
      $tr.append($('<td class="text-center"></td>').append(
        $('<button type="button" class="stores-icon-btn text-danger"><i class="fa fa-trash"></i></button>').on('click', function () {
          consumptionItems = consumptionItems.filter(function (r) { return r.i !== item.i; });
          renderTable();
        })
      ));
      $body.append($tr);
    });
    $('#storesConsumptionSumQty').text(sumQty % 1 === 0 ? sumQty : sumQty);
    $('#storesConsumptionSubmit').prop('disabled', consumptionItems.length === 0);
    updateQtyHint();
  }

  function resetModal() {
    consumptionItems = [];
    consumptionIdx = 0;
    $('#storesConsumptionForm')[0].reset();
    if ($('#storesConsumptionCategory').length) $('#storesConsumptionCategory').val('0');
    $('.js-stores-consumption-qty').val('0');
    if ($('.js-stores-consumption-unit').length) $('.js-stores-consumption-unit').val('');
    $('#storesConsumptionQtyHint').text('').removeClass('text-danger');
    rebuildItemSelect();
    initConsumptionRoomSelect();
    $('#storesConsumptionAllRooms').prop('checked', false);
    syncConsumptionAllRoomsUi();
    renderTable();
  }

  $('#storesCreateConsumptionModal').on('show.bs.modal', resetModal);
  $('#storesConsumptionAllRooms').on('change', syncConsumptionAllRoomsUi);
  $('#storesConsumptionDepartment').on('change', function () {
    rebuildItemSelect();
    updateQtyHint();
  });
  if ($('#storesConsumptionCategory').is('select')) {
    $('#storesConsumptionCategory').on('change', rebuildItemSelect);
  }
  $('#storesConsumptionItemSelect').on('change', function () {
    var item = selectedItem();
    var $unit = $('.js-stores-consumption-unit');
    if ($unit.length) $unit.val(item ? item.unit : '');
    updateQtyHint();
  });
  $('.js-stores-consumption-qty').on('input', updateQtyHint);

  $('.js-stores-consumption-add').on('click', function () {
    var item = selectedItem();
    var qty = parseFloat($('.js-stores-consumption-qty').val()) || 0;
    if (!item || qty <= 0) return;
    if (qty > remainingForItem(item)) {
      updateQtyHint();
      return;
    }
    var roomAll = consumptionShowRoom && $('#storesConsumptionAllRooms').prop('checked');
    var roomIds = consumptionShowRoom && !roomAll ? ($('#storesConsumptionRoomSelect').val() || []) : [];
    if (!Array.isArray(roomIds)) roomIds = roomIds ? [roomIds] : [];
    roomIds = roomIds.map(function (v) { return parseInt(v, 10); }).filter(function (v) { return v > 0; });
    consumptionItems.push({
      i: consumptionIdx++,
      master_item_id: item.id,
      name: item.name,
      quantity: qty,
      unit: item.unit || '',
      comments: ($('.js-stores-consumption-line-comment').val() || '').trim(),
      room_all: roomAll,
      room_unit_ids: roomIds,
      room_labels: roomAll ? ['All rooms'] : roomLabelsForIds(roomIds)
    });
    $('#storesConsumptionItemSelect').val(null).trigger('change');
    $('.js-stores-consumption-qty').val('0');
    $('.js-stores-consumption-line-comment').val('');
    if (consumptionShowRoom) {
      $('#storesConsumptionRoomSelect').val(null).trigger('change');
      $('#storesConsumptionAllRooms').prop('checked', false);
      syncConsumptionAllRoomsUi();
    }
    renderTable();
  });

  initConsumptionRoomSelect();

  var consumptionDetailRoute = @json($consumptionDetailRoute);
  var consumptionAutoOpenId = @json($consumptionAutoOpenId);
  var consumptionDetailLoading = false;

  function stripConsumptionViewFromUrl() {
    if (!window.history.replaceState) return;
    var url = new URL(window.location.href);
    if (!url.searchParams.has('view_consumption')) return;
    url.searchParams.delete('view_consumption');
    window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash);
  }

  function openConsumptionDetail(id) {
    id = parseInt(id, 10);
    if (!id || consumptionDetailLoading) return;
    consumptionDetailLoading = true;
    var url = consumptionDetailRoute.replace('__ID__', String(id));
    var $modal = $('#storesConsumptionDetailModal');
    var $mount = $('#storesConsumptionDetailModalMount');
    $mount.html('<div class="modal-content"><div class="modal-body text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x"></i></div></div>');
    $modal.modal('show');
    $.get(url).done(function (html) {
      $mount.html(html);
    }).fail(function () {
      $mount.html('<div class="modal-content"><div class="modal-body"><div class="alert alert-danger mb-0">Could not load consumption details.</div></div><div class="modal-footer"><button type="button" class="btn btn-stores-cancel" data-dismiss="modal">Close</button></div></div>');
    }).always(function () {
      consumptionDetailLoading = false;
    });
  }

  $(document).on('click', '.js-consumption-view', function (e) {
    e.preventDefault();
    openConsumptionDetail($(this).data('consumption-id'));
  });

  $('#storesConsumptionDetailModal').on('hidden.bs.modal', function () {
    stripConsumptionViewFromUrl();
    $('#storesConsumptionDetailModalMount').html('<div class="modal-content"><div class="modal-body text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x"></i></div></div>');
  });

  if (consumptionAutoOpenId > 0) {
    openConsumptionDetail(consumptionAutoOpenId);
  }
})(jQuery);
</script>
@endpush
