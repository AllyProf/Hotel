@php
  $barPurchaseLists = $barPurchaseLists ?? app(\App\Services\Bar\BarPurchaseListService::class);
  $masterByName = $masterByName ?? [];
  $plannedTotal = $purchaseList->marketPlannedBudgetTotal();
  $defaultBudget = old('market_budget_amount', $purchaseList->market_budget_amount ?? $plannedTotal);
  $defaultBudget = $defaultBudget !== null && $defaultBudget !== '' ? (float) $defaultBudget : $plannedTotal;
  $packages = $packages ?? collect();
  $resolvePackageIdFromUnit = static function (string $unitLabel) use ($packages): ?int {
    $needle = strtoupper(trim($unitLabel));
    if ($needle === '') {
      return null;
    }
    foreach ($packages as $pkg) {
      $abbr = strtoupper(trim((string) ($pkg->abbreviation ?: '')));
      $name = strtoupper(trim((string) $pkg->name));
      if ($needle === $abbr || ($abbr !== '' && $needle === $abbr) || $needle === $name) {
        return (int) $pkg->id;
      }
    }

    return null;
  };
@endphp

@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@php
  $poPaidFromAccounts = $purchaseList->purchaseOrder?->isPaid() ?? false;
@endphp
@if($poPaidFromAccounts)
  <div class="alert alert-info py-2">
    Accounts already paid the approved budget for {{ $purchaseList->purchaseOrder->po_number }}.
    If market spend is lower, the difference is posted as a <strong>return (paid in)</strong> when you complete this purchase.
  </div>
@endif

<form method="POST" action="{{ route('hotel.bar.purchase-lists.market-complete', $purchaseList) }}" id="barMarketItemsForm" data-loader-text="Saving...">
  @csrf

  <input type="hidden" class="js-bar-market-budget" name="market_budget_amount"
    value="{{ number_format($defaultBudget, 2, '.', '') }}">
  <div class="row bar-market-summary-row mb-3">
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--budget">
        <span class="bar-market-stat-card__label">Budget ({{ $currency }})</span>
        <span class="bar-market-stat-card__value">{{ number_format($defaultBudget, 2, '.', ',') }}</span>
        <span class="bar-market-stat-card__hint">Approved total for this list</span>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--used">
        <span class="bar-market-stat-card__label">Amount used</span>
        <span class="bar-market-stat-card__value"><span class="js-bar-market-used">0</span> <small>{{ $currency }}</small></span>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--remaining js-bar-market-remaining-card">
        <span class="bar-market-stat-card__label">Remaining</span>
        <span class="bar-market-stat-card__value"><span class="js-bar-market-remaining">0</span> <small>{{ $currency }}</small></span>
        <span class="bar-market-stat-card__hint js-bar-market-remaining-hint"></span>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--missing">
        <span class="bar-market-stat-card__label">Missing items</span>
        <span class="bar-market-stat-card__value js-bar-market-missing">0</span>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered mb-0 bar-market-items-table">
      <thead>
        <tr>
          <th class="text-center bar-market-col-found">Found</th>
          <th class="bar-market-col-name">Item Name</th>
          <th class="text-center bar-market-col-plan">Plan</th>
          <th class="text-center bar-market-col-bought">Bought</th>
          <th class="bar-market-col-recv-pkg">Recv. pkg</th>
          <th class="bar-market-col-sell-pkg">Sell. pkg</th>
          <th class="text-center bar-market-col-cost">Total Cost</th>
          <th class="text-center bar-market-col-sell-price">Sell price</th>
          <th class="text-center bar-market-col-expiry">Expiry</th>
        </tr>
      </thead>
      <tbody>
        @foreach($purchaseList->lines as $index => $line)
          @php
            $catalogKey = strtolower(trim($line->item_name));
            $catalog = $catalogByName[$catalogKey] ?? null;
            $masterItem = $masterByName[$catalogKey] ?? null;
            $planQty = (float) $line->quantity;
            $oldRow = old('lines.'.$index, []);
            $foundDefault = array_key_exists('market_item_found', $oldRow)
              ? filter_var($oldRow['market_item_found'], FILTER_VALIDATE_BOOLEAN)
              : ($line->market_item_found ?? true);
            $boughtDefault = $oldRow['quantity_purchased'] ?? ($line->quantity_purchased ?? $planQty);
            $lineTotalDefault = $oldRow['purchase_line_total'] ?? ($line->purchase_line_total ?? $line->line_budget);
            if ($lineTotalDefault === null && $line->unit_cost !== null) {
              $lineTotalDefault = round($planQty * (float) $line->unit_cost, 2);
            }
            $expiryDefault = $oldRow['expiry_date'] ?? ($line->expiry_date?->format('Y-m-d') ?? '');
            $categoryName = $catalog?->category?->name;
            $planDisplay = rtrim(rtrim(number_format($planQty, 2, '.', ''), '0'), '.');
            $recvPkgFromMaster = $barPurchaseLists->marketReceivingPackageIdForLine(
              $catalog,
              $masterItem,
              (string) $line->unit,
              $packages,
            );
            $recvPkgDefault = (int) ($oldRow['receiving_package_id']
              ?? $line->receiving_package_id
              ?? ($recvPkgFromMaster > 0 ? $recvPkgFromMaster : null)
              ?? $catalog?->hotel_pms_service_package_id
              ?? $resolvePackageIdFromUnit((string) $line->unit)
              ?? 0);
            $sellMethodsArray = $barPurchaseLists->marketSellMethodsForLine(
              $catalog,
              $masterItem,
              (string) $line->unit,
              $packages,
            );
            $sellPkgDefault = (int) ($oldRow['selling_package_id']
              ?? $line->selling_package_id
              ?? $barPurchaseLists->marketPrimarySellingPackageId($catalog, $masterItem, $packages, $sellMethodsArray)
              ?? $catalog?->selling_hotel_pms_service_package_id
              ?? $catalog?->hotel_pms_service_package_id
              ?? $recvPkgDefault
              ?? 0);
            $sellingPriceDefault = $oldRow['selling_unit_price'] ?? $line->selling_unit_price;
            if ($sellingPriceDefault !== null && $sellingPriceDefault !== '' && (float) $sellingPriceDefault <= 0) {
              $sellingPriceDefault = null;
            }
            $catalogBuyRef = (float) ($catalog?->expected_unit_price ?? $line->unit_cost ?? 0);
            $catalogSellRef = (float) ($catalog?->unit_price ?? 0);
            $sellMultiplier = 1.35;
            if ($catalogBuyRef > 0 && $catalogSellRef > 0) {
              $sellMultiplier = max(1.0, round($catalogSellRef / $catalogBuyRef, 4));
            }
            $sellMethods = collect($sellMethodsArray);
            if ($sellMethods->isEmpty()) {
              $fallbackPkgId = $sellPkgDefault > 0 ? $sellPkgDefault : ($recvPkgDefault > 0 ? $recvPkgDefault : $resolvePackageIdFromUnit((string) $line->unit));
              $fallbackPkg = $fallbackPkgId ? $packages->firstWhere('id', $fallbackPkgId) : null;
              $fbName = trim((string) ($fallbackPkg?->name ?? ''));
              $fbAbbr = trim((string) ($fallbackPkg?->abbreviation ?? ''));
              $fbLabel = $fbName !== '' ? $fbName : ($fbAbbr !== '' ? $fbAbbr : ((string) $line->unit ?: 'Sale unit'));
              if ($fbName !== '' && $fbAbbr !== '' && strcasecmp($fbName, $fbAbbr) !== 0) {
                $fbLabel = $fbName.' ('.$fbAbbr.')';
              }
              $sellMethods = collect([[
                'package_id' => (int) ($fallbackPkgId ?: 0),
                'unit_label' => $fbLabel,
                'pieces_per_sell_unit' => 1.0,
                'unit_price' => $catalogSellRef > 0 ? $catalogSellRef : ($sellingPriceDefault !== null && $sellingPriceDefault !== '' ? (float) $sellingPriceDefault : null),
              ]]);
            }
            $hasMultipleSellMethods = $sellMethods->count() > 1;
            $oldSellPrices = is_array($oldRow['sell_option_prices'] ?? null) ? $oldRow['sell_option_prices'] : [];
            $storedSellPrices = is_array($line->market_sell_option_prices) ? $line->market_sell_option_prices : [];
            $sellMethodsWithPrices = $sellMethods->map(function (array $method) use ($oldSellPrices, $storedSellPrices) {
              $pid = $method['package_id'];
              $saved = null;
              if (array_key_exists($pid, $oldSellPrices) && $oldSellPrices[$pid] !== '' && $oldSellPrices[$pid] !== null) {
                $saved = max(0, (float) $oldSellPrices[$pid]);
              } elseif (array_key_exists($pid, $storedSellPrices)) {
                $saved = max(0, (float) $storedSellPrices[$pid]);
              } elseif ($method['unit_price'] !== null) {
                $saved = max(0, (float) $method['unit_price']);
              }
              $method['saved_price'] = ($saved !== null && $saved > 0) ? $saved : null;

              return $method;
            });
            $sellUnitsTotal = $sellMethodsWithPrices->count();
            $sellUnitsSet = $sellMethodsWithPrices->filter(fn (array $m) => $m['saved_price'] !== null)->count();
            $primarySellMethod = $sellMethodsWithPrices->sortBy('pieces_per_sell_unit')->first();
            $activeSellMethod = $sellMethodsWithPrices->firstWhere('package_id', $sellPkgDefault) ?? $primarySellMethod;
            $effectiveSellPkgId = ($sellPkgDefault > 0 && $sellMethodsWithPrices->contains('package_id', $sellPkgDefault))
              ? $sellPkgDefault
              : (int) ($primarySellMethod['package_id'] ?? 0);
            if ($primarySellMethod && ($sellingPriceDefault === null || $sellingPriceDefault === '')) {
              $sellingPriceDefault = $primarySellMethod['saved_price'] ?? $primarySellMethod['unit_price'];
            }
            $unitsBtnState = $sellUnitsSet >= $sellUnitsTotal && $sellUnitsTotal > 0 ? 'is-complete' : ($sellUnitsSet > 0 ? 'is-partial' : 'is-empty');
            $primaryUnitLabel = ($sellMethodsWithPrices->firstWhere('package_id', $effectiveSellPkgId) ?? $primarySellMethod)['unit_label'] ?? '—';
            $lineStockDest = $oldRow['market_stock_destination']
              ?? $line->market_stock_destination
              ?? \App\Models\HotelBarPurchaseList::STOCK_DEST_BAR;
            $plannedUnitCost = (float) ($line->unit_cost ?? 0);
            if ($plannedUnitCost <= 0 && $line->line_budget !== null && $planQty > 0) {
              $plannedUnitCost = round((float) $line->line_budget / $planQty, 4);
            }
            if ($plannedUnitCost <= 0 && $lineTotalDefault !== null && (float) $boughtDefault > 0) {
              $plannedUnitCost = round((float) $lineTotalDefault / (float) $boughtDefault, 4);
            }
            if ($plannedUnitCost <= 0 && $catalogBuyRef > 0) {
              $plannedUnitCost = $catalogBuyRef;
            }
          @endphp
          <tr class="js-bar-market-line js-bar-market-line-sell-modal"
            data-sell-multiplier="{{ $sellMultiplier }}"
            data-line-index="{{ $index }}"
            data-item-name="{{ e($line->item_name) }}"
            @if($plannedUnitCost > 0) data-planned-unit-cost="{{ number_format($plannedUnitCost, 4, '.', '') }}" @endif
            data-sell-methods='@json($sellMethodsWithPrices->values())'
            data-sell-units-total="{{ max(1, $sellUnitsTotal) }}"
            @if($sellingPriceDefault !== null && $sellingPriceDefault !== '') data-sell-touched="1" @endif>
            <td class="align-middle text-center">
              <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
              <input type="hidden" name="lines[{{ $index }}][market_stock_destination]" value="{{ $lineStockDest }}">
              <div class="animated-checkbox">
                <label class="mb-0">
                  <input type="hidden" name="lines[{{ $index }}][market_item_found]" value="0">
                  <input type="checkbox" class="js-bar-market-found" name="lines[{{ $index }}][market_item_found]" value="1"
                    @checked($foundDefault)>
                  <span class="label-text"></span>
                </label>
              </div>
            </td>
            <td class="align-middle bar-market-col-name">
              <div class="bar-market-item-name">
                <div class="bar-market-item-name__title">{{ $line->item_name }}</div>
                @if($categoryName)
                  <span class="bar-market-item-name__tag">{{ $categoryName }}</span>
                @endif
              </div>
            </td>
            <td class="text-center align-middle">{{ $planDisplay }}</td>
            <td class="align-middle">
              <input type="number" class="form-control form-control-sm text-center js-bar-market-bought bar-market-no-spinners bar-market-field"
                name="lines[{{ $index }}][quantity_purchased]" step="1" min="0"
                value="{{ $foundDefault ? (int) round((float) $boughtDefault) : 0 }}">
            </td>
            <td class="align-middle">
              <select class="form-control form-control-sm js-bar-market-recv-pkg bar-market-field" name="lines[{{ $index }}][receiving_package_id]">
                <option value="">—</option>
                @foreach($packages as $package)
                  <option value="{{ $package->id }}" @selected($recvPkgDefault === (int) $package->id)>
                    {{ $package->abbreviation ?: $package->name }}
                  </option>
                @endforeach
              </select>
            </td>
            <td class="align-middle">
              <input type="hidden" class="js-bar-market-sell-pkg bar-market-field" name="lines[{{ $index }}][selling_package_id]"
                value="{{ $effectiveSellPkgId ?: '' }}">
              <span class="d-block text-center small js-bar-market-sell-pkg-label">
                {{ $hasMultipleSellMethods ? 'Multi' : $primaryUnitLabel }}
              </span>
            </td>
            <td class="align-middle">
              <input type="number" class="form-control form-control-sm text-center js-bar-market-line-total bar-market-no-spinners bar-market-field"
                name="lines[{{ $index }}][purchase_line_total]" step="0.01" min="0"
                value="{{ $lineTotalDefault !== null ? number_format((float) $lineTotalDefault, 2, '.', '') : '' }}">
              <small class="text-muted d-block js-bar-market-unit-cost-hint mt-1 text-center">Unit Cost: -</small>
            </td>
            <td class="align-middle bar-market-sell-price-cell">
              <button type="button"
                class="bar-market-sell-units-btn js-bar-market-sell-price-open {{ $unitsBtnState }}"
                data-units-set="{{ $sellUnitsSet }}"
                data-units-total="{{ max(1, $sellUnitsTotal) }}">
                <i class="fa fa-tag" aria-hidden="true"></i>
                <span class="js-bar-market-sell-units-btn-label">{{ $sellUnitsSet }}/{{ max(1, $sellUnitsTotal) }} units set</span>
              </button>
              <div class="js-bar-market-sell-option-price-fields">
                @foreach($sellMethodsWithPrices as $method)
                  @if((int) $method['package_id'] > 0)
                    <input type="hidden" class="js-bar-market-sell-option-price bar-market-field"
                      name="lines[{{ $index }}][sell_option_prices][{{ $method['package_id'] }}]"
                      value="{{ $method['saved_price'] !== null ? number_format($method['saved_price'], 2, '.', '') : '' }}"
                      data-package-id="{{ $method['package_id'] }}"
                      data-unit-label="{{ $method['unit_label'] }}"
                      data-pieces="{{ $method['pieces_per_sell_unit'] }}">
                  @endif
                @endforeach
                <input type="hidden" class="js-bar-market-sell-price bar-market-field" name="lines[{{ $index }}][selling_unit_price]"
                  value="{{ $sellingPriceDefault !== null && $sellingPriceDefault !== '' ? number_format((float) $sellingPriceDefault, 2, '.', '') : '' }}">
              </div>
            </td>
            <td class="align-middle text-center">
              <div class="bar-market-date-picker">
                <input type="date" class="bar-market-date-input js-bar-market-expiry bar-market-field" name="lines[{{ $index }}][expiry_date]"
                  value="{{ $expiryDefault }}" title="{{ $expiryDefault ?: 'Set expiry date' }}">
                <button type="button" class="btn btn-sm btn-light border bar-market-date-trigger" tabindex="-1" aria-label="Pick expiry date">
                  <i class="fa fa-calendar"></i>
                </button>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th colspan="7" class="text-right align-middle">
            TOTALS: <span class="text-muted font-weight-normal">(Planned: {{ number_format($plannedTotal, 2, '.', ',') }} {{ $currency }})</span>
          </th>
          <th colspan="2" class="text-right align-middle">
            <span class="bar-market-grand-total">
              <span class="js-bar-market-grand-total">—</span> <span class="js-bar-market-grand-currency">{{ $currency }}</span>
            </span>
          </th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="form-group">
        <label class="control-label">
          Payment method
          @if(! $poPaidFromAccounts)
            <span class="text-danger">*</span>
          @endif
          <small class="text-muted">(on complete)</small>
        </label>
        <select class="form-control form-control-sm" name="market_payment_type" @disabled($poPaidFromAccounts)>
          <option value="">— Select —</option>
          @foreach($paymentModes as $value => $label)
            <option value="{{ $value }}" @selected(old('market_payment_type', $purchaseList->market_payment_type ?: ($poPaidFromAccounts ? $purchaseList->purchaseOrder?->paymentExpense?->payment_type : null)) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        @if($poPaidFromAccounts)
          <input type="hidden" name="market_payment_type" value="{{ $purchaseList->purchaseOrder?->paymentExpense?->payment_type ?? 'cash' }}">
          <small class="text-muted d-block">Uses the payment method from Payables.</small>
        @endif
      </div>
    </div>
    <div class="col-md-8">
      <div class="form-group">
        <label class="control-label">Notes (optional)</label>
        <input type="text" class="form-control form-control-sm" name="market_completion_notes" maxlength="2000"
          value="{{ old('market_completion_notes', $purchaseList->market_completion_notes) }}">
      </div>
    </div>
  </div>

  <div class="tile-footer px-0 pb-0 text-right">
    <a href="{{ route('hotel.bar.index', ['tab' => 'market']) }}" class="btn btn-light border btn-sm mr-1">Cancel</a>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fa fa-check"></i> Complete purchase
    </button>
  </div>
</form>

<div class="modal fade" id="barMarketSellPriceModal" tabindex="-1" role="dialog" aria-labelledby="barMarketSellPriceModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered bar-market-sell-price-modal-dialog" role="document">
    <div class="modal-content bar-market-sell-price-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title bar-market-sell-price-modal__title" id="barMarketSellPriceModalTitle">
          <i class="fa fa-tag text-success"></i> Sell Price
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pt-2">
        <p class="bar-market-sell-price-modal__lead mb-3">
          Set the selling price for <strong class="js-bar-market-sell-modal-item-name">—</strong>
        </p>
        <div class="table-responsive">
          <table class="table table-bordered mb-0 bar-market-sell-price-modal-table">
            <thead>
              <tr>
                <th>Sale unit</th>
                <th class="text-center">Pcs</th>
                <th>Price ({{ $currency }})</th>
              </tr>
            </thead>
            <tbody class="js-bar-market-sell-modal-rows"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success js-bar-market-sell-modal-save">
          <i class="fa fa-check"></i> Save Prices
        </button>
      </div>
    </div>
  </div>
</div>

@once
  @push('styles')
    @include('hotel.erp.bar.partials._bar-market-page-styles')
    <style>
      .bar-market-sell-units-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
        border-radius: 4px;
        padding: 6px 8px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
        background: #f4faf4;
        border: 1px solid #6aab6a;
        color: #2d6a2d;
      }
      .bar-market-sell-units-btn.is-partial {
        background: #fffbea;
        border-color: #c9a227;
        color: #7a5d00;
      }
      .bar-market-sell-units-btn.is-empty {
        background: #fafafa;
        border-color: #bdbdbd;
        color: #616161;
      }
      .bar-market-sell-units-btn.is-complete {
        background: #e8f5e9;
        border-color: #2e7d32;
        color: #1b5e20;
      }
      .bar-market-sell-price-modal-dialog { max-width: 520px; }
      .bar-market-sell-price-modal__title { font-weight: 700; font-size: 18px; }
      .bar-market-sell-price-modal__title .fa { margin-right: 6px; }
      .bar-market-sell-price-modal-table thead th {
        background: #f5f5f5;
        font-size: 12px;
        font-weight: 700;
      }
      .bar-market-sell-price-modal-table td { vertical-align: middle; font-size: 13px; }
      .bar-market-sell-price-modal-table .bar-market-sell-modal-unit { font-weight: 700; }
      .bar-market-sell-modal-price-wrap input {
        text-align: left;
        width: 100%;
        min-width: 120px;
        display: block;
      }
      .bar-market-sell-price-modal-table .bar-market-sell-modal-price-wrap { min-width: 140px; }
      .bar-market-sell-modal-input-wrap { position: relative; display: block; }
      .bar-market-sell-modal-input-wrap .js-bar-market-sell-modal-price { padding-right: 26px !important; }
      .bar-market-sell-modal-status {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
        line-height: 1;
        pointer-events: none;
      }
      .bar-market-sell-modal-price-wrap input.is-valid-price {
        border-color: #2e7d32;
        background: #f1fbf1;
      }
      .bar-market-sell-modal-price-wrap input.is-below-min {
        border-color: #c62828;
        background: #fff5f5;
      }
      .bar-market-sell-modal-price-wrap input.is-checking {
        border-color: #c9a227;
        background: #fffdf5;
      }
      .bar-market-sell-modal-min {
        display: block;
        font-size: 11px;
        color: #888;
        margin-top: 4px;
        text-align: left;
      }
      .bar-market-sell-modal-hint {
        display: block;
        font-size: 11px;
        margin-top: 2px;
        text-align: left;
        line-height: 1.35;
      }
      .bar-market-sell-modal-hint.is-neutral { color: #777; }
      .bar-market-sell-modal-hint.is-checking { color: #856404; }
      .bar-market-sell-modal-hint.is-below-cost { color: #c62828; }
      .bar-market-sell-modal-hint.is-ok { color: #2e7d32; }
      .bar-market-sell-wrap {
        position: relative;
        display: flex;
        align-items: center;
      }
      .bar-market-sell-wrap .js-bar-market-sell-price {
        flex: 1;
        padding-right: 22px !important;
      }
      .bar-market-sell-status {
        position: absolute;
        right: 4px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
        line-height: 1;
        pointer-events: none;
      }
      .bar-market-sell-hint { font-size: 10px; line-height: 1.25; }
      .bar-market-sell-hint.is-below-cost { color: #c0392b; font-weight: 600; }
      .bar-market-sell-hint.is-ok { color: #1e7e34; }
      .bar-market-sell-hint.is-neutral { color: #6c757d; }
      .bar-market-sell-hint.is-checking { color: #856404; }
      .js-bar-market-sell-price.is-below-cost {
        border-color: #e74c3c;
        background-color: #fff5f5;
      }
      .js-bar-market-sell-price.is-ok { border-color: #28a745; }
    </style>
  @endpush
  @push('scripts')
    <script>
      (function ($) {
        function fmtDisplayMoney(n) {
          var v = Number(n);
          if (!isFinite(v) || v === 0) {
            return '—';
          }
          return v.toFixed(2);
        }

        function fmtRemaining(n) {
          var v = Number(n);
          if (!isFinite(v)) {
            return '—';
          }
          return v.toFixed(2);
        }

        function lineTotal($row) {
          if (!$row.find('.js-bar-market-found').is(':checked')) {
            return 0;
          }
          return parseFloat($row.find('.js-bar-market-line-total').val()) || 0;
        }

        function plannedUnitCost($row) {
          var fromData = parseFloat($row.data('plannedUnitCost'));
          if (isFinite(fromData) && fromData > 0) {
            return fromData;
          }
          var qty = parseFloat($row.find('.js-bar-market-bought').val()) || 0;
          var total = parseFloat($row.find('.js-bar-market-line-total').val()) || 0;
          if (qty > 0 && total > 0) {
            return total / qty;
          }

          return null;
        }

        function syncLineTotalFromBought($row) {
          if (!$row.find('.js-bar-market-found').is(':checked')) {
            return;
          }
          var unitCost = plannedUnitCost($row);
          if (unitCost === null) {
            return;
          }
          var qty = parseFloat($row.find('.js-bar-market-bought').val()) || 0;
          if (qty <= 0) {
            $row.find('.js-bar-market-line-total').val('');

            return;
          }
          var total = Math.round(qty * unitCost * 100) / 100;
          $row.find('.js-bar-market-line-total').val(total.toFixed(2));
        }

        function rememberUnitCostFromTotal($row) {
          var qty = parseFloat($row.find('.js-bar-market-bought').val()) || 0;
          var total = parseFloat($row.find('.js-bar-market-line-total').val()) || 0;
          if (qty > 0 && total > 0) {
            $row.data('plannedUnitCost', Math.round((total / qty) * 10000) / 10000);
          }
        }

        function unitBuyCost($row) {
          var qty = parseFloat($row.find('.js-bar-market-bought').val()) || 0;
          var total = parseFloat($row.find('.js-bar-market-line-total').val()) || 0;
          if (qty <= 0 || total <= 0) {
            return null;
          }
          return total / qty;
        }

        function unitCostHint($row) {
          var buy = unitBuyCost($row);
          var $hint = $row.find('.js-bar-market-unit-cost-hint');
          if (buy !== null) {
            $hint.text('Unit Cost: ' + buy.toFixed(2));
          } else {
            $hint.text('Unit Cost: -');
          }
        }

        function sellMultiplier($row) {
          var mult = parseFloat($row.data('sellMultiplier'));
          return isFinite(mult) && mult >= 1 ? mult : 1.35;
        }

        function sellPriceHint($row, phase) {
          phase = phase || 'idle';
          var buy = unitBuyCost($row);
          var $hint = $row.find('.js-bar-market-sell-hint');
          var $status = $row.find('.js-bar-market-sell-status');
          var $sellInput = $row.find('.js-bar-market-sell-price');
          var sellRaw = $.trim(String($sellInput.val() || ''));
          var sell = parseFloat(sellRaw);

          $sellInput.removeClass('is-below-cost is-ok is-checking');
          $hint.removeClass('is-below-cost is-ok is-neutral is-checking');
          $status.empty();

          if (buy === null) {
            $hint.addClass('is-neutral').text('Enter bought qty & total cost');
            return;
          }

          var suggested = buy * sellMultiplier($row);

          if (sellRaw === '') {
            $hint.addClass('is-neutral').html(
              'Buy: <strong>' + buy.toFixed(2) + '</strong> · Suggested: <strong>' + suggested.toFixed(2) + '</strong>'
            );
            return;
          }

          if (phase === 'typing') {
            $sellInput.addClass('is-checking');
            $hint.addClass('is-checking').text('Checking against buy price…');
            $status.html('<i class="fa fa-circle-o-notch fa-spin text-muted"></i>');
            return;
          }

          if (!isFinite(sell)) {
            $hint.addClass('is-neutral').text('Enter a valid sell price');
            return;
          }

          var profit = sell - buy;
          var markupPct = buy > 0 ? (profit / buy) * 100 : 0;
          var marginPct = sell > 0 ? (profit / sell) * 100 : 0;

          if (sell <= buy) {
            $sellInput.addClass('is-below-cost');
            $hint.addClass('is-below-cost').html(
              'Buy: ' + buy.toFixed(2) + ' · <strong>Below cost</strong> (' + profit.toFixed(2) + ')'
            );
            return;
          }

          $sellInput.addClass('is-ok');
          $status.html('<i class="fa fa-check-circle text-success"></i>');
          $hint.addClass('is-ok').html(
            'Buy: ' + buy.toFixed(2) + ' · Markup: +' + markupPct.toFixed(0) + '% · Margin: ' + marginPct.toFixed(0) + '% · Profit: ' + profit.toFixed(2)
          );
        }

        function setRowEnabled($row, found) {
          if (!found) {
            $row.find('.js-bar-market-bought').val(0);
          }
          $row.find('.bar-market-field').prop('disabled', !found);
        }

        function recalcMarketSummary() {
          var budget = parseFloat($('.js-bar-market-budget').val()) || 0;
          var used = 0;
          var missing = 0;
          var grand = 0;

          $('.js-bar-market-line').each(function () {
            var $row = $(this);
            var found = $row.find('.js-bar-market-found').is(':checked');
            if (!found) {
              missing += 1;
            }
            setRowEnabled($row, found);
            var lt = lineTotal($row);
            used += lt;
            grand += lt;
            unitCostHint($row);
          });

          var remaining = budget - used;
          $('.js-bar-market-used').text(fmtDisplayMoney(used));
          $('.js-bar-market-remaining').text(fmtRemaining(remaining));
          var $remCard = $('.js-bar-market-remaining-card');
          $remCard.toggleClass('bar-market-stat-card--over', budget > 0 && remaining < -0.009);
          var $remHint = $('.js-bar-market-remaining-hint');
          if (budget > 0 && remaining < -0.009) {
            $remHint.text('Over budget — reduce line totals before completing.');
          } else if (budget > 0 && remaining > 0.009) {
            $remHint.text('Unspent '+fmtRemaining(remaining)+' {{ $currency }} stays unspent; Payables will match amount used.');
          } else {
            $remHint.text('');
          }
          $('.js-bar-market-missing').text(missing);
          $('.js-bar-market-grand-total').text(fmtDisplayMoney(grand));
          $('.js-bar-market-grand-currency').toggle(grand !== 0);
        }

        var $sellPriceModal = null;
        var $sellModalActiveRow = null;
        var sellModalMethods = [];

        function fmtMoneyPlain(n) {
          var v = Number(n);
          if (!isFinite(v)) {
            return '—';
          }
          return v.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        function sellModalBuyPerPiece($row) {
          return unitBuyCost($row);
        }

        function minSellForMethod(buyPerPiece, pieces) {
          if (buyPerPiece === null || !isFinite(pieces) || pieces <= 0) {
            return null;
          }
          return buyPerPiece * pieces;
        }

        function updateSellUnitsButton($row) {
          var $btn = $row.find('.js-bar-market-sell-price-open');
          if (!$btn.length) {
            return;
          }
          var total = parseInt($row.data('sellUnitsTotal'), 10) || 0;
          if (!total) {
            total = sellMethodsForRow($row).length || 1;
          }
          var set = 0;
          $row.find('.js-bar-market-sell-option-price').each(function () {
            var v = parseFloat($(this).val());
            if (isFinite(v) && v > 0) {
              set += 1;
            }
          });
          if (!$row.find('.js-bar-market-sell-option-price').length) {
            var single = parseFloat($row.find('.js-bar-market-sell-price').val());
            if (isFinite(single) && single > 0) {
              set = 1;
            }
          }
          $btn.find('.js-bar-market-sell-units-btn-label').text(set + '/' + total + ' units set');
          $btn.removeClass('is-complete is-partial is-empty');
          if (set >= total && total > 0) {
            $btn.addClass('is-complete');
          } else if (set > 0) {
            $btn.addClass('is-partial');
          } else {
            $btn.addClass('is-empty');
          }
        }

        function syncPrimarySellFromOptions($row) {
          var methods = [];
          try {
            methods = JSON.parse($row.attr('data-sell-methods') || '[]');
          } catch (e) {
            methods = [];
          }
          if (!methods.length) {
            return;
          }
          methods.sort(function (a, b) {
            return (parseFloat(a.pieces_per_sell_unit) || 1) - (parseFloat(b.pieces_per_sell_unit) || 1);
          });
          var primary = methods[0];
          var $primaryHidden = $row.find('.js-bar-market-sell-option-price[data-package-id="' + primary.package_id + '"]');
          var primaryPrice = parseFloat($primaryHidden.val());
          if (isFinite(primaryPrice) && primaryPrice > 0) {
            $row.find('.js-bar-market-sell-price').val(primaryPrice);
            $row.find('.js-bar-market-sell-pkg').val(String(primary.package_id));
          }
        }

        var sellModalValidateTimers = {};

        function validateSellModalPriceInput($input, phase) {
          phase = phase || 'done';
          if (!$sellModalActiveRow) {
            return;
          }
          var $wrap = $input.closest('.bar-market-sell-modal-price-wrap');
          var $status = $wrap.find('.js-bar-market-sell-modal-status');
          var $min = $wrap.find('.js-bar-market-sell-modal-min');
          var $hint = $wrap.find('.js-bar-market-sell-modal-hint');
          var buyPerPiece = sellModalBuyPerPiece($sellModalActiveRow);
          var pieces = parseFloat($input.data('pieces')) || 1;
          var unitBuyCost = minSellForMethod(buyPerPiece, pieces);
          var mult = sellMultiplier($sellModalActiveRow);
          var sellRaw = $.trim(String($input.val() || ''));
          var sell = parseFloat(sellRaw);

          $input.removeClass('is-valid-price is-below-min is-checking');
          $hint.removeClass('is-neutral is-checking is-below-cost is-ok');
          $status.empty();

          if (unitBuyCost !== null) {
            $min.text('Min: ' + fmtMoneyPlain(unitBuyCost) + ' {{ $currency }}');
          } else {
            $min.text('Min: —');
          }

          if (buyPerPiece === null || unitBuyCost === null) {
            $hint.addClass('is-neutral').text('Enter bought qty & total cost on the row first');
            return;
          }

          var suggested = unitBuyCost * mult;

          if (sellRaw === '') {
            $hint.addClass('is-neutral').html(
              'Suggested: <strong>' + fmtMoneyPlain(suggested) + ' {{ $currency }}</strong>'
            );
            return;
          }

          if (phase === 'typing') {
            $input.addClass('is-checking');
            $hint.addClass('is-checking').text('Checking against buy cost…');
            $status.html('<i class="fa fa-circle-o-notch fa-spin text-muted"></i>');
            return;
          }

          if (!isFinite(sell) || sell <= 0) {
            $hint.addClass('is-neutral').text('Enter a valid sell price');
            return;
          }

          var profit = sell - unitBuyCost;
          var markupPct = unitBuyCost > 0 ? (profit / unitBuyCost) * 100 : 0;

          if (sell < unitBuyCost) {
            $input.addClass('is-below-min');
            $hint.addClass('is-below-cost').html(
              '<strong>Below cost</strong> by ' + fmtMoneyPlain(Math.abs(profit)) + ' {{ $currency }}'
            );
            return;
          }

          $input.addClass('is-valid-price');
          $status.html('<i class="fa fa-check-circle text-success"></i>');
          $hint.addClass('is-ok').html(
            'Markup: +' + markupPct.toFixed(0) + '% · Profit: ' + fmtMoneyPlain(profit) + ' {{ $currency }}'
          );
        }

        function refreshSellModalRowInputs(phase) {
          if (!$sellModalActiveRow) {
            return;
          }
          phase = phase || 'done';
          $('#barMarketSellPriceModal .js-bar-market-sell-modal-price').each(function () {
            validateSellModalPriceInput($(this), phase);
          });
        }

        function sellMethodsForRow($row) {
          var methods = [];
          try {
            var raw = $row.attr('data-sell-methods');
            if (raw) {
              methods = JSON.parse(raw);
            }
          } catch (e1) {
            methods = [];
          }
          if (!methods.length) {
            $row.find('.js-bar-market-sell-option-price').each(function () {
              var $h = $(this);
              var pkgId = parseInt($h.data('packageId'), 10);
              if (!pkgId) {
                return;
              }
              methods.push({
                package_id: pkgId,
                unit_label: $h.data('unitLabel') || 'Sale unit',
                pieces_per_sell_unit: parseFloat($h.data('pieces')) || 1,
                saved_price: $h.val() !== '' ? parseFloat($h.val()) : null,
              });
            });
          }
          if (!methods.length) {
            var sellPrice = parseFloat($row.find('.js-bar-market-sell-price').val());
            var pkgId = parseInt($row.find('.js-bar-market-sell-pkg').val(), 10) || 0;
            methods.push({
              package_id: pkgId,
              unit_label: $row.find('.js-bar-market-sell-pkg-label').text() || 'Sale unit',
              pieces_per_sell_unit: 1,
              saved_price: isFinite(sellPrice) ? sellPrice : null,
            });
          }

          return methods;
        }

        function openSellPriceModal($row) {
          $sellModalActiveRow = $row;
          sellModalMethods = sellMethodsForRow($row);
          $('.js-bar-market-sell-modal-item-name').text($row.data('itemName') || '—');

          var $tbody = $('.js-bar-market-sell-modal-rows');
          $tbody.empty();
          sellModalMethods.forEach(function (method) {
            var pkgId = method.package_id;
            var $hidden = $row.find('.js-bar-market-sell-option-price[data-package-id="' + pkgId + '"]');
            var saved = $hidden.length ? ($hidden.val() || '') : (method.saved_price != null ? String(method.saved_price) : '');
            var piecesDisplay = method.pieces_per_sell_unit;
            if (parseFloat(piecesDisplay) === parseInt(piecesDisplay, 10)) {
              piecesDisplay = parseInt(piecesDisplay, 10);
            }
            var $tr = $('<tr></tr>');
            $tr.append($('<td class="bar-market-sell-modal-unit"></td>').text(method.unit_label || '—'));
            $tr.append($('<td class="text-center"></td>').text(piecesDisplay));
            var $wrap = $('<td class="bar-market-sell-modal-price-wrap"></td>');
            var $inputWrap = $('<div class="bar-market-sell-modal-input-wrap"></div>');
            var $input = $('<input type="number" step="0.01" min="0" class="form-control form-control-sm js-bar-market-sell-modal-price bar-market-no-spinners">');
            $input.attr('data-package-id', pkgId);
            $input.attr('data-pieces', method.pieces_per_sell_unit || 1);
            if (saved !== '') {
              $input.val(saved);
            }
            $inputWrap.append($input);
            $inputWrap.append($('<span class="bar-market-sell-modal-status js-bar-market-sell-modal-status" aria-hidden="true"></span>'));
            $wrap.append($inputWrap);
            $wrap.append($('<small class="bar-market-sell-modal-min js-bar-market-sell-modal-min"></small>'));
            $wrap.append($('<small class="bar-market-sell-modal-hint js-bar-market-sell-modal-hint"></small>'));
            $tr.append($wrap);
            $tbody.append($tr);
          });

          if (!sellModalMethods.length) {
            $tbody.append(
              '<tr><td colspan="3" class="text-muted text-center small py-3">No sale units configured for this item.</td></tr>'
            );
          }

          refreshSellModalRowInputs();
          if (!$sellPriceModal) {
            $sellPriceModal = $('#barMarketSellPriceModal');
          }
          if (typeof $sellPriceModal.modal === 'function') {
            $sellPriceModal.modal('show');
          } else {
            $sellPriceModal.addClass('show').css('display', 'block');
          }
        }

        function saveSellPriceModal() {
          if (!$sellModalActiveRow) {
            return;
          }
          var $row = $sellModalActiveRow;
          $('#barMarketSellPriceModal .js-bar-market-sell-modal-price').each(function () {
            var $input = $(this);
            var pkgId = $input.data('packageId');
            var raw = $.trim(String($input.val() || ''));
            var priceVal = raw !== '' && !isNaN(parseFloat(raw)) ? parseFloat(raw) : '';
            var $hidden = $row.find('.js-bar-market-sell-option-price[data-package-id="' + pkgId + '"]');
            if ($hidden.length) {
              $hidden.val(priceVal);
            } else if (sellModalMethods.length === 1) {
              $row.find('.js-bar-market-sell-price').val(priceVal);
            }
          });
          syncPrimarySellFromOptions($row);
          if (!$row.find('.js-bar-market-sell-price').val()) {
            var $firstModalPrice = $('#barMarketSellPriceModal .js-bar-market-sell-modal-price').first();
            var firstVal = $.trim(String($firstModalPrice.val() || ''));
            if (firstVal !== '' && !isNaN(parseFloat(firstVal))) {
              $row.find('.js-bar-market-sell-price').val(parseFloat(firstVal));
            }
          }
          updateSellUnitsButton($row);
          $row.data('sellTouched', 1);
          $('#barMarketSellPriceModal').modal('hide');
        }

        $(function () {
          $('.js-bar-market-line-sell-modal').each(function () {
            updateSellUnitsButton($(this));
          });

          $(document).on('click', '.js-bar-market-sell-price-open', function (e) {
            e.preventDefault();
            openSellPriceModal($(this).closest('.js-bar-market-line'));
          });

          $(document).on('input', '.js-bar-market-sell-modal-price', function () {
            var $input = $(this);
            var key = String($input.data('packageId') || '') + '-' + ($sellModalActiveRow ? $sellModalActiveRow.data('lineIndex') : '');
            validateSellModalPriceInput($input, 'typing');
            if (sellModalValidateTimers[key]) {
              clearTimeout(sellModalValidateTimers[key]);
            }
            sellModalValidateTimers[key] = setTimeout(function () {
              validateSellModalPriceInput($input, 'done');
            }, 320);
          });

          $(document).on('blur', '.js-bar-market-sell-modal-price', function () {
            var $input = $(this);
            var key = String($input.data('packageId') || '') + '-' + ($sellModalActiveRow ? $sellModalActiveRow.data('lineIndex') : '');
            if (sellModalValidateTimers[key]) {
              clearTimeout(sellModalValidateTimers[key]);
            }
            validateSellModalPriceInput($input, 'done');
          });

          $(document).on('click', '.js-bar-market-sell-modal-save', function (e) {
            e.preventDefault();
            saveSellPriceModal();
          });

          $(document).on('input change', '.js-bar-market-bought', function () {
            var $row = $(this).closest('.js-bar-market-line');
            syncLineTotalFromBought($row);
            recalcMarketSummary();
            if ($('#barMarketSellPriceModal').hasClass('show') && $sellModalActiveRow) {
              refreshSellModalRowInputs('done');
            }
          });
          $(document).on('input change', '.js-bar-market-line-total', function () {
            var $row = $(this).closest('.js-bar-market-line');
            rememberUnitCostFromTotal($row);
            recalcMarketSummary();
            if ($('#barMarketSellPriceModal').hasClass('show') && $sellModalActiveRow) {
              refreshSellModalRowInputs('done');
            }
          });
          $(document).on('change', '.js-bar-market-found', function () {
            var $row = $(this).closest('.js-bar-market-line');
            if ($(this).is(':checked')) {
              syncLineTotalFromBought($row);
            }
            recalcMarketSummary();
          });
          $(document).on('click', '.bar-market-date-trigger', function () {
            $(this).siblings('.bar-market-date-input').trigger('click');
          });
          $('#barMarketItemsForm').on('submit', function (e) {
            var $form = $(this);
            $form.find('.bar-market-field').prop('disabled', false);

            var submitter = e.originalEvent && e.originalEvent.submitter;
            if (submitter && submitter.getAttribute('formaction')) {
              var formaction = String(submitter.getAttribute('formaction') || '');
              if (formaction.indexOf('market-draft') !== -1) {
                return true;
              }
            }

            if ($form.data('underBudgetConfirmed')) {
              $form.removeData('underBudgetConfirmed');
              return true;
            }

            var budget = parseFloat($('.js-bar-market-budget').val()) || 0;
            var used = 0;
            $('.js-bar-market-line').each(function () {
              used += lineTotal($(this));
            });

            if (budget > 0 && used > budget + 0.009) {
              e.preventDefault();
              var over = (used - budget).toFixed(2);
              if (typeof AppAlerts !== 'undefined') {
                AppAlerts.error(
                  'Over budget',
                  'Reduce line totals by '+over+' {{ $currency }}, or ask Accounts to revise the approved budget.'
                );
              }
              return false;
            }

            if (budget > 0 && used + 0.009 < budget) {
              e.preventDefault();
              var remaining = (budget - used).toFixed(2);
              var confirmText = 'You spent '+used.toFixed(2)+' of '+budget.toFixed(2)+' {{ $currency }} approved. '
                +remaining+' {{ $currency }} will stay unspent. Payables will be updated to '+used.toFixed(2)+' {{ $currency }}.';
              if (typeof AppAlerts !== 'undefined') {
                AppAlerts.confirm({
                  title: 'Complete with unspent budget?',
                  text: confirmText,
                  type: 'warning',
                  confirmText: 'Complete purchase',
                  cancelText: 'Go back',
                }, function () {
                  $form.data('underBudgetConfirmed', 1);
                  $form.trigger('submit');
                });
              } else if (window.confirm(confirmText)) {
                $form.data('underBudgetConfirmed', 1);
                $form.get(0).submit();
              }
              return false;
            }
          });
          recalcMarketSummary();
        });
      })(jQuery);
    </script>
  @endpush
@endonce
