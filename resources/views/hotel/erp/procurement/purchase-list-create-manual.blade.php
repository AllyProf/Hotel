@extends('layouts.app')

@section('title', 'New purchase list')

@push('styles')
  <link rel="stylesheet" href="{{ asset('panel-assets/css/select2.min.css') }}">
  <style>
    .manual-pl-item-select-wrap .select2-container {
      width: 100% !important;
    }
    .manual-pl-qty-input::-webkit-outer-spin-button,
    .manual-pl-qty-input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .manual-pl-qty-input {
      -moz-appearance: textfield;
      appearance: textfield;
    }
    .manual-pl-qty-change {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #940000;
      margin-top: 2px;
      line-height: 1.2;
    }
  </style>
@endpush

@section('content')
  @php
    $procW = $procWorkflow ?? 'hk';
    $indexUrl = \App\Support\DepartmentProcurement::indexUrl($procW, 'purchase-lists');
    $reviseList = $reviseList ?? null;
    $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
    $storeUrl = $reviseList ? $procRoute('purchase-lists.resubmit', $reviseList) : $procRoute('purchase-lists.store');
    $lockDept = (bool) ($lockRequestingDepartment ?? false);
    $deptId = (int) old('hotel_department_id', $defaultDepartmentId ?? 0);
    $deptName = $requestingDepartmentName ?? ($departments->firstWhere('id', $deptId)?->name ?? 'Department');
    $defaultLineRow = [['item_name' => '', 'quantity' => '1', 'unit' => '', 'line_notes' => '']];
    if ($errors->isNotEmpty()) {
      $oldLines = old('lines', $initialFormLines ?? $defaultLineRow);
    } elseif (isset($initialFormLines) && is_array($initialFormLines) && $initialFormLines !== []) {
      $oldLines = $initialFormLines;
    } else {
      $oldLines = old('lines', $defaultLineRow);
    }
    if (! is_array($oldLines) || $oldLines === []) {
      $oldLines = $defaultLineRow;
    }
    if ($reviseList && isset($initialFormLines) && is_array($initialFormLines)) {
      foreach ($oldLines as $idx => &$mergeLine) {
        if (is_array($mergeLine) && ! isset($mergeLine['previous_quantity']) && isset($initialFormLines[$idx]['previous_quantity'])) {
          $mergeLine['previous_quantity'] = $initialFormLines[$idx]['previous_quantity'];
        }
      }
      unset($mergeLine);
    }
    $formatPlQty = static function ($qty): string {
      if ($qty === null || $qty === '' || ! is_numeric($qty)) {
        return '1';
      }
      $formatted = rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');

      return $formatted !== '' ? $formatted : '1';
    };
    $itemNames = $suggestedItemNames ?? collect();
    $catalogByDepartment = $catalogByDepartment ?? [];
    $deptCatalog = collect($catalogByDepartment[$deptId] ?? $catalogByDepartment[(string) $deptId] ?? [])
      ->keyBy(fn ($row) => mb_strtolower(trim((string) ($row['name'] ?? ''))));
  @endphp
  <div class="app-title">
    <div>
      <h1><i class="fa fa-paper-plane"></i> {{ $reviseList ? 'Edit & send · '.$reviseList->reference_no : 'New purchase list · '.($procLabel ?? '') }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ $indexUrl }}">{{ $procLabel ?? 'Procurement' }}</a></li>
      <li class="breadcrumb-item">New list</li>
    </ul>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($lockDept && $deptId <= 0)
    <div class="alert alert-warning">{{ $procLabel ?? 'This' }} department is not set up yet. Check HR departments or open {{ $procLabel ?? 'the module' }} once.</div>
  @else
  <div class="tile">
    <form method="POST" action="{{ $storeUrl }}" id="manualPlForm" class="js-swal-confirm"
      data-title="{{ $reviseList ? 'Send to store again?' : 'Send purchase list?' }}"
      data-text="{{ $reviseList ? 'Update this list and send it back to the store team.' : 'Email this list to the configured recipients?' }}"
      data-confirm="{{ $reviseList ? 'Send to store' : 'Yes, send' }}">
      @csrf

      @if($reviseList)
        @if($returnMessage = $reviseList->storeReturnNotes())
          <div class="alert alert-warning py-2">
            <strong>Store message:</strong> {{ $returnMessage }}
          </div>
        @endif
        <p class="text-muted small mb-3">Adjust items, then send to the store again.</p>
      @endif

      @if($lockDept)
        <input type="hidden" name="hotel_department_id" value="{{ $deptId }}">
        <div class="form-group">
          <label class="control-label">Requesting department</label>
          <p class="form-control-plaintext font-weight-bold mb-0">{{ $deptName }}</p>
        </div>
      @else
        <div class="form-group">
          <label class="control-label">Department</label>
          <select class="form-control" name="hotel_department_id" id="manualPlDepartment" required>
            @foreach($departments as $department)
              <option value="{{ $department->id }}" @selected($deptId === (int) $department->id)>
                {{ $department->name }}
              </option>
            @endforeach
          </select>
        </div>
      @endif

      <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 mt-3">
        <label class="control-label mb-0">Items to purchase</label>
        <button type="button" class="btn btn-sm btn-light border" id="addPlLine"><i class="fa fa-plus"></i> Add line</button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0" id="manualPlLines">
          <thead>
            <tr>
              <th style="min-width:220px">Item</th>
              <th style="width:120px">Qty</th>
              <th style="width:100px">Unit</th>
              <th>Notes</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($oldLines as $index => $line)
              @php
                $prevQtyRaw = $line['previous_quantity'] ?? null;
                $prevQtyLabel = $prevQtyRaw !== null && $prevQtyRaw !== '' ? $formatPlQty($prevQtyRaw) : null;
                $currQtyLabel = $errors->isNotEmpty()
                  ? $formatPlQty(old('lines.'.$index.'.quantity', $line['quantity'] ?? '1'))
                  : $formatPlQty($line['quantity'] ?? '1');
              @endphp
              <tr class="pl-line">
                <td>
                  <div class="manual-pl-item-select-wrap">
                    <select class="form-control js-manual-pl-item-select" name="lines[{{ $index }}][item_name]" required
                      data-placeholder="Select or type item…">
                      <option value=""></option>
                      @if(filled($line['item_name'] ?? null))
                        <option value="{{ $line['item_name'] }}" selected>{{ $line['item_name'] }}</option>
                      @endif
                      @foreach($itemNames as $name)
                        @if(($line['item_name'] ?? '') !== $name)
                          <option value="{{ $name }}">{{ $name }}</option>
                        @endif
                      @endforeach
                    </select>
                  </div>
                </td>
                <td>
                  <input class="form-control manual-pl-qty-input" type="text" inputmode="decimal" autocomplete="off"
                    name="lines[{{ $index }}][quantity]" value="{{ $currQtyLabel }}" required
                    @if($reviseList && $prevQtyLabel !== null) data-previous-qty="{{ $prevQtyLabel }}" @endif>
                  @if($reviseList && $prevQtyLabel !== null)
                    <small class="manual-pl-qty-change {{ $prevQtyLabel !== $currQtyLabel ? '' : 'd-none' }}">{{ $prevQtyLabel }}-{{ $currQtyLabel }}</small>
                  @endif
                </td>
                <td class="manual-pl-unit-cell">
                  @include('hotel.erp.procurement.partials.manual-pl-unit-select', [
                    'lineIndex' => $index,
                    'itemName' => $line['item_name'] ?? '',
                    'selectedUnit' => $line['unit'] ?? '',
                    'deptCatalog' => $deptCatalog,
                    'catalogItem' => $deptCatalog->get(mb_strtolower(trim((string) ($line['item_name'] ?? '')))),
                  ])
                </td>
                <td><input class="form-control" name="lines[{{ $index }}][line_notes]" maxlength="255"
                  value="{{ $line['line_notes'] ?? '' }}"></td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line" title="Remove">&times;</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="form-group mt-4 mb-0">
        <label class="control-label">Notes for stores / procurement</label>
        <textarea class="form-control" name="notes" rows="3" maxlength="2000" placeholder="Urgent delivery, event stock-up, etc.">{{ old('notes', ($reviseList ?? null)?->notes ?? '') }}</textarea>
      </div>

      <div class="text-right mt-4">
        <a href="{{ $indexUrl }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> {{ $reviseList ? 'Send to store again' : 'Send list' }}</button>
      </div>
    </form>
  </div>
  @endif
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script type="application/json" id="manualPlItemNames">@json($itemNames ?? [])</script>
  <script type="application/json" id="manualPlItemsByDepartment">@json($itemsByDepartment ?? [])</script>
  @php
    $catalogByDepartmentJson = collect($catalogByDepartment ?? [])
      ->mapWithKeys(fn ($rows, $key) => [(string) $key => $rows])
      ->all();
  @endphp
  <script type="application/json" id="manualPlCatalogByDepartment">@json($catalogByDepartmentJson)</script>
  <script type="application/json" id="manualPlDefaultDepartmentId">@json($deptId)</script>
  <script>
    jQuery(function ($) {
      var itemNames = [];
      var itemsByDepartment = {};
      var catalogByDepartment = {};
      var catalogByName = {};
      var defaultDepartmentId = '0';
      try {
        itemNames = JSON.parse(document.getElementById('manualPlItemNames').textContent || '[]');
      } catch (e) {
        itemNames = [];
      }
      try {
        itemsByDepartment = JSON.parse(document.getElementById('manualPlItemsByDepartment').textContent || '{}');
      } catch (e) {
        itemsByDepartment = {};
      }
      try {
        catalogByDepartment = JSON.parse(document.getElementById('manualPlCatalogByDepartment').textContent || '{}');
      } catch (e) {
        catalogByDepartment = {};
      }
      try {
        defaultDepartmentId = String(JSON.parse(document.getElementById('manualPlDefaultDepartmentId').textContent || '0'));
      } catch (e) {
        defaultDepartmentId = '0';
      }
      var tbody = document.querySelector('#manualPlLines tbody');
      var lineIdx = tbody ? tbody.querySelectorAll('.pl-line').length : 1;

      function destroyItemSelect2($sel) {
        if ($sel.length && $sel.hasClass('select2-hidden-accessible')) {
          $sel.select2('destroy');
        }
      }

      function initItemSelect2($sel) {
        if (!$sel.length || !$.fn.select2) {
          return;
        }
        destroyItemSelect2($sel);
        $sel.select2({
          width: '100%',
          placeholder: $sel.data('placeholder') || 'Select or type item…',
          allowClear: true,
          tags: true,
          dropdownParent: $(document.body),
        });
      }

      function setItemNamesFromDepartment(deptId) {
        var key = String(deptId || '');
        itemNames = itemsByDepartment[key] || [];
        catalogByName = {};
        (catalogByDepartment[key] || catalogByDepartment[Number(key)] || []).forEach(function (item) {
          if (item && item.name) {
            catalogByName[String(item.name).trim().toLowerCase()] = item;
          }
        });
      }

      function catalogItemByName(name) {
        return catalogByName[String(name || '').trim().toLowerCase()] || null;
      }

      function currentDepartmentId() {
        var $dept = $('#manualPlDepartment');
        if ($dept.length) {
          return String($dept.val() || defaultDepartmentId);
        }
        return defaultDepartmentId;
      }

      function syncRowUnitHidden(row) {
        var $unit = $(row).find('.js-manual-pl-unit-select');
        var $hidden = $(row).find('.js-manual-pl-unit-value');
        if (!$hidden.length) {
          return;
        }
        if (!$unit.length || $unit.prop('disabled')) {
          return;
        }
        var $opt = $unit.find('option:selected');
        var unit = ($opt.attr('data-unit') || '').trim();
        if (!unit) {
          unit = String($opt.text() || '').trim();
        }
        $hidden.val(unit);
      }

      function defaultOptionKeyForItem(item, preferredUnit) {
        if (!item || !item.unit_options || !item.unit_options.length) {
          return '';
        }
        var unit = String(preferredUnit || '').trim();
        if (unit) {
          var byUnit = item.unit_options.find(function (opt) { return String(opt.unit) === unit; });
          if (byUnit) {
            return byUnit.value;
          }
        }
        var usage = item.unit_options.find(function (opt) { return opt.kind === 'usage'; });
        if (usage) {
          return usage.value;
        }
        return item.unit_options[0].value;
      }

      function rebuildRowUnitSelect(row, itemName, preferredUnit) {
        var $unit = $(row).find('.js-manual-pl-unit-select');
        var $hidden = $(row).find('.js-manual-pl-unit-value');
        if (!$unit.length) {
          return;
        }
        var item = catalogItemByName(itemName);
        var prevUnit = String(preferredUnit || $hidden.val() || '').trim();
        $unit.empty();
        if (!itemName) {
          $unit.prop('disabled', true);
          $unit.append($('<option value="">Select item first</option>'));
          if ($hidden.length) {
            $hidden.val('');
          }
          return;
        }
        if (!item || !item.unit_options || !item.unit_options.length) {
          $unit.prop('disabled', false);
          var custom = prevUnit || 'pcs';
          $unit.append($('<option></option>').attr('data-unit', custom).val('custom').text(custom).prop('selected', true));
          if ($hidden.length) {
            $hidden.val(custom);
          }
          return;
        }
        $unit.prop('disabled', false);
        $unit.append($('<option value="">Select unit…</option>'));
        item.unit_options.forEach(function (opt) {
          $unit.append(
            $('<option></option>')
              .val(opt.value)
              .attr('data-unit', opt.unit)
              .text(opt.label)
          );
        });
        var pickKey = defaultOptionKeyForItem(item, prevUnit);
        if (pickKey) {
          $unit.val(pickKey);
        }
        syncRowUnitHidden(row);
      }

      function rebuildAllRowUnits() {
        if (!tbody) {
          return;
        }
        tbody.querySelectorAll('.pl-line').forEach(function (row) {
          var $item = $(row).find('.js-manual-pl-item-select');
          rebuildRowUnitSelect(row, String($item.val() || '').trim(), $(row).find('.js-manual-pl-unit-value').val());
        });
      }

      function refreshAllItemSelects() {
        if (!tbody) {
          return;
        }
        $(tbody).find('.js-manual-pl-item-select').each(function () {
          var $sel = $(this);
          var selected = $sel.val() || '';
          destroyItemSelect2($sel);
          $sel.find('option').remove();
          $sel.append($('<option value=""></option>'));
          if (selected && itemNames.indexOf(selected) === -1) {
            $sel.append($('<option></option>').val(selected).text(selected).prop('selected', true));
          } else if (selected) {
            $sel.append($('<option></option>').val(selected).text(selected).prop('selected', true));
          }
          itemNames.forEach(function (name) {
            if (name !== selected) {
              $sel.append($('<option></option>').val(name).text(name));
            }
          });
          initItemSelect2($sel);
        });
        rebuildAllRowUnits();
      }

      function buildItemSelectField(index, selectedName) {
        var $wrap = $('<div class="manual-pl-item-select-wrap"></div>');
        var $sel = $('<select class="form-control js-manual-pl-item-select" required></select>');
        $sel.attr('name', 'lines[' + index + '][item_name]');
        $sel.attr('data-placeholder', 'Select or type item…');
        $sel.append($('<option value=""></option>'));
        if (selectedName) {
          $sel.append($('<option></option>').val(selectedName).text(selectedName).prop('selected', true));
        }
        itemNames.forEach(function (name) {
          if (name !== selectedName) {
            $sel.append($('<option></option>').val(name).text(name));
          }
        });
        $wrap.append($sel);
        return $wrap;
      }

      function reindexLines() {
        if (!tbody) {
          return;
        }
        $(tbody).find('.js-manual-pl-item-select').each(function () {
          destroyItemSelect2($(this));
        });
        tbody.querySelectorAll('.pl-line').forEach(function (row, index) {
          row.querySelectorAll('[name^="lines["]').forEach(function (field) {
            field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
          });
        });
        $(tbody).find('.js-manual-pl-item-select').each(function () {
          initItemSelect2($(this));
        });
      }

      function normalizeQtyText(value) {
        return String(value || '').trim().replace(/,/g, '');
      }

      function updateManualQtyChangeHint(input) {
        var prev = input.getAttribute('data-previous-qty');
        if (!prev) {
          return;
        }
        var hint = input.parentElement.querySelector('.manual-pl-qty-change');
        if (!hint) {
          return;
        }
        var cur = normalizeQtyText(input.value);
        if (cur === '' || cur === normalizeQtyText(prev)) {
          hint.classList.add('d-none');
          return;
        }
        hint.textContent = prev + '-' + cur;
        hint.classList.remove('d-none');
      }

      if (tbody) {
        $(tbody).find('.js-manual-pl-item-select').each(function () {
          initItemSelect2($(this));
        });
        setItemNamesFromDepartment(currentDepartmentId());
        rebuildAllRowUnits();
        tbody.addEventListener('input', function (e) {
          if (e.target.classList.contains('manual-pl-qty-input')) {
            updateManualQtyChangeHint(e.target);
          }
        });
        tbody.querySelectorAll('.manual-pl-qty-input').forEach(updateManualQtyChangeHint);
      }

      $('#addPlLine').on('click', function () {
        if (!tbody) {
          return;
        }
        var tr = document.createElement('tr');
        tr.className = 'pl-line';
        var tdItem = document.createElement('td');
        tdItem.appendChild(buildItemSelectField(lineIdx, '')[0]);
        tr.appendChild(tdItem);
        var tdQty = document.createElement('td');
        tdQty.innerHTML = '<input class="form-control manual-pl-qty-input" type="number" step="any" min="0.01" name="lines[' + lineIdx + '][quantity]" value="1" required>';
        tr.appendChild(tdQty);
        var tdUnit = document.createElement('td');
        tdUnit.className = 'manual-pl-unit-cell';
        tdUnit.innerHTML =
          '<input type="hidden" class="js-manual-pl-unit-value" name="lines[' + lineIdx + '][unit]" value="">' +
          '<select class="form-control js-manual-pl-unit-select" disabled aria-label="Unit">' +
          '<option value="">Select item first</option></select>';
        tr.appendChild(tdUnit);
        var tdNotes = document.createElement('td');
        tdNotes.innerHTML = '<input class="form-control" name="lines[' + lineIdx + '][line_notes]" maxlength="255">';
        tr.appendChild(tdNotes);
        var tdRm = document.createElement('td');
        tdRm.className = 'text-center align-middle';
        tdRm.innerHTML = '<button type="button" class="btn btn-link text-danger p-0 remove-line" title="Remove">&times;</button>';
        tr.appendChild(tdRm);
        tbody.appendChild(tr);
        initItemSelect2($(tr).find('.js-manual-pl-item-select'));
        rebuildRowUnitSelect(tr, '', '');
        lineIdx++;
      });

      if (tbody) {
        $(tbody).on('change select2:select select2:clear', '.js-manual-pl-item-select', function () {
          var row = this.closest('.pl-line');
          if (!row) {
            return;
          }
          rebuildRowUnitSelect(row, String($(this).val() || '').trim(), '');
        });
        $(tbody).on('change', '.js-manual-pl-unit-select', function () {
          var row = this.closest('.pl-line');
          if (row) {
            syncRowUnitHidden(row);
          }
        });
      }

      var $deptSelect = $('#manualPlDepartment');
      if ($deptSelect.length) {
        $deptSelect.on('change', function () {
          setItemNamesFromDepartment($(this).val());
          refreshAllItemSelects();
        });
      }

      $('#manualPlForm').on('submit', function () {
        if (tbody) {
          tbody.querySelectorAll('.pl-line').forEach(function (row) {
            syncRowUnitHidden(row);
          });
        }
        $(this).find('select:disabled').prop('disabled', false);
      });

      if (tbody) {
        tbody.addEventListener('click', function (e) {
          if (!e.target.classList.contains('remove-line')) {
            return;
          }
          var rows = tbody.querySelectorAll('.pl-line');
          if (rows.length <= 1) {
            return;
          }
          var row = e.target.closest('tr');
          destroyItemSelect2($(row).find('.js-manual-pl-item-select'));
          row.remove();
          reindexLines();
        });
      }
    });
  </script>
@endpush
