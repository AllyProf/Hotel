@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $order = $goodsReceiveOrder;
  $readOnly = (bool) ($readOnly ?? $order->goods_received);
  $currency = strtoupper(trim((string) ($currency ?? $hotel->currency ?? 'TZS'))) ?: 'TZS';
  $plannedTotal = (float) $order->total;
  $packages = $packages ?? collect();
  $cancelUrl = route('hotel.stores.index', ['tab' => 'purchase-orders']);
  $receiveMoneyDecimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;
@endphp

@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($readOnly)
  <div class="alert alert-light border small mb-3">
    Received on {{ $order->goods_received_at?->format('d M Y h:i A') ?? '—' }}
    @if($order->goodsReceivedBy)
      by {{ $order->goodsReceivedBy->name }}
    @endif
  </div>
@endif

<form method="POST"
  action="{{ route('hotel.stores.invoices.goods-receive', $order) }}"
  id="storesPoReceiveForm"
  data-loader-text="Saving...">
  @csrf
  <input type="hidden" name="return_tab" value="purchase-orders">
  <input type="hidden" class="js-stores-po-budget" value="{{ number_format($plannedTotal, 2, '.', '') }}">

  <div class="row bar-market-summary-row mb-3">
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--budget">
        <span class="bar-market-stat-card__label">Budget ({{ $currency }})</span>
        <span class="bar-market-stat-card__value">{{ number_format($plannedTotal, 2, '.', ',') }}</span>
        <span class="bar-market-stat-card__hint">Approved total for this PO</span>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--used">
        <span class="bar-market-stat-card__label">Amount received</span>
        <span class="bar-market-stat-card__value"><span class="js-stores-po-used">0</span> <small>{{ $currency }}</small></span>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--remaining js-stores-po-remaining-card">
        <span class="bar-market-stat-card__label">Remaining</span>
        <span class="bar-market-stat-card__value"><span class="js-stores-po-remaining">0</span> <small>{{ $currency }}</small></span>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="bar-market-stat-card bar-market-stat-card--missing">
        <span class="bar-market-stat-card__label">Missing items</span>
        <span class="bar-market-stat-card__value js-stores-po-missing">0</span>
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
          <th class="text-center bar-market-col-bought">Received</th>
          <th class="bar-market-col-recv-pkg">Recv. pkg</th>
          <th class="bar-market-col-sell-pkg">Stock pkg</th>
          <th class="text-center bar-market-col-cost">Total Cost</th>
          <th class="text-center bar-market-col-sell-price">Unit rate</th>
          <th class="text-center bar-market-col-expiry">Notes</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->items as $index => $line)
          @php
            $master = $line->masterItem;
            $master?->loadMissing('category', 'storePackage');
            $planQty = (float) $line->quantity;
            $planDisplay = rtrim(rtrim(number_format($planQty, 2, '.', ''), '0'), '.');
            $statusDefault = old('lines.'.$line->id.'.receive_status', $line->receive_status ?? 'full');
            $foundDefault = $statusDefault !== 'missing';
            if (old('lines.'.$line->id.'.item_found') !== null) {
              $foundDefault = filter_var(old('lines.'.$line->id.'.item_found'), FILTER_VALIDATE_BOOLEAN);
            }
            $boughtDefault = old('lines.'.$line->id.'.received_quantity', $line->received_quantity ?? $planQty);
            $lineTotal = (float) $line->total;
            $unitRate = (float) old('lines.'.$line->id.'.received_unit_rate', $line->rate);
            $recvPkgDefault = (int) old(
              'lines.'.$line->id.'.hotel_store_package_id',
              $line->hotel_store_package_id ?? $master?->hotel_store_package_id ?? 0,
            );
            $sellPkgLabel = $master?->storePackage?->abbreviation ?: ($master?->storePackage?->name ?? '—');
            $categoryName = $master?->category?->name;
            $notesDefault = old('lines.'.$line->id.'.receive_notes', $line->receive_notes ?? '');
          @endphp
          <tr class="js-stores-po-line"
            data-plan="{{ $planQty }}"
            data-pre-tax="{{ number_format((float) $line->pre_tax, 2, '.', '') }}"
            data-plan-tax="{{ number_format((float) $line->tax, 2, '.', '') }}">
            <td class="align-middle text-center">
              @if($readOnly)
                {{ $foundDefault ? 'Yes' : 'No' }}
              @else
                <div class="animated-checkbox">
                  <label class="mb-0">
                    <input type="hidden" name="lines[{{ $line->id }}][item_found]" value="0">
                    <input type="checkbox" class="js-stores-po-found" name="lines[{{ $line->id }}][item_found]" value="1"
                      @checked($foundDefault)>
                    <span class="label-text"></span>
                  </label>
                </div>
              @endif
              <input type="hidden" name="lines[{{ $line->id }}][receive_status]" class="js-stores-po-receive-status" value="{{ $statusDefault }}">
            </td>
            <td class="align-middle bar-market-col-name">
              <div class="bar-market-item-name">
                <div class="bar-market-item-name__title">{{ $line->name }}</div>
                @if($categoryName)
                  <span class="bar-market-item-name__tag">{{ $categoryName }}</span>
                @endif
              </div>
            </td>
            <td class="text-center align-middle">{{ $planDisplay }}</td>
            <td class="align-middle text-center">
              @if($readOnly)
                {{ $boughtDefault }}
              @else
                <input type="number" class="form-control form-control-sm text-center js-stores-po-bought bar-market-no-spinners"
                  name="lines[{{ $line->id }}][received_quantity]" step="0.01" min="0"
                  value="{{ $foundDefault ? $boughtDefault : 0 }}">
              @endif
            </td>
            <td class="align-middle">
              @if($readOnly)
                {{ $line->storePackage?->abbreviation ?: ($packages->firstWhere('id', $recvPkgDefault)?->abbreviation ?: '—') }}
              @else
                <select class="form-control form-control-sm js-stores-po-recv-pkg"
                  name="lines[{{ $line->id }}][hotel_store_package_id]">
                  <option value="">—</option>
                  @foreach($packages as $package)
                    <option value="{{ $package->id }}" @selected($recvPkgDefault === (int) $package->id)>
                      {{ $package->abbreviation ?: $package->name }}
                    </option>
                  @endforeach
                </select>
              @endif
            </td>
            <td class="align-middle text-center small">{{ $sellPkgLabel }}</td>
            <td class="align-middle text-center">
              <span class="js-stores-po-line-total-display">{{ $storeItemService->formatMoney($lineTotal, $hotel) }}</span>
            </td>
            <td class="align-middle text-center">
              @if($readOnly)
                {{ $storeItemService->formatMoney($unitRate, $hotel) }}
              @else
                <input type="number" class="form-control form-control-sm text-center js-stores-po-unit-rate bar-market-no-spinners"
                  name="lines[{{ $line->id }}][received_unit_rate]" step="0.01" min="0"
                  value="{{ $foundDefault ? $unitRate : 0 }}">
              @endif
            </td>
            <td class="align-middle">
              @if($readOnly)
                {{ $notesDefault ?: '—' }}
              @else
                <input type="text" name="lines[{{ $line->id }}][receive_notes]" class="form-control form-control-sm"
                  maxlength="500" placeholder="Optional" value="{{ $notesDefault }}">
              @endif
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
              <span class="js-stores-po-grand-total">—</span> <span>{{ $currency }}</span>
            </span>
          </th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="row mt-4">
    <div class="col-md-12">
      <div class="form-group mb-0">
        <label class="control-label">Notes (optional)</label>
        @if($readOnly)
          <p class="text-muted mb-0">{{ $order->goods_receive_notes ?: '—' }}</p>
        @else
          <input type="text" class="form-control form-control-sm" name="goods_receive_notes" maxlength="2000"
            value="{{ old('goods_receive_notes', $order->goods_receive_notes) }}"
            placeholder="Delivery or receipt notes">
        @endif
      </div>
    </div>
  </div>

  <div class="tile-footer px-0 pb-0 text-right">
    <a href="{{ $cancelUrl }}" class="btn btn-light border btn-sm mr-1">{{ $readOnly ? 'Back' : 'Cancel' }}</a>
    @if(! $readOnly)
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fa fa-check"></i> Complete receive
      </button>
    @endif
  </div>
</form>

@if(! $readOnly)
@push('scripts')
<script>
(function ($) {
  var moneyDecimals = @json($receiveMoneyDecimals);

  function formatAmount(value) {
    var v = parseFloat(value) || 0;
    var factor = Math.pow(10, moneyDecimals);
    v = Math.round(v * factor) / factor;
    var parts = v.toFixed(moneyDecimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return moneyDecimals > 0 ? parts.join('.') : parts[0];
  }

  function receivedLineAmount($row) {
    if (!$row.find('.js-stores-po-found').is(':checked')) {
      return 0;
    }
    var bought = parseFloat($row.find('.js-stores-po-bought').val()) || 0;
    var unitRate = parseFloat($row.find('.js-stores-po-unit-rate').val()) || 0;
    if (bought <= 0 || unitRate <= 0) {
      return 0;
    }
    var plannedPreTax = parseFloat($row.data('pre-tax')) || 0;
    var plannedTax = parseFloat($row.data('plan-tax')) || 0;
    var preTax = bought * unitRate;
    var taxRatio = plannedPreTax > 0 ? plannedTax / plannedPreTax : 0;
    var tax = preTax * taxRatio;

    return preTax + tax;
  }

  function lineValue($row) {
    return receivedLineAmount($row);
  }

  function syncLineCostDisplay($row) {
    var amount = receivedLineAmount($row);
    $row.find('.js-stores-po-line-total-display').text(formatAmount(amount));
  }

  function syncReceiveStatus($row) {
    var plan = parseFloat($row.data('plan')) || 0;
    var $status = $row.find('.js-stores-po-receive-status');
    var $bought = $row.find('.js-stores-po-bought');
    var $unitRate = $row.find('.js-stores-po-unit-rate');
    var $recvPkg = $row.find('.js-stores-po-recv-pkg');
    if (!$row.find('.js-stores-po-found').is(':checked')) {
      $status.val('missing');
      $bought.val(0).prop('readonly', true);
      $unitRate.prop('readonly', true);
      $recvPkg.prop('disabled', true);
      return;
    }
    $bought.prop('readonly', false);
    $unitRate.prop('readonly', false);
    $recvPkg.prop('disabled', false);
    var bought = parseFloat($bought.val()) || 0;
    if (bought <= 0) {
      $status.val('missing');
    } else if (plan > 0 && bought + 0.001 < plan) {
      $status.val('partial');
    } else {
      $status.val('full');
      if (plan > 0 && bought > plan) {
        $bought.val(plan);
      }
    }
  }

  function recalcSummary() {
    var budget = parseFloat($('.js-stores-po-budget').val()) || 0;
    var used = 0;
    var missing = 0;
    $('.js-stores-po-line').each(function () {
      var $row = $(this);
      if (!$row.find('.js-stores-po-found').is(':checked')) {
        missing++;
      } else {
        used += lineValue($row);
      }
    });
    var remaining = budget - used;
    $('.js-stores-po-used').text(formatAmount(used));
    $('.js-stores-po-remaining').text(formatAmount(remaining));
    $('.js-stores-po-missing').text(missing);
    $('.js-stores-po-grand-total').text(used > 0 ? formatAmount(used) : '—');
    $('.js-stores-po-remaining-card').toggleClass('bar-market-stat-card--over', budget > 0 && remaining < -0.009);
  }

  function syncRow($row) {
    syncReceiveStatus($row);
    syncLineCostDisplay($row);
    recalcSummary();
  }

  $('.js-stores-po-line').each(function () { syncRow($(this)); });

  $(document).on('change', '.js-stores-po-found', function () {
    syncRow($(this).closest('.js-stores-po-line'));
  });
  $(document).on('input change', '.js-stores-po-bought, .js-stores-po-unit-rate', function () {
    syncRow($(this).closest('.js-stores-po-line'));
  });

  $('#storesPoReceiveForm').on('submit', function (e) {
    $(this).find('.js-stores-po-recv-pkg').prop('disabled', false);
    var submitter = e.originalEvent && e.originalEvent.submitter;
    if (submitter && submitter.getAttribute('formaction') && String(submitter.getAttribute('formaction')).indexOf('receive-draft') !== -1) {
      $('.js-stores-po-line').each(function () { syncReceiveStatus($(this)); });
      return true;
    }
    $('.js-stores-po-line').each(function () { syncReceiveStatus($(this)); });
  });
})(jQuery);
</script>
@endpush
@endif
