@php
  $storesTemplate = $storesTemplate ?? false;
  $inputClass = $storesTemplate ? 'form-control' : 'form-control form-control-sm';
  $currency = strtoupper(trim((string) ($budgetCurrency ?? '')));
  if ($currency === '') {
    $currency = $purchaseList->resolvedBudgetCurrency();
  }
  $expectedPrices = $expectedPrices ?? [];
  $oldLines = old('lines');
  if (! is_array($oldLines)) {
    $oldLines = $purchaseList->lines->map(function ($line) use ($expectedPrices) {
      $key = strtolower(trim($line->item_name));
      $defaultCost = $expectedPrices[$key] ?? null;

      return [
        'id' => $line->id,
        'item_name' => $line->item_name,
        'quantity' => (int) max(1, round((float) $line->quantity)),
        'unit' => $line->unit,
        'line_notes' => $line->line_notes,
        'unit_cost' => $line->unit_cost ?? $defaultCost,
      ];
    })->all();
  }
  if ($oldLines === []) {
    $oldLines = [['id' => '', 'item_name' => '', 'quantity' => '1', 'unit' => '', 'line_notes' => '', 'unit_cost' => '']];
  }
  $pickableItems = ($catalogItems ?? collect())->values();
  $respondCatalogForJs = $pickableItems->map(function ($barItem) {
    $category = $barItem->category?->name;
    $unit = trim((string) ($barItem->package?->abbreviation ?: $barItem->package?->name ?: ''));

    return [
      'name' => $barItem->item_name,
      'label' => $category ? $barItem->item_name.' — '.$category : $barItem->item_name,
      'category' => $category,
      'unit' => $unit,
      'expectedPrice' => $barItem->expected_unit_price !== null ? (float) $barItem->expected_unit_price : null,
    ];
  })->values();
@endphp

@if($purchaseList->isAccountingRejected() && ! $locked)
  <div class="alert alert-danger mb-3">
    <strong>Accountant rejected this budget.</strong>
    @if($purchaseList->accounting_notes)
      {{ $purchaseList->accounting_notes }}
    @endif
  </div>
@endif
@if($submitted)
  <div class="alert alert-success">Budget submitted to <strong>Accounts</strong>. Finalize under ERP → Accounts → Purchase budgets (approve or reject).</div>
@elseif($saved)
  <div class="alert alert-info">Draft saved. You can continue editing and submit the budget when ready.</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@php
  $purchaseListVendors = $purchaseListVendors ?? collect();
  $selectedVendorId = (int) old('hotel_vendor_id', $purchaseList->hotel_vendor_id ?? 0);
@endphp

@if($storesTemplate)
  <div class="stores-respond-form-fields mb-3">
  <div class="stores-filter-fields mb-0">
    <div class="stores-filter-field">
      <span class="control-label d-block text-muted small">Department</span>
      <strong>{{ $purchaseList->department?->name ?? '—' }}</strong>
    </div>
    <div class="stores-filter-field">
      <span class="control-label d-block text-muted small">Requested by</span>
      <strong>{{ $purchaseList->creator?->name ?? '—' }}</strong>
    </div>
    <div class="stores-filter-field">
      <span class="control-label d-block text-muted small">Sent</span>
      <strong>{{ $purchaseList->sent_at?->format('d M Y H:i') ?? '—' }}</strong>
    </div>
    <div class="stores-filter-field">
      <span class="control-label d-block text-muted small">Status</span>
      <strong>{{ $purchaseList->recipientStatusLabel() }}</strong>
    </div>
    @if($locked && $purchaseList->vendor)
      <div class="stores-filter-field">
        <span class="control-label d-block text-muted small">Supplier</span>
        <strong>{{ $purchaseList->vendor->name }}</strong>
      </div>
    @endif
  </div>
  </div>
@else
  <div class="row mb-4">
    <div class="col-md-3"><strong>Department</strong><br>{{ $purchaseList->department?->name ?? '—' }}</div>
    <div class="col-md-3"><strong>Requested by</strong><br>{{ $purchaseList->creator?->name ?? '—' }}</div>
    <div class="col-md-3"><strong>Sent</strong><br>{{ $purchaseList->sent_at?->format('d M Y H:i') ?? '—' }}</div>
    <div class="col-md-3"><strong>Status</strong><br>{{ $purchaseList->recipientStatusLabel() }}</div>
  </div>
  @if($locked && $purchaseList->vendor)
    <p class="mb-3"><strong>Supplier:</strong> {{ $purchaseList->vendor->name }}</p>
  @endif
@endif

@if($purchaseList->notes)
  <p class="mb-3"><strong>Request notes:</strong> {{ $purchaseList->notes }}</p>
@endif

@if($locked)
  @if($purchaseList->recipient_notes)
    <p class="mb-3"><strong>Recipient notes:</strong> {{ $purchaseList->recipient_notes }}</p>
  @endif
  @include('hotel.erp.bar.partials._purchase-list-budget-table', [
    'purchaseList' => $purchaseList,
    'currency' => $currency,
    'storesTemplate' => $storesTemplate,
  ])
  <p class="text-muted mt-3 mb-0" style="font-size:12px;">
    Prepared by {{ $purchaseList->budget_prepared_by_name ?? '—' }}
    @if($purchaseList->budget_prepared_by_email) ({{ $purchaseList->budget_prepared_by_email }}) @endif
    · {{ $purchaseList->budget_prepared_at?->format('d/m/Y H:i') }}
  </p>
@else
  <form method="POST" action="{{ $submitUrl }}" id="barRespondForm">
    @unless($sharedView)
      @csrf
    @endunless

    @if($storesTemplate)
      <div class="stores-respond-form-fields">
        <div class="form-group">
          <label class="control-label">Supplier <span class="text-danger">*</span></label>
          <select name="hotel_vendor_id" class="{{ $inputClass }}" required
            @if($purchaseListVendors->isEmpty()) disabled @endif>
            <option value="">Select supplier</option>
            @foreach($purchaseListVendors as $vendor)
              <option value="{{ $vendor->id }}" @selected($selectedVendorId === (int) $vendor->id)>{{ $vendor->name }}</option>
            @endforeach
          </select>
          @if($purchaseListVendors->isEmpty())
            <p class="text-muted small mb-0 mt-1">Add vendors under <a href="{{ route('hotel.stores.index', ['tab' => 'settings', 'settings' => 'vendor']) }}">Stores → Settings → Vendor</a> first.</p>
          @endif
          @error('hotel_vendor_id')
            <span class="text-danger small">{{ $message }}</span>
          @enderror
        </div>
        @if($sharedView ?? false)
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-md-0">
                <label class="control-label">Your name</label>
                <input type="text" class="{{ $inputClass }}" name="responder_name" maxlength="120" required
                  value="{{ old('responder_name') }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group mb-md-0">
                <label class="control-label">Your email</label>
                <input type="email" class="{{ $inputClass }}" name="responder_email" maxlength="160" required
                  value="{{ old('responder_email') }}">
              </div>
            </div>
          </div>
        @endif
        <div class="form-group mb-0">
          <label class="control-label">Notes for requesting department (optional)</label>
          <textarea class="{{ $inputClass }}" name="recipient_notes" rows="2" maxlength="2000">{{ old('recipient_notes', $purchaseList->recipient_notes) }}</textarea>
        </div>
      </div>
      <div class="stores-respond-line-toolbar">
        <label class="control-label mb-0">Line items</label>
        <button type="button" class="btn btn-stores-outline js-bar-respond-add-line"><i class="fa fa-plus"></i> Add line</button>
      </div>
      @if($pickableItems->isEmpty())
        <p class="text-muted small stores-respond-hint">Item search: register products under <a href="{{ route('hotel.bar.index', ['tab' => 'items']) }}">Bar → Items</a> first.</p>
      @else
        <p class="text-muted small stores-respond-hint">Type in <strong>Item</strong> to search registered bar products (or add a custom name).</p>
      @endif
    @else
      <div class="row mb-3">
        <div class="col-md-12">
          <div class="form-group mb-md-0">
            <label class="control-label">Supplier <span class="text-danger">*</span></label>
            <select name="hotel_vendor_id" class="{{ $inputClass }}" required
              @if($purchaseListVendors->isEmpty()) disabled @endif>
              <option value="">Select supplier</option>
              @foreach($purchaseListVendors as $vendor)
                <option value="{{ $vendor->id }}" @selected($selectedVendorId === (int) $vendor->id)>{{ $vendor->name }}</option>
              @endforeach
            </select>
            @if($purchaseListVendors->isEmpty())
              <p class="text-muted small mb-0 mt-1">Add vendors under <a href="{{ route('hotel.stores.index', ['tab' => 'settings', 'settings' => 'vendor']) }}">Stores → Settings → Vendor</a> first.</p>
            @endif
            @error('hotel_vendor_id')
              <span class="text-danger small">{{ $message }}</span>
            @enderror
          </div>
        </div>
      </div>

      @if($sharedView ?? false)
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="form-group mb-md-0">
              <label class="control-label">Your name</label>
              <input type="text" class="{{ $inputClass }}" name="responder_name" maxlength="120" required
                value="{{ old('responder_name') }}">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group mb-md-0">
              <label class="control-label">Your email</label>
              <input type="email" class="{{ $inputClass }}" name="responder_email" maxlength="160" required
                value="{{ old('responder_email') }}">
            </div>
          </div>
        </div>
      @endif

      <div class="form-group">
        <label class="control-label">Notes for requesting department (optional)</label>
        <textarea class="{{ $inputClass }}" name="recipient_notes" rows="2" maxlength="2000">{{ old('recipient_notes', $purchaseList->recipient_notes) }}</textarea>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="control-label mb-0">Line items</label>
        <button type="button" class="btn btn-sm btn-light border js-bar-respond-add-line"><i class="fa fa-plus"></i> Add line</button>
      </div>
      @if($pickableItems->isEmpty())
        <p class="text-muted small mb-2">Item search: register products under <a href="{{ route('hotel.bar.index', ['tab' => 'items']) }}">Bar → Items</a> first.</p>
      @else
        <p class="text-muted small mb-2">Type in <strong>Item</strong> to search registered bar products (or add a custom name).</p>
      @endif
    @endif

    <div class="{{ $storesTemplate ? 'stores-table-wrap table-responsive' : 'table-responsive' }}">
      <table class="table table-bordered table-hover mb-0 {{ $storesTemplate ? 'stores-po-list-table' : '' }}" @unless($storesTemplate) style="table-layout: fixed; width: 100%;" @endunless>
        <colgroup>
          <col style="width: 200px;">
          <col style="width: 120px;">
          <col style="width: 150px;">
          <col style="width: 160px;">
          <col style="width: 110px;">
          <col style="width: 150px;">
          <col style="width: 36px;">
        </colgroup>
        <thead>
          <tr>
            <th style="width: 200px; max-width: 200px;">Item</th>
            <th style="width:120px;min-width:120px;">Qty</th>
            <th style="width:150px;min-width:150px;">Unit</th>
            <th style="width:160px;min-width:160px;">Expected price</th>
            <th style="width:110px;min-width:110px;" class="text-right">Line total</th>
            <th style="width:150px;">Notes</th>
            <th style="width:36px;"></th>
          </tr>
        </thead>
        <tbody class="js-bar-respond-lines">
          @foreach($oldLines as $index => $line)
            <tr class="js-bar-respond-line">
              <td style="max-width: 200px;">
                <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line['id'] ?? '' }}">
                @include('hotel.erp.bar.partials._purchase-list-respond-item-picker', [
                  'lineIndex' => $index,
                  'itemName' => $line['item_name'] ?? '',
                  'pickableItems' => $pickableItems,
                  'inputClass' => $inputClass,
                ])
              </td>
              <td style="width:120px;min-width:120px;">
                <input type="number" class="{{ $inputClass }} js-bar-respond-qty" name="lines[{{ $index }}][quantity]"
                  step="1" min="1" inputmode="numeric" value="{{ $line['quantity'] ?? '1' }}" required>
              </td>
              <td style="width:150px;min-width:150px;">
                @include('hotel.erp.bar.partials._purchase-list-respond-unit-select', [
                  'lineIndex' => $index,
                  'selectedUnit' => $line['unit'] ?? '',
                  'packages' => $packages ?? collect(),
                  'inputClass' => $inputClass,
                ])
              </td>
              <td style="width:160px;min-width:160px;">
                <input type="number" class="{{ $inputClass }} js-bar-respond-cost" name="lines[{{ $index }}][unit_cost]"
                  step="0.01" min="0" value="{{ $line['unit_cost'] ?? '' }}" placeholder="0">
              </td>
              <td class="text-right align-middle" style="width:110px;min-width:110px;">
                <span class="js-bar-respond-line-total">—</span>
              </td>
              <td style="width:150px;">
                <input type="text" class="{{ $inputClass }}" name="lines[{{ $index }}][line_notes]" maxlength="255"
                  value="{{ $line['line_notes'] ?? '' }}">
              </td>
              <td class="text-center align-middle">
                <button type="button" class="btn btn-sm btn-link text-danger js-bar-respond-remove">&times;</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if($storesTemplate)
      <div class="stores-total-value text-right mb-0">
        <strong>Budget total:</strong>
        <span class="js-bar-respond-grand-total">0.00</span> {{ $currency }}
      </div>
      <div class="stores-respond-footer">
        <button type="submit" name="action" value="draft" class="btn btn-stores-outline">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-stores-outline">
          <i class="fa fa-check"></i> Submit to Accounts
        </button>
      </div>
    @else
      <p class="text-right mt-3 mb-0" style="font-size:16px;font-weight:700;color:#940000;">
        Budget total: <span class="js-bar-respond-grand-total">0.00</span> {{ $currency }}
      </p>
      <div class="tile-footer px-0 pb-0 mt-4">
        <button type="submit" name="action" value="draft" class="btn btn-light border mr-2">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary">
          <i class="fa fa-check"></i> Submit to Accounts
        </button>
      </div>
    @endif
  </form>

  <ul id="barRespondItemSuggestions" class="bar-pl-item-suggestions d-none" role="listbox"></ul>
  <script type="application/json" id="barRespondCatalogData">@json($respondCatalogForJs)</script>

  <template id="barRespondLineTemplate">
    <tr class="js-bar-respond-line">
      <td style="max-width: 200px;">
        <input type="hidden" name="lines[__INDEX__][id]" value="">
        @include('hotel.erp.bar.partials._purchase-list-respond-item-picker', [
          'lineIndex' => '__INDEX__',
          'itemName' => '',
          'pickableItems' => $pickableItems,
          'inputClass' => $inputClass,
        ])
      </td>
      <td style="width:120px;min-width:120px;">
        <input type="number" class="{{ $inputClass }} js-bar-respond-qty" name="lines[__INDEX__][quantity]" step="1" min="1" inputmode="numeric" value="1" required>
      </td>
      <td style="width:150px;min-width:150px;">
        @include('hotel.erp.bar.partials._purchase-list-respond-unit-select', [
          'lineIndex' => '__INDEX__',
          'selectedUnit' => '',
          'packages' => $packages ?? collect(),
          'inputClass' => $inputClass,
        ])
      </td>
      <td style="width:160px;min-width:160px;">
        <input type="number" class="{{ $inputClass }} js-bar-respond-cost" name="lines[__INDEX__][unit_cost]" step="0.01" min="0" placeholder="0">
      </td>
      <td class="text-right align-middle" style="width:110px;min-width:110px;"><span class="js-bar-respond-line-total">—</span></td>
      <td style="width:150px;">
        <input type="text" class="{{ $inputClass }}" name="lines[__INDEX__][line_notes]" maxlength="255">
      </td>
      <td class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-link text-danger js-bar-respond-remove">&times;</button>
      </td>
    </tr>
  </template>
@endif
