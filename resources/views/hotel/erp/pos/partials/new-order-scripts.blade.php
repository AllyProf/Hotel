<script>
(function () {
  var catalog = @json($catalog['items']);
  var inHouseGuests = @json($inHouseGuestOptions ?? []);
  var taxPercent = {{ (float) $taxPercent }};
  var taxInclusive = @json($taxInclusive ?? false);
  var currencyDisplay = @json($currencyDisplay ?? ['code' => $currency, 'prefix' => null]);
  var lastMatches = [];

  var moneyDecimals = typeof currencyDisplay.decimals === 'number' ? currencyDisplay.decimals : 2;

  function fmtQty(n) {
    var q = parseFloat(n) || 0;
    if (Math.abs(q - Math.round(q)) < 0.0001) {
      return String(Math.round(q));
    }
    return q.toFixed(2);
  }

  function formatPosMoney(n) {
    var amount = parseFloat(n) || 0;
    var factor = Math.pow(10, moneyDecimals);
    var rounded = Math.round(amount * factor) / factor;
    var v = rounded.toLocaleString('en-US', {
      minimumFractionDigits: moneyDecimals,
      maximumFractionDigits: moneyDecimals
    });
    if (currencyDisplay.prefix) {
      return currencyDisplay.prefix + v;
    }
    if (currencyDisplay.code) {
      return v + ' ' + currencyDisplay.code;
    }
    return v;
  }
  function formatPosMoneyZero() {
    return formatPosMoney(0);
  }

  function roundForCurrency(amount) {
    var factor = Math.pow(10, moneyDecimals);
    return Math.round((parseFloat(amount) || 0) * factor) / factor;
  }

  var lineIndex = 0;
  var selectedItem = null;
  var complimentary = false;

  var $modal = $('#posNewOrderModal');
  var $tbody = $('#posLinesTable tbody');
  var $form = $('#posNewOrderForm');
  var $suggest = $('#posItemSuggestions');
  var $serviceInput = $('#posLineServiceSearch');
  var $inHouseGuestInput = $('#posInHouseGuestSearch');
  var $inHouseGuestSuggest = $('#posInHouseGuestSuggestions');
  var $inHouseGuestId = $('#posInHouseGuestId');
  var selectedInHouseGuest = null;
  var lastInHouseMatches = [];

  var uiNewOrder = @json(config('hotel_pos.ui.new_order', 'New Order'));
  var uiComplimentary = @json(config('hotel_pos.ui.complimentary_order_title', 'Complimentary order'));
  var uiEditOrder = @json(config('hotel_pos.ui.edit_order_title', 'Edit order'));
  var uiSubmit = @json(config('hotel_pos.ui.submit', 'Submit'));
  var uiUpdate = @json(config('hotel_pos.ui.update_order', 'Update'));
  var openOrderEdits = @json($openOrderEditById ?? []);
  var orderViews = @json($orderViewById ?? []);
  var defaultWaiterUserId = @json($defaultWaiterUserId ?? null);
  var requireWaiterOnOrder = @json($requireWaiterOnOrder ?? false);
  var posStoreUrl = @json(route('hotel.pos.orders.store'));
  var posUpdateUrlTemplate = @json($posUpdateOrderUrlTemplate ?? '');
  var skipNextModalReset = false;

  function resolveSelectedItem() {
    var catalogKey = $('#posLineCatalogKey').val();
    if (catalogKey) {
      var byKey = catalog.find(function (it) {
        return String(it.catalog_key) === String(catalogKey);
      });
      if (byKey) {
        selectedItem = byKey;
        return byKey;
      }
    }

    var itemId = $('#posLineItemId').val();
    var packageId = $('#posLinePackageId').val();
    if (itemId) {
      var byId = catalog.find(function (it) {
        if (String(it.id) !== String(itemId)) {
          return false;
        }
        if (packageId && it.package_id) {
          return String(it.package_id) === String(packageId);
        }
        return true;
      });
      if (byId) {
        selectedItem = byId;
        return byId;
      }
    }

    var cat = $('#posLineCategory').val();
    var name = $serviceInput.val().trim().toLowerCase();
    if (cat && name) {
      var byName = catalog.find(function (it) {
        return String(it.category_id) === String(cat) && (it.name || '').toLowerCase() === name;
      });
      if (byName) {
        pickItem(byName, false);
        return byName;
      }
    }

    return null;
  }

  function splitLineAmounts(gross, ratePercent, isComplimentary) {
    gross = parseFloat(gross) || 0;
    if (isComplimentary || ratePercent <= 0) {
      return { amt: gross, tax: 0, total: gross };
    }
    if (taxInclusive) {
      var net = gross / (1 + ratePercent / 100);
      var taxPart = gross - net;
      return { amt: net, tax: taxPart, total: gross };
    }
    var taxPart = gross * ratePercent / 100;
    return { amt: gross, tax: taxPart, total: gross + taxPart };
  }

  function recalcLinePreview() {
    var item = resolveSelectedItem();
    var qty = parseFloat($('#posLineQty').val()) || 0;
    var rate = item ? parseFloat(item.unit_price) || 0 : 0;
    if (complimentary) {
      rate = 0;
    }
    var parts = splitLineAmounts(qty * rate, taxPercent, complimentary);
    $('#posLineAmount').val(formatPosMoney(parts.amt));
    $('#posLineTax').val(formatPosMoney(parts.tax));
    $('#posLineTotal').val(formatPosMoney(parts.total));
  }

  function recalcFooters() {
    var qty = 0, amt = 0, tax = 0, total = 0;
    $tbody.find('tr').each(function () {
      qty += parseFloat($(this).data('qty')) || 0;
      amt += parseFloat($(this).data('amt')) || 0;
      tax += parseFloat($(this).data('tax')) || 0;
      total += parseFloat($(this).data('total')) || 0;
    });
    $('#posFootQty').text(fmtQty(qty));
    $('#posFootAmt').text(formatPosMoney(amt));
    $('#posFootTax').text(formatPosMoney(tax));
    $('#posFootTotal').text(formatPosMoney(total));
  }

  function filterItems(term, categoryId) {
    if (!categoryId) {
      return [];
    }
    term = (term || '').toLowerCase();
    return catalog.filter(function (it) {
      if (String(it.category_id) !== String(categoryId)) {
        return false;
      }
      if (!term) {
        return true;
      }
      return (it.name || '').toLowerCase().indexOf(term) !== -1;
    }).slice(0, 15);
  }

  function clearItemSelection() {
    selectedItem = null;
    $('#posLineItemId').val('');
    $('#posLinePackageId').val('');
    $('#posLineCatalogKey').val('');
    $('#posLineCategoryName').val('');
    $('#posLineUnitLabel').val('');
  }

  function pickItem(it, hideList) {
    if (!it) {
      return;
    }
    selectedItem = it;
    $serviceInput.val(it.name);
    $('#posLineItemId').val(it.id);
    $('#posLineCatalogKey').val(it.catalog_key || (it.id + '-' + (it.package_id || 0)));
    $('#posLineCategoryName').val(it.category_name || '');
    $('#posLinePackageId').val(it.package_id || '');
    $('#posLineUnitLabel').val(it.unit_label || '');
    if (it.category_id) {
      $('#posLineCategory').val(it.category_id);
    }
    if (hideList !== false) {
      $suggest.hide();
    }
    recalcLinePreview();
  }

  function renderSuggestions() {
    var cat = $('#posLineCategory').val();
    var matches = filterItems($serviceInput.val(), cat);
    lastMatches = matches;
    $suggest.empty();

    if (!cat) {
      $suggest.append('<li class="list-group-item text-muted small">Select a category first</li>').show();
      return;
    }
    if (!matches.length) {
      $suggest.append('<li class="list-group-item text-muted small">No in-stock item in this category</li>').show();
      return;
    }

    matches.forEach(function (it, index) {
      var $li = $('<li class="list-group-item list-group-item-action pos-suggest-item"></li>');
      $li.append($('<span class="pos-suggest-name"></span>').text(it.name));
      var meta = formatPosMoney(it.unit_price);
      if (typeof it.stock_on_hand === 'number' && it.stock_on_hand > 0) {
        meta += ' · ' + fmtQty(it.stock_on_hand) + ' in stock';
      }
      $li.append($('<span class="pos-suggest-price"></span>').text(meta));
      $li.data('index', index);
      $suggest.append($li);
    });
    $suggest.show();
  }

  $serviceInput.on('input', function () {
    var val = $(this).val().trim();
    if (selectedItem && selectedItem.name !== val) {
      clearItemSelection();
    }
    renderSuggestions();
    recalcLinePreview();
  });

  $serviceInput.on('focus', function () {
    renderSuggestions();
  });

  $suggest.on('mousedown', '.pos-suggest-item', function (e) {
    e.preventDefault();
  });

  $suggest.on('click', '.pos-suggest-item', function () {
    var index = $(this).data('index');
    if (index === undefined || !lastMatches[index]) {
      return;
    }
    pickItem(lastMatches[index]);
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('#posLineServiceSearch, #posItemSuggestions').length) {
      $suggest.hide();
    }
    if (!$(e.target).closest('#posInHouseGuestSearch, #posInHouseGuestSuggestions').length) {
      $inHouseGuestSuggest.hide();
    }
  });

  $form.on('change', '.js-pos-guest-type', syncPosGuestTypeFields);
  syncPosGuestTypeOptionStyle();

  $('#posLineCategory').on('change', function () {
    clearItemSelection();
    $serviceInput.val('');
    $suggest.hide();
    recalcLinePreview();
  });

  $('#posLineQty').on('change input', recalcLinePreview);

  function resetCreateFormMode() {
    $('#posEditOrderId').val('');
    $('#posFormMethodSpoof').val('POST');
    $form.attr('action', posStoreUrl);
    $('#posOrderSubmitBtn').text(uiSubmit);
  }

  function setEditFormMode(orderId) {
    $('#posEditOrderId').val(orderId);
    $('#posFormMethodSpoof').val('PUT');
    $form.attr('action', posUpdateUrlTemplate.replace('__ID__', String(orderId)));
    $('#posOrderSubmitBtn').text(uiUpdate);
  }

  function appendLineRow(name, qty, rate, meta, lineTaxRate) {
    meta = meta || {};
    if (complimentary) {
      rate = 0;
    }
    var appliedTaxRate = lineTaxRate !== undefined && lineTaxRate !== null
      ? parseFloat(lineTaxRate)
      : (complimentary ? 0 : taxPercent);
    var parts = splitLineAmounts(qty * rate, appliedTaxRate, complimentary);
    var amt = parts.amt;
    var tax = parts.tax;
    var total = parts.total;
    var idx = lineIndex++;

    var row = $('<tr></tr>').attr('data-qty', qty).attr('data-amt', amt).attr('data-tax', tax).attr('data-total', total);
    row.append('<td>' + $('<div>').text(name).html() + '</td>');
    row.append('<td class="text-right">' + formatPosMoney(rate) + '</td>');
    row.append('<td class="text-right">' + fmtQty(qty) + '</td>');
    row.append('<td class="text-right">' + formatPosMoney(amt) + '</td>');
    row.append('<td class="text-right">' + formatPosMoney(tax) + '</td>');
    row.append('<td class="text-right">' + formatPosMoney(total) + '</td>');
    var $actions = $('<td></td>');
    $actions.append($('<button type="button" class="btn btn-link text-danger btn-sm p-0 js-remove-line"><i class="fa fa-trash"></i></button>'));
    $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][item_name]').val(name));
    $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][quantity]').val(qty));
    $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][unit_price]').val(rate));
    $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][tax_rate]').val(appliedTaxRate));
    if (meta.item_id) {
      $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][item_id]').val(meta.item_id));
    }
    if (meta.category_id) {
      $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][category_id]').val(meta.category_id));
    }
    if (meta.category_name) {
      $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][category_name]').val(meta.category_name));
    }
    if (meta.package_id) {
      $actions.append($('<input type="hidden">').attr('name', 'lines[' + idx + '][package_id]').val(meta.package_id));
    }
    row.append($actions);
    $tbody.append(row);
  }

  function clearInHouseGuestSelection() {
    selectedInHouseGuest = null;
    $inHouseGuestId.val('');
    $inHouseGuestInput.val('');
    $inHouseGuestSuggest.hide().empty();
  }

  function filterInHouseGuests(term) {
    term = (term || '').toLowerCase().trim();
    return inHouseGuests.filter(function (g) {
      if (!term) {
        return true;
      }
      var blob = (g.label + ' ' + (g.room || '') + ' ' + (g.guest || '')).toLowerCase();
      return blob.indexOf(term) !== -1;
    }).slice(0, 15);
  }

  function pickInHouseGuest(guest, hideList) {
    if (!guest) {
      return;
    }
    selectedInHouseGuest = guest;
    $inHouseGuestId.val(String(guest.id));
    $inHouseGuestInput.val(guest.label);
    if (hideList !== false) {
      $inHouseGuestSuggest.hide();
    }
  }

  function renderInHouseGuestSuggestions() {
    var matches = filterInHouseGuests($inHouseGuestInput.val());
    lastInHouseMatches = matches;
    $inHouseGuestSuggest.empty();

    if (!inHouseGuests.length) {
      $inHouseGuestSuggest.append('<li class="list-group-item text-muted small">No checked-in guests</li>').show();
      return;
    }
    if (!matches.length) {
      $inHouseGuestSuggest.append('<li class="list-group-item text-muted small">No match</li>').show();
      return;
    }

    matches.forEach(function (g, index) {
      var $li = $('<li class="list-group-item list-group-item-action pos-suggest-item js-pos-inhouse-suggest"></li>');
      $li.append($('<span class="pos-suggest-name"></span>').text(g.label));
      if (g.room) {
        $li.append($('<span class="pos-suggest-price text-muted small"></span>').text('Room ' + g.room));
      }
      $li.data('index', index);
      $inHouseGuestSuggest.append($li);
    });
    $inHouseGuestSuggest.show();
  }

  function syncPosGuestTypeOptionStyle() {
    $('.pos-guest-type-option').each(function () {
      $(this).toggleClass('is-checked', $(this).find('input').is(':checked'));
    });
  }

  function resetWaiterSelect() {
    if (defaultWaiterUserId) {
      $('#posWaiterUserId').val(String(defaultWaiterUserId));
    } else {
      $('#posWaiterUserId').val('');
    }
  }

  function syncPosGuestTypeFields() {
    var type = $form.find('[name=guest_type]:checked').val() || 'walk_in';
    var inHouse = type === 'in_house';
    $('.js-pos-guest-walk-in-fields').toggleClass('d-none', inHouse);
    $('.js-pos-guest-inhouse-fields').toggleClass('d-none', !inHouse);
    syncPosGuestTypeOptionStyle();
    if (inHouse) {
      $('#posWalkInLabel').val('');
    } else {
      clearInHouseGuestSelection();
    }
  }

  function setGuestFieldsFromPayload(payload) {
    var type = payload.guest_type || (payload.cm_reservation_id ? 'in_house' : 'walk_in');
    $form.find('[name=guest_type][value="' + type + '"]').prop('checked', true);
    syncPosGuestTypeFields();
    if (type === 'in_house' && payload.cm_reservation_id) {
      var guest = inHouseGuests.find(function (g) {
        return String(g.id) === String(payload.cm_reservation_id);
      });
      if (guest) {
        pickInHouseGuest(guest, false);
      } else {
        clearInHouseGuestSelection();
        $inHouseGuestId.val(String(payload.cm_reservation_id));
      }
    } else {
      $('#posWalkInLabel').val('');
      clearInHouseGuestSelection();
    }
  }

  $inHouseGuestInput.on('input', function () {
    var val = $(this).val().trim();
    if (selectedInHouseGuest && selectedInHouseGuest.label !== val) {
      selectedInHouseGuest = null;
      $inHouseGuestId.val('');
    }
    renderInHouseGuestSuggestions();
  });

  $inHouseGuestInput.on('focus', function () {
    renderInHouseGuestSuggestions();
  });

  $inHouseGuestSuggest.on('mousedown', '.js-pos-inhouse-suggest', function (e) {
    e.preventDefault();
  });

  $inHouseGuestSuggest.on('click', '.js-pos-inhouse-suggest', function () {
    var index = $(this).data('index');
    if (index === undefined || !lastInHouseMatches[index]) {
      return;
    }
    pickInHouseGuest(lastInHouseMatches[index]);
  });

  function loadOrderIntoForm(payload) {
    $tbody.empty();
    lineIndex = 0;
    complimentary = !!payload.is_complimentary;
    $('#posComplimentaryFlag').val(complimentary ? 1 : 0);
    setGuestFieldsFromPayload(payload);
    $form.find('[name=details]').val(payload.details || '');
    if (payload.waiter_user_id) {
      $('#posWaiterUserId').val(String(payload.waiter_user_id));
    } else {
      resetWaiterSelect();
    }
    (payload.lines || []).forEach(function (line) {
      appendLineRow(
        line.item_name,
        parseFloat(line.quantity) || 1,
        parseFloat(line.unit_price) || 0,
        {
          item_id: line.item_id,
          category_id: line.category_id,
          category_name: line.category_name,
          package_id: line.package_id
        },
        line.tax_rate
      );
    });
    recalcFooters();
  }

  $('#posAddLineBtn').on('click', function () {
    var item = resolveSelectedItem();
    var name = $serviceInput.val().trim();
    if (!name || !item) {
      alert('Select an item from the list.');
      return;
    }
    var qty = parseFloat($('#posLineQty').val()) || 1;
    if (typeof item.stock_on_hand === 'number' && item.stock_on_hand > 0 && qty > item.stock_on_hand) {
      alert('Quantity cannot exceed bar stock on hand (' + fmtQty(item.stock_on_hand) + ').');
      return;
    }
    var rate = parseFloat(item.unit_price) || 0;
    appendLineRow(name, qty, rate, {
      item_id: item.id,
      category_id: item.category_id,
      category_name: item.category_name,
      package_id: item.package_id
    });
    $serviceInput.val('');
    clearItemSelection();
    $('#posLineQty').val(1);
    recalcLinePreview();
    recalcFooters();
  });

  function formatViewQty(qty) {
    var q = parseFloat(qty) || 0;
    if (Math.abs(q - Math.round(q)) < 0.0001) {
      return String(Math.round(q));
    }
    return q.toFixed(2);
  }

  function appendPosViewLineRows($body, lines) {
    $body.empty();
    (lines || []).forEach(function (line) {
      $body.append(
        $('<tr></tr>')
          .append($('<td></td>').text(line.service || ''))
          .append($('<td class="text-right"></td>').text(line.rate || ''))
          .append($('<td class="text-right"></td>').text(formatViewQty(line.qty)))
          .append($('<td class="text-right"></td>').text(line.amount || ''))
          .append($('<td class="text-right"></td>').text(line.tax || ''))
          .append($('<td class="text-right"></td>').text(line.total || ''))
      );
    });
  }

  function applyPosViewTotals(prefix, payload) {
    var totals = payload.line_totals || {};
    $('#' + prefix + 'FootQty').text(formatViewQty(totals.qty || 0));
    $('#' + prefix + 'FootAmt').text(totals.amount || formatPosMoneyZero());
    $('#' + prefix + 'FootTax').text(totals.tax || formatPosMoneyZero());
    $('#' + prefix + 'FootTotal').text(totals.total || formatPosMoneyZero());
  }

  function applyPosViewPayment(prefix, payload) {
    var payment = payload.payment || {};
    $('#' + prefix + 'PaymentType').text(payment.type || '—');
    $('#' + prefix + 'PaymentComments').text(payment.comments || '—');
    $('#' + prefix + 'PaymentAmount').text(payment.amount_plain || payment.amount || '—');
  }

  function showPosReceiptView(payload) {
    $('#posViewReceiptLayout').removeClass('d-none');
    $('#posViewInvoiceLayout').addClass('d-none');
    $('#posViewOrderDialog').addClass('pos-view-invoice-dialog');

    $('#posViewReceiptTitle').text('Current order · ' + (payload.order_no || ''));
    $('#posViewHeading').text(payload.heading || '');
    if (payload.address) {
      $('#posViewAddress').text(payload.address).removeClass('d-none');
    } else {
      $('#posViewAddress').addClass('d-none').text('');
    }
    if (payload.tax_id) {
      $('#posViewTax').text((payload.tax_id_label || 'GST') + ': ' + payload.tax_id).removeClass('d-none');
    } else {
      $('#posViewTax').addClass('d-none').text('');
    }
    $('#posViewOrderNoValue').text(payload.order_no || '—');
    $('#posViewDateValue').text(payload.date_label || '—');
    $('#posViewGuestValue').text(payload.guest_summary || '—');
    $('#posViewWaiterValue').text(payload.waiter_summary || '—');
    $('#posViewDetailsValue').val(payload.details || '');
    $('#posViewDetailsWrap').toggle(!!payload.details);

    var lines = payload.lines || [];
    if (lines.length) {
      $('#posViewEmpty').addClass('d-none');
      $('#posViewLinesTable').show();
      appendPosViewLineRows($('#posViewLinesBody'), lines);
    } else {
      $('#posViewLinesTable').hide();
      $('#posViewEmpty').removeClass('d-none');
    }
    applyPosViewTotals('posView', payload);
    applyPosViewPayment('posView', payload);

    if (payload.can_edit) {
      $('#posViewEditOrderBtn').removeClass('d-none').data('order-id', payload.id).attr('data-order-id', payload.id);
    } else {
      $('#posViewEditOrderBtn').addClass('d-none').data('order-id', '').attr('data-order-id', '');
    }

    if (payload.print_url) {
      $('#posViewPrintLink').attr('href', payload.print_url).removeClass('d-none');
    } else {
      $('#posViewPrintLink').addClass('d-none');
    }
  }

  function showPosInvoiceView(payload) {
    $('#posViewReceiptLayout').addClass('d-none');
    $('#posViewInvoiceLayout').removeClass('d-none');
    $('#posViewOrderDialog').addClass('pos-view-invoice-dialog');

    var title = payload.invoice_title || payload.invoice_no || payload.order_no || 'Invoice';
    $('#posInvoiceModalTitle').text(title);
    $('#posInvoiceDetails').val(payload.details || '');

    if (payload.add_service_url) {
      $('#posInvoiceAddService').attr('href', payload.add_service_url).removeClass('d-none');
    } else {
      $('#posInvoiceAddService').addClass('d-none');
    }

    appendPosViewLineRows($('#posInvoiceLinesBody'), payload.lines || []);
    applyPosViewTotals('posInvoice', payload);
    applyPosViewPayment('posInvoice', payload);

    if (payload.print_url) {
      $('#posInvoiceViewBill').attr('href', payload.print_url).show();
    } else {
      $('#posInvoiceViewBill').hide();
    }
  }

  $('#posInvoicePrintBtn').on('click', function () {
    var url = $('#posInvoiceViewBill').attr('href');
    if (!url || url === '#') {
      return;
    }
    var w = window.open(url, '_blank');
    if (w) {
      w.focus();
    }
  });

  $('.js-pos-view-order').on('click', function () {
    var id = $(this).data('order-id');
    var payload = orderViews[id] || orderViews[String(id)];
    if (!payload) {
      return;
    }
    if (payload.is_billed) {
      showPosInvoiceView(payload);
    } else {
      showPosReceiptView(payload);
    }
    $('#posViewOrderModal').modal('show');
  });

  function openPosOrderEditor(orderId) {
    var id = orderId;
    var payload = openOrderEdits[id] || openOrderEdits[String(id)];
    if (!payload) {
      alert('This order cannot be edited (only current open orders).');
      return;
    }
    $('#posViewOrderModal').modal('hide');
    resetCreateFormMode();
    setEditFormMode(id);
    loadOrderIntoForm(payload);
    $serviceInput.val('');
    clearItemSelection();
    $('#posLineQty').val(1);
    $suggest.hide();
    recalcLinePreview();
    $modal.find('.modal-title').text(uiEditOrder + ' · ' + payload.order_no);
    skipNextModalReset = true;
    setTimeout(function () {
      $modal.modal('show');
    }, 200);
  }

  $('.js-pos-edit-order').on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    openPosOrderEditor($(this).data('order-id'));
  });

  $(document).on('click', '.js-pos-view-edit-order', function (e) {
    e.preventDefault();
    e.stopPropagation();
    openPosOrderEditor($(this).data('order-id'));
  });

  $tbody.on('click', '.js-remove-line', function () {
    $(this).closest('tr').remove();
    recalcFooters();
  });

  $modal.on('show.bs.modal', function (e) {
    if (skipNextModalReset) {
      skipNextModalReset = false;
      return;
    }
    resetCreateFormMode();
    complimentary = $(e.relatedTarget).data('complimentary') === 1;
    $('#posComplimentaryFlag').val(complimentary ? 1 : 0);
    $tbody.empty();
    lineIndex = 0;
    var $cat = $('#posLineCategory');
    var $first = $cat.find('option[value!=""]').first();
    if ($first.length) {
      $cat.val($first.val());
    } else {
      $cat.val('');
    }
    $serviceInput.val('');
    clearItemSelection();
    $('#posLineQty').val(1);
    $suggest.hide();
    $('#posLineAmount').val(formatPosMoneyZero());
    $('#posLineTax').val(formatPosMoneyZero());
    $('#posLineTotal').val(formatPosMoneyZero());
    $form.find('[name=guest_type][value="walk_in"]').prop('checked', true);
    $('#posWalkInLabel').val('');
    clearInHouseGuestSelection();
    $form.find('[name=details]').val('');
    resetWaiterSelect();
    syncPosGuestTypeFields();
    recalcFooters();
    recalcLinePreview();
    $modal.find('.modal-title').text(complimentary ? uiComplimentary : uiNewOrder);
  });

  $form.on('submit', function () {
    if ($tbody.find('tr').length === 0) {
      alert('Add at least one service line.');
      return false;
    }
    var guestType = $form.find('[name=guest_type]:checked').val();
    if (requireWaiterOnOrder && !$('#posWaiterUserId').val()) {
      alert('Select the waiter who took this order.');
      return false;
    }
    if (guestType === 'in_house' && !$inHouseGuestId.val()) {
      alert('Select an in-house guest from the list.');
      return false;
    }
    return true;
  });

  $('.js-pos-pay').on('click', function () {
    var id = $(this).data('order-id');
    var balance = $(this).data('balance');
    var orderNo = $(this).data('order-no');
    $('#posPaymentForm').attr('action', '{{ url('/hotel/pos/orders') }}/' + id + '/pay').data('order-id', id);
    $('#posPaymentAmount').val(roundForCurrency(balance));
    $('#posPaymentComments').val('Payment for ' + orderNo);
  });

  function syncPosPaymentTypeFields() {
    var type = $('#posPaymentType').val();
    $('#posRoomBillGroup').toggleClass('d-none', type !== 'room_bill');
    $('#posCompanyBillGroup').toggleClass('d-none', type !== 'bill_to_company');
    if (type !== 'room_bill') {
      $('#posRoomBillSelect').val('');
    } else {
      var orderId = $('#posPaymentForm').data('order-id');
      var editPayload = orderId ? (openOrderEdits[orderId] || openOrderEdits[String(orderId)]) : null;
      if (editPayload && editPayload.cm_reservation_id) {
        $('#posRoomBillSelect').val(String(editPayload.cm_reservation_id));
      }
    }
    if (type !== 'bill_to_company') {
      $('#posCompanyBillSelect').val('');
    }
  }

  $('#posPaymentType').on('change', syncPosPaymentTypeFields);
  $('#posPaymentModal').on('show.bs.modal', syncPosPaymentTypeFields);
  syncPosPaymentTypeFields();

  $('#posRunReportBtn').on('click', function () {
    var outlet = $('select[name=department]').val();
    var from = $('input[name=from]').val();
    var to = $('input[name=to]').val();
    var type = $('#posReportType').val();
    var url = '{{ route('hotel.pos.report') }}?' + $.param({ department: outlet, from: from, to: to, type: type });
    $.getJSON(url, function (data) {
      var $thead = $('#posReportThead').empty();
      var $tbody = $('#posReportTbody').empty();
      var $tfoot = $('#posReportTfoot').empty();
      if (data.type === 'payments') {
        $('#posReportModalTitle').text('Payments — ' + from + ' to ' + to);
        $thead.append('<tr><th>Type of Payment</th><th class="text-right">Amount</th></tr>');
        (data.rows || []).forEach(function (r) {
          $tbody.append('<tr><td>' + r.type + '</td><td class="text-right">' + formatPosMoney(r.amount) + '</td></tr>');
        });
        $tfoot.append('<tr class="font-weight-bold"><td>Total</td><td class="text-right">' + formatPosMoney(data.total || 0) + '</td></tr>');
      } else {
        $('#posReportModalTitle').text('Item Consumption — ' + from + ' to ' + to);
        $thead.append('<tr><th>Service Name</th><th class="text-right">Qty</th><th class="text-right">Amount</th><th class="text-right">Tax</th><th class="text-right">Total</th></tr>');
        (data.rows || []).forEach(function (r) {
          var qtyCell = r.qty_display || fmtQty(r.qty);
          $tbody.append('<tr><td>' + r.item_name + '</td><td class="text-right">' + qtyCell + '</td><td class="text-right">' + formatPosMoney(r.amount) + '</td><td class="text-right">' + formatPosMoney(r.tax) + '</td><td class="text-right">' + formatPosMoney(r.total) + '</td></tr>');
        });
      }
      $('#posReportModal').modal('show');
    });
  });

  var $searchInputs = $('input[form="posSearchForm"]');
  $searchInputs.on('input', function () {
    var v = $(this).val();
    $searchInputs.not(this).val(v);
  });
  $searchInputs.on('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      $('#posSearchForm').submit();
    }
  });
})();
</script>
