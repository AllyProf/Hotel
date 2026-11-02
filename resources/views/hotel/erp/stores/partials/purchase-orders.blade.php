@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $purchaseOrders = $purchaseOrders ?? null;
  $poSearch = $poSearch ?? '';
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $poStatus = $poStatus ?? 'all';
  $poStatusFilters = $poStatusFilters ?? config('hotel_stores.po_status_filters', []);
  $poVendors = $poVendors ?? collect();
  $storePoPermissions = $storePoPermissions ?? ['create' => true, 'approve' => true, 'invoice' => true, 'edit' => true, 'delete' => true];
  $currency = strtoupper(trim((string) ($hotel->currency ?? 'TZS'))) ?: 'TZS';
  $poMoneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;
  $poEditPayload = [];
  $storeKeeperPurchaseOrders = $storeKeeperPurchaseOrders ?? collect();
  foreach ($storeKeeperPurchaseOrders as $po) {
      $po->loadMissing('items');
      $deptId = $po->hotel_store_category_id;
      if (! $deptId && $po->items->isNotEmpty()) {
        $firstMasterId = (int) ($po->items->first()->hotel_store_master_item_id ?? 0);
        if ($firstMasterId > 0) {
          $deptId = \App\Models\HotelStoreMasterItem::query()->whereKey($firstMasterId)->value('hotel_store_category_id');
        }
      }
      $poEditPayload[$po->id] = [
        'vendor_id' => $po->hotel_vendor_id,
        'department_id' => $deptId,
        'items' => $po->items->map(fn ($line) => [
          'master_item_id' => $line->hotel_store_master_item_id,
          'name' => $line->name,
          'quantity' => (float) $line->quantity,
          'rate' => (float) $line->rate,
          'pre_tax' => (float) $line->pre_tax,
          'tax' => (float) $line->tax,
          'total' => (float) $line->total,
        ])->values()->all(),
      ];
  }
@endphp

@if(session('success'))
  <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
@endif
@if(session('warning'))
  <div class="alert alert-warning mx-3 mt-3 mb-0">{{ session('warning') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
@endif

@include('hotel.erp.stores.partials.department-purchase-lists')

@include('hotel.erp.stores.partials.create-po-modal')
@include('hotel.erp.stores.partials.invoice-detail-modal', ['invoiceModalTab' => 'purchase-orders'])

@push('scripts')
<script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
<script>
(function ($) {
  var catalog = @json($poMasterItemsPayload ?? []);
  var poEditPayload = @json($poEditPayload);
  var poMoneyDecimals = @json($poMoneyDecimals);
  var poItems = [];
  var poIdx = 0;
  var poEditingKey = null;

  function setPoAddButtonLabel() {
    $('.js-stores-po-add').text(poEditingKey !== null ? 'Update item' : 'Add item');
  }

  function clearPoLineEdit() {
    poEditingKey = null;
    setPoAddButtonLabel();
  }

  function startPoLineEdit(item) {
    poEditingKey = item.i;
    if (item.master_item_id) {
      var masterId = String(item.master_item_id);
      if (!$('#storesPoItemSelect option[value="' + masterId + '"]').length) {
        $('#storesPoItemSelect').append(
          $('<option></option>').val(masterId).text(item.name)
        );
        initPoItemSelect2();
      }
      $('#storesPoItemSelect').val(masterId).trigger('change.select2');
    } else {
      $('#storesPoItemSelect').val('').trigger('change.select2');
    }
    $('.js-stores-po-qty').val(item.quantity);
    $('.js-stores-po-rate').val(item.rate > 0 ? item.rate : '');
    $('.js-stores-po-tax').val(moneyRaw(item.tax));
    recalcPoEntry();
    renderPoTable();
    setPoAddButtonLabel();
  }

  function money(v) {
    v = parseFloat(v) || 0;
    var factor = Math.pow(10, poMoneyDecimals);
    v = Math.round(v * factor) / factor;
    var parts = v.toFixed(poMoneyDecimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return poMoneyDecimals > 0 ? parts.join('.') : parts[0];
  }

  function moneyRaw(v) {
    v = parseFloat(v) || 0;
    var factor = Math.pow(10, poMoneyDecimals);
    return String(Math.round(v * factor) / factor);
  }

  function linePreTax() {
    var qty = parseFloat($('.js-stores-po-qty').val()) || 0;
    var rate = parseFloat($('.js-stores-po-rate').val()) || 0;
    return qty * rate;
  }

  function calcTax(preTax, taxRate, taxInclusive) {
    taxRate = parseFloat(taxRate) || 0;
    if (taxRate <= 0 || preTax <= 0) return 0;
    if (taxInclusive) {
      return preTax - (preTax / (1 + taxRate / 100));
    }
    return preTax * (taxRate / 100);
  }

  function selectedCatalogItem() {
    var id = $('#storesPoItemSelect').val();
    if (!id) return null;
    id = parseInt(id, 10);
    return catalog.find(function (row) { return row.id === id; }) || null;
  }

  function destroyPoItemSelect2() {
    var $sel = $('#storesPoItemSelect');
    if ($sel.hasClass('select2-hidden-accessible')) {
      $sel.select2('destroy');
    }
  }

  function initPoItemSelect2() {
    var $sel = $('#storesPoItemSelect');
    if (!$.fn.select2) return;
    destroyPoItemSelect2();
    $sel.select2({
      width: '100%',
      placeholder: 'Select item',
      allowClear: true,
      dropdownParent: $('#storesCreatePoModal')
    });
  }

  function rebuildItemSelect() {
    var vendorId = String($('#storesPoVendor').val() || '');
    var deptId = String($('#storesPoDepartment').val() || '');
    var $sel = $('#storesPoItemSelect');
    var prev = $sel.val();
    destroyPoItemSelect2();
    $sel.find('option:not(:first)').remove();
    catalog.forEach(function (row) {
      if (deptId && row.category_id && String(row.category_id) !== deptId) {
        return;
      }
      if (vendorId && row.vendor_id && String(row.vendor_id) !== vendorId) {
        return;
      }
      $sel.append($('<option></option>').val(row.id).text(row.name));
    });
    initPoItemSelect2();
    if (prev && $sel.find('option[value="' + prev + '"]').length) {
      $sel.val(prev).trigger('change.select2');
    } else {
      $sel.val('').trigger('change.select2');
    }
  }

  function recalcPoEntry() {
    var pre = linePreTax();
    var item = selectedCatalogItem();
    var taxRate = item ? item.tax_rate : 0;
    var taxInclusive = item ? item.tax_inclusive : false;
    var tax = calcTax(pre, taxRate, taxInclusive);
    if (!$('.js-stores-po-tax').is(':focus')) {
      $('.js-stores-po-tax').val(moneyRaw(tax));
    } else {
      tax = parseFloat($('.js-stores-po-tax').val()) || 0;
    }
    $('.js-stores-po-line-total').val(money(pre + tax));
  }

  function renderPoTable() {
    var $body = $('#storesPoItemsBody');
    $body.empty();
    var sumQty = 0, sumTax = 0, sumTotal = 0;

    poItems.forEach(function (item) {
      sumQty += item.quantity;
      sumTax += item.tax;
      sumTotal += item.total;
    });

    poItems.forEach(function (item) {
      if (poEditingKey !== null && item.i === poEditingKey) {
        return;
      }

      var $tr = $('<tr></tr>');
      $tr.append(
        $('<td></td>').text(item.name).append(
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][name]').val(item.name),
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][pre_tax]').val(item.pre_tax),
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][master_item_id]').val(item.master_item_id || '')
        )
      );
      $tr.append(
        $('<td></td>').text(item.quantity).append(
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][quantity]').val(item.quantity)
        )
      );
      $tr.append(
        $('<td></td>').text(money(item.rate)).append(
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][rate]').val(item.rate)
        )
      );
      $tr.append(
        $('<td></td>').text(money(item.tax)).append(
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][tax]').val(item.tax)
        )
      );
      $tr.append(
        $('<td></td>').text(money(item.total)).append(
          $('<input type="hidden">').attr('name', 'items[' + item.i + '][total]').val(item.total)
        )
      );
      $tr.append(
        $('<td class="text-center text-nowrap stores-po-line-actions"></td>').append(
          $('<button type="button" class="stores-icon-btn text-primary mr-1" title="Edit"><i class="fa fa-pencil"></i></button>')
            .on('click', function () {
              startPoLineEdit(item);
            }),
          $('<button type="button" class="stores-icon-btn text-danger" title="Remove"><i class="fa fa-trash"></i></button>')
            .on('click', function () {
              if (poEditingKey === item.i) {
                clearPoLineEdit();
              }
              poItems = poItems.filter(function (row) { return row.i !== item.i; });
              renderPoTable();
            })
        )
      );
      $body.append($tr);
    });

    $('#storesPoSumQty').text(sumQty % 1 === 0 ? sumQty : money(sumQty));
    $('#storesPoSumTax').text(money(sumTax));
    $('#storesPoSumTotal').text(money(sumTotal));
    $('#storesPoSubmit').prop('disabled', poItems.length === 0);
  }

  var poStoreUrl = @json(route('hotel.stores.purchase-orders.store'));
  var poUpdateUrlTemplate = @json(url('/hotel/stores/purchase-orders/__ID__'));

  function loadPoItemsFromRows(rows) {
    poItems = [];
    poIdx = 0;
    (rows || []).forEach(function (row) {
      poItems.push({
        i: poIdx++,
        master_item_id: row.master_item_id || null,
        name: row.name,
        quantity: parseFloat(row.quantity) || 0,
        rate: parseFloat(row.rate) || 0,
        pre_tax: parseFloat(row.pre_tax) || 0,
        tax: parseFloat(row.tax) || 0,
        total: parseFloat(row.total) || 0
      });
    });
    renderPoTable();
  }

  function resetPoModal() {
    poItems = [];
    poIdx = 0;
    clearPoLineEdit();
    $('#storesPoForm').attr('action', poStoreUrl);
    $('#storesPoMethod').val('');
    $('#storesPoModalTitle').text('Create PO');
    $('#storesPoForm')[0].reset();
    $('.js-stores-po-qty').val('1');
    $('.js-stores-po-rate').val('');
    $('.js-stores-po-tax').val('0');
    rebuildItemSelect();
    recalcPoEntry();
    renderPoTable();
  }

  var poModalEditMode = false;

  $('#storesCreatePoModal').on('show.bs.modal', function (e) {
    if (poModalEditMode) {
      poModalEditMode = false;
      return;
    }
    var $trigger = $(e.relatedTarget);
    if ($trigger.length && ($trigger.is('[data-target="#storesCreatePoModal"]') || $trigger.is('.btn-stores-po-create'))) {
      resetPoModal();
    }
  });

  function openPoEditModal(orderId) {
    var payload = poEditPayload[orderId] || poEditPayload[String(orderId)];
    if (!payload) {
      return;
    }
    clearPoLineEdit();
    $('#storesPoForm').attr('action', poUpdateUrlTemplate.replace('__ID__', orderId));
    $('#storesPoMethod').val('PUT');
    $('#storesPoModalTitle').text('Edit PO');
    $('#storesPoVendor').val(payload.vendor_id ? String(payload.vendor_id) : '');
    $('#storesPoDepartment').val(payload.department_id ? String(payload.department_id) : '');
    rebuildItemSelect();
    loadPoItemsFromRows(payload.items || []);
    $('.js-stores-po-qty').val('1');
    $('.js-stores-po-rate').val('');
    $('.js-stores-po-tax').val('0');
    recalcPoEntry();
    poModalEditMode = true;
    $('#storesCreatePoModal').modal('show');
  }

  $(document).on('click', '.js-stores-po-edit', function () {
    openPoEditModal($(this).attr('data-id'));
  });

  function onPoVendorOrDepartmentChange() {
    poItems = [];
    poIdx = 0;
    renderPoTable();
    rebuildItemSelect();
    $('#storesPoItemSelect').trigger('change');
  }

  $('#storesPoVendor, #storesPoDepartment').on('change', onPoVendorOrDepartmentChange);

  $('#storesPoItemSelect').on('change', function () {
    var item = selectedCatalogItem();
    if (item) {
      $('.js-stores-po-rate').val(item.rate > 0 ? item.rate : '');
    }
    recalcPoEntry();
  });

  $('.js-stores-po-qty, .js-stores-po-rate').on('input', recalcPoEntry);
  $('.js-stores-po-tax').on('input', function () {
    var pre = linePreTax();
    var tax = parseFloat($('.js-stores-po-tax').val()) || 0;
    $('.js-stores-po-line-total').val(money(pre + tax));
  });

  $('.js-stores-po-add').on('click', function () {
    var catalogItem = selectedCatalogItem();
    var qty = parseFloat($('.js-stores-po-qty').val()) || 0;
    var rate = parseFloat($('.js-stores-po-rate').val()) || 0;
    var tax = parseFloat($('.js-stores-po-tax').val()) || 0;
    if (qty <= 0) return;
    if (rate <= 0) return;
    var pre = qty * rate;
    var lineName = catalogItem ? catalogItem.name : '';
    var masterId = catalogItem && catalogItem.id > 0 ? catalogItem.id : null;

    if (poEditingKey !== null) {
      var editIdx = poItems.findIndex(function (row) { return row.i === poEditingKey; });
      if (editIdx < 0) {
        clearPoLineEdit();
        return;
      }
      if (!catalogItem) {
        lineName = poItems[editIdx].name;
        masterId = poItems[editIdx].master_item_id;
      }
      poItems[editIdx] = {
        i: poEditingKey,
        master_item_id: masterId,
        name: lineName,
        quantity: qty,
        rate: rate,
        pre_tax: pre,
        tax: tax,
        total: pre + tax
      };
      clearPoLineEdit();
    } else {
      if (!catalogItem) return;
      poItems.push({
        i: poIdx++,
        master_item_id: masterId,
        name: lineName,
        quantity: qty,
        rate: rate,
        pre_tax: pre,
        tax: tax,
        total: pre + tax
      });
    }
    $('#storesPoItemSelect').val(null).trigger('change');
    $('.js-stores-po-qty').val('1');
    $('.js-stores-po-rate').val('');
    $('.js-stores-po-tax').val('0');
    recalcPoEntry();
    renderPoTable();
  });

  rebuildItemSelect();
})(jQuery);
</script>
@endpush
