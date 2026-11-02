@php
  $activeItems = $items->where('is_active', true);
  $reviseList = $reviseList ?? null;
  $defaultLineRow = [['item_id' => '', 'package_id' => '', 'quantity' => '1', 'line_notes' => '']];
  // Prefer prefilled revise lines on GET; use old() only after validation errors (avoids stale session qty).
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
      if (! is_array($mergeLine)) {
        continue;
      }
      if (! isset($mergeLine['previous_quantity']) && isset($initialFormLines[$idx]['previous_quantity'])) {
        $mergeLine['previous_quantity'] = $initialFormLines[$idx]['previous_quantity'];
      }
    }
    unset($mergeLine);
  }
  $formatPlQty = static function ($qty): string {
    if ($qty === null || $qty === '') {
      return '1';
    }
    if (! is_numeric($qty)) {
      return '1';
    }
    $formatted = rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');

    return $formatted !== '' ? $formatted : '1';
  };
  $selectedIds = collect($oldLines)->pluck('item_id')->filter()->map(fn ($id) => (int) $id);
  $pickableItems = $activeItems
    ->merge($items->whereIn('id', $selectedIds))
    ->unique('id')
    ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE)
    ->values();
  $procW = $procWorkflow ?? 'bar';
  $requestingDeptId = (int) old('hotel_department_id', $defaultDepartmentId ?? 0);
  $requestingDept = ($departments ?? collect())->firstWhere('id', $requestingDeptId);
  $requestingDeptName = $requestingDept?->name ?? 'Bar';
@endphp

@if($pickableItems->isEmpty())
  <div class="alert alert-warning mb-0">
    No registered bar items yet.
    <a href="{{ route('hotel.services.show', $service) }}">Set up the bar menu</a>, then return here.
  </div>
@elseif($packages->isEmpty())
  <div class="alert alert-warning mb-0">
    No units configured.
    <a href="{{ route('hotel.services.show', $service) }}">Set up menu units</a> first.
  </div>
@elseif($requestingDeptId <= 0)
  <div class="alert alert-warning mb-0">
    Bar department is not set up yet. Open <a href="{{ route('hotel.bar.index', ['tab' => 'stock']) }}">Bar</a> once or check HR departments.
  </div>
@else
  @php
    $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
    $procIndexUrl = \App\Support\DepartmentProcurement::indexUrl($procW, 'purchase-lists');
  @endphp
  <form method="POST" action="{{ $reviseList ? $procRoute('purchase-lists.resubmit', $reviseList) : $procRoute('purchase-lists.store') }}" class="js-swal-confirm" id="barPurchaseListForm"
    data-title="{{ $reviseList ? 'Send to store again?' : 'Send purchase list?' }}"
    data-text="{{ $reviseList ? 'Update this list and send it back to the store team.' : 'Email this list to the configured recipients?' }}"
    data-confirm="{{ $reviseList ? 'Send to store' : 'Yes, send' }}">
    @csrf
    <input type="hidden" name="hotel_department_id" value="{{ $requestingDeptId }}">

    @if($reviseList)
      @if($returnMessage = $reviseList->storeReturnNotes())
        <div class="alert alert-warning py-2">
          <strong>Store message:</strong> {{ $returnMessage }}
        </div>
      @endif
      <p class="text-muted small mb-3">Reference <strong>{{ $reviseList->reference_no }}</strong> — adjust items, then send to the store again.</p>
    @endif

    <div class="row">
      <div class="col-md-4">
        <div class="form-group">
          <label class="control-label">Requesting department</label>
          <p class="form-control-plaintext font-weight-bold mb-0">{{ $requestingDeptName }}</p>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
      <label class="control-label mb-0">Items to purchase</label>
      <button type="button" class="btn btn-sm btn-light border js-bar-pl-add-line">
        <i class="fa fa-plus"></i> Add line
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered mb-0">
        <thead>
          <tr>
            <th style="min-width:240px;">Item</th>
            <th style="width:110px;">Qty</th>
            <th style="width:160px;">Unit</th>
            <th>Notes</th>
            <th style="width:44px;"></th>
          </tr>
        </thead>
        <tbody class="js-bar-pl-lines">
          @foreach($oldLines as $index => $line)
            @php
              $selectedId = (int) ($line['item_id'] ?? 0);
              $packageId = (int) ($line['package_id'] ?? 0);
              $prevQtyRaw = $line['previous_quantity'] ?? null;
              $prevQtyLabel = $prevQtyRaw !== null && $prevQtyRaw !== '' ? $formatPlQty($prevQtyRaw) : null;
              $currQtyLabel = $errors->isNotEmpty()
                ? $formatPlQty(old('lines.'.$index.'.quantity', $line['quantity'] ?? '1'))
                : $formatPlQty($line['quantity'] ?? '1');
            @endphp
            <tr class="js-bar-pl-line">
              <td>
                @include('hotel.erp.bar.partials._purchase-list-item-picker', [
                  'lineIndex' => $index,
                  'selectedItemId' => $selectedId,
                ])
              </td>
              <td class="bar-pl-qty-cell">
                <input type="text" class="form-control form-control-sm bar-pl-qty-input" name="lines[{{ $index }}][quantity]"
                  inputmode="decimal" autocomplete="off" required
                  @if($reviseList && $prevQtyLabel !== null) data-previous-qty="{{ $prevQtyLabel }}" @endif
                  value="{{ $currQtyLabel }}">
                @if($reviseList && $prevQtyLabel !== null)
                  <small class="bar-pl-qty-change {{ $prevQtyLabel !== $currQtyLabel ? '' : 'd-none' }}">{{ $prevQtyLabel }}-{{ $currQtyLabel }}</small>
                @endif
              </td>
              <td>
                @include('hotel.erp.bar.partials._purchase-list-package-select', [
                  'lineIndex' => $index,
                  'selectedPackageId' => $packageId,
                ])
              </td>
              <td>
                <input type="text" class="form-control form-control-sm" name="lines[{{ $index }}][line_notes]" maxlength="255"
                  value="{{ $line['line_notes'] ?? '' }}">
              </td>
              <td class="text-center align-middle">
                <button type="button" class="btn btn-sm btn-link text-danger js-bar-pl-remove-line" title="Remove">&times;</button>
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

    <div class="tile-footer px-0 pb-0 mt-4">
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-paper-plane"></i> {{ $reviseList ? 'Send to store again' : 'Send purchase list' }}
      </button>
      <a class="btn btn-secondary" href="{{ $procIndexUrl }}">Cancel</a>
    </div>
  </form>

  <template id="barPurchaseListLineTemplate">
    <tr class="js-bar-pl-line">
      <td>
        @include('hotel.erp.bar.partials._purchase-list-item-picker', [
          'lineIndex' => '__INDEX__',
          'selectedItemId' => 0,
        ])
      </td>
      <td>
        <input type="text" class="form-control form-control-sm bar-pl-qty-input" name="lines[__INDEX__][quantity]" inputmode="decimal" autocomplete="off" value="1" required>
      </td>
      <td>
        @include('hotel.erp.bar.partials._purchase-list-package-select', [
          'lineIndex' => '__INDEX__',
          'selectedPackageId' => 0,
        ])
      </td>
      <td>
        <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][line_notes]" maxlength="255">
      </td>
      <td class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-link text-danger js-bar-pl-remove-line" title="Remove">&times;</button>
      </td>
    </tr>
  </template>
@endif
