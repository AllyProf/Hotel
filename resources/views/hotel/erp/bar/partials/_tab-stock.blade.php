@php
  $pieceUnit = $packages->first(fn ($p) => strtoupper((string) ($p->abbreviation ?? '')) === 'PIC')
    ?? $packages->first();
  $pieceLabel = strtoupper((string) ($pieceUnit?->abbreviation ?: 'PIC'));
  $stockRows = $stockItems ?? $items;
  $stockSearch = $stockSearch ?? '';
  $sellDisplayCurrency = strtoupper(trim((string) ($barOperatingCurrency ?? ''))) ?: 'TZS';
  $barMoneyDecimals = function (string $currency): int {
    return in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF', 'KES'], true) ? 0 : 2;
  };
  $formatBarSellMoney = function (float $amount, string $currency) use ($barMoneyDecimals): string {
    $decimals = $barMoneyDecimals($currency);

    return number_format($amount, $decimals, '.', ',');
  };
@endphp

<div class="row align-items-center mb-3">
  <div class="col-md-6 col-lg-5">
    <form method="GET" action="{{ route('hotel.bar.index') }}" class="mb-0" id="barStockSearchForm">
      <input type="hidden" name="tab" value="stock">
      <div class="input-group input-group-sm">
        <div class="input-group-prepend">
          <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
        </div>
        <input type="search" name="search" class="form-control js-bar-stock-search" id="barStockSearchInput"
          value="{{ $stockSearch }}" placeholder="Search item, category, unit…" autocomplete="off" spellcheck="false">
        @if($stockSearch !== '')
          <div class="input-group-append">
            <a href="{{ route('hotel.bar.index', ['tab' => 'stock']) }}" class="btn btn-light border" title="Clear search">Clear</a>
          </div>
        @endif
      </div>
    </form>
  </div>
  <div class="col-md-6 col-lg-7">
    <p class="text-muted small mb-0 mt-2 mt-md-0">
      On-hand after market receive. Bar stock is sellable; store stock is central store.
      @if($stockSearch !== '')
        <span class="js-bar-stock-match-count">{{ $stockRows->count() }}</span> match(es).
      @endif
    </p>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-hover table-bordered mb-0" id="barStockTable">
    <thead>
      <tr>
        <th>Item</th>
        <th>Category</th>
        <th>Receive unit</th>
        <th class="text-right">Sell price</th>
        <th class="text-center">Bar stock</th>
        <th class="text-center">Store stock</th>
        <th class="text-center">Reorder at</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($stockRows as $barItem)
        @php
          $barStock = $barItem->stock_on_hand !== null ? (float) $barItem->stock_on_hand : null;
          $storeStock = $barItem->store_stock_on_hand !== null ? (float) $barItem->store_stock_on_hand : null;
          $recvLabel = $barItem->package
            ? (trim((string) ($barItem->package->abbreviation ?? '')) ?: trim((string) ($barItem->package->name ?? '')))
            : '—';
          $hasAnyStock = ($barStock !== null && $barStock > 0) || ($storeStock !== null && $storeStock > 0);
          $barItem->loadMissing(['sellOptions.package', 'sellingPackage']);
          $itemCurrency = $sellDisplayCurrency;
          $sellPriceLines = [];
          $sellCandidates = $barItem->sellOptions
            ->filter(fn ($opt) => $opt->unit_price !== null && (float) $opt->unit_price > 0)
            ->sortBy(fn ($opt) => (float) ($opt->pieces_per_sell_unit ?? 1));
          foreach ($sellCandidates as $sellOpt) {
            $u = trim((string) ($sellOpt->package?->abbreviation ?: $sellOpt->package?->name ?: 'unit'));
            $sellPriceLines[] = [
              'amount' => $formatBarSellMoney((float) $sellOpt->unit_price, $itemCurrency),
              'currency' => $itemCurrency,
              'unit' => $u,
            ];
          }
          if ($sellPriceLines === [] && $barItem->unit_price !== null && (float) $barItem->unit_price > 0) {
            $u = trim((string) ($barItem->sellingPackage?->abbreviation ?: $barItem->sellingPackage?->name ?: 'unit'));
            $sellPriceLines[] = [
              'amount' => $formatBarSellMoney((float) $barItem->unit_price, $itemCurrency),
              'currency' => $itemCurrency,
              'unit' => $u,
            ];
          }
          $searchBlob = mb_strtolower(trim(implode(' ', array_filter([
            $barItem->item_name,
            $barItem->category?->name,
            $barItem->brand ?? '',
            $recvLabel !== '—' ? $recvLabel : '',
          ]))));
        @endphp
        <tr class="js-bar-stock-row" data-search="{{ e($searchBlob) }}">
          <td><strong>{{ $barItem->item_name }}</strong></td>
          <td>{{ $barItem->category?->name ?? '—' }}</td>
          <td>{{ $recvLabel }}</td>
          <td class="text-right">
            @if($sellPriceLines !== [])
              @foreach($sellPriceLines as $line)
                <div class="text-nowrap">
                  <strong>{{ $line['amount'] }}</strong>
                  <span class="text-muted small">{{ $line['currency'] }}</span>
                </div>
                <div class="text-muted small">per {{ $line['unit'] }}</div>
              @endforeach
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td class="text-center">
            @if($barStock !== null)
              <strong class="{{ $barItem->isLowStock() ? 'text-danger' : '' }}">
                {{ rtrim(rtrim(number_format($barStock, 2, '.', ''), '0'), '.') }}
              </strong>
              <span class="text-muted d-block small">{{ $recvLabel !== '—' ? $recvLabel : 'units' }}</span>
              @if($barItem->pieces_per_receive_unit !== null && (float) $barItem->pieces_per_receive_unit > 0 && $barStock > 0)
                @php $piecesTotal = $barStock * (float) $barItem->pieces_per_receive_unit; @endphp
                <span class="text-muted d-block small" style="font-size:10px;">
                  ≈ {{ rtrim(rtrim(number_format($piecesTotal, 2, '.', ''), '0'), '.') }} {{ $pieceLabel }}
                </span>
              @endif
            @else
              <span class="text-muted">0</span>
            @endif
          </td>
          <td class="text-center">
            @if($storeStock !== null && $storeStock > 0)
              <strong>{{ rtrim(rtrim(number_format($storeStock, 2, '.', ''), '0'), '.') }}</strong>
              <span class="text-muted d-block small">{{ $recvLabel !== '—' ? $recvLabel : 'units' }}</span>
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td class="text-center">
            @if($barItem->reorder_level !== null)
              {{ rtrim(rtrim(number_format((float) $barItem->reorder_level, 2, '.', ''), '0'), '.') }}
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td>
            @if($barItem->isLowStock())
              <span class="badge badge-danger">Low stock</span>
            @elseif($hasAnyStock)
              <span class="badge badge-success">In stock</span>
            @else
              <span class="badge badge-secondary">Empty</span>
            @endif
          </td>
        </tr>
      @empty
        <tr class="js-bar-stock-empty-row">
          <td colspan="8" class="text-center text-muted py-4">
            @if($stockSearch !== '')
              No items match “{{ $stockSearch }}”.
              <a href="{{ route('hotel.bar.index', ['tab' => 'stock']) }}">Clear search</a>
            @else
              No catalog items yet. <a href="{{ route('hotel.bar.items.create') }}">Register items</a> first.
            @endif
          </td>
        </tr>
      @endforelse
      <tr class="js-bar-stock-no-live-match d-none">
        <td colspan="8" class="text-center text-muted py-4">No items match your search.</td>
      </tr>
    </tbody>
  </table>
</div>

@once
  @push('scripts')
    <script>
      (function () {
        var input = document.getElementById('barStockSearchInput');
        if (!input) {
          return;
        }
        var rows = document.querySelectorAll('.js-bar-stock-row');
        var noMatch = document.querySelector('.js-bar-stock-no-live-match');

        function applyLiveFilter() {
          var q = (input.value || '').trim().toLowerCase();
          var visible = 0;
          rows.forEach(function (row) {
            var blob = (row.getAttribute('data-search') || '').toLowerCase();
            var show = q === '' || blob.indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) {
              visible += 1;
            }
          });
          if (noMatch) {
            noMatch.classList.toggle('d-none', q === '' || visible > 0);
          }
        }

        input.addEventListener('input', applyLiveFilter);
        applyLiveFilter();

        var form = document.getElementById('barStockSearchForm');
        if (form) {
          form.addEventListener('submit', function (e) {
            if (input.value.trim() === '') {
              e.preventDefault();
              window.location.href = '{{ route('hotel.bar.index', ['tab' => 'stock']) }}';
            }
          });
        }
      })();
    </script>
  @endpush
@endonce
