@extends('layouts.app')

@section('title', 'Receive stock · '.$purchaseList->reference_no)

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-truck"></i> Receive purchased stock</h1>
      <p>{{ $purchaseList->reference_no }} · {{ $purchaseList->department?->name ?? 'Bar' }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.bar.index', ['tab' => 'stock-receive']) }}">Bar · Receive stock</a></li>
      <li class="breadcrumb-item">{{ $purchaseList->reference_no }}</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row">
    <div class="col-lg-10">
      <div class="tile">
        <div class="tile-body">
          <p class="text-muted small mb-3">
            Each item goes to <strong>Store</strong> or <strong>Bar</strong> stock as chosen on the purchase list.
            Completed {{ $purchaseList->market_completed_at?->format('d M Y H:i') ?? '—' }}.
          </p>

          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-center">Qty (receiving unit)</th>
                  <th class="text-center">Send to</th>
                  <th class="text-right">Sell price ({{ $currency }})</th>
                </tr>
              </thead>
              <tbody>
                @foreach($purchaseList->lines as $line)
                  @if($line->market_item_found === false || (float) ($line->quantity_purchased ?? 0) <= 0)
                    @continue
                  @endif
                  @php
                    $qtyDisplay = rtrim(rtrim(number_format((float) $line->quantity_purchased, 2, '.', ''), '0'), '.');
                    $recvUnit = $line->marketReceivingUnitLabel();
                    $sellOptionPrices = is_array($line->market_sell_option_prices) ? $line->market_sell_option_prices : [];
                  @endphp
                  <tr>
                    <td>{{ $line->item_name }}</td>
                    <td class="text-center">
                      <strong>{{ $qtyDisplay }}</strong>
                      <span class="text-muted d-block small">{{ $recvUnit }}</span>
                    </td>
                    <td class="text-center">{{ $line->marketStockDestinationLabel() ?? '—' }}</td>
                    <td class="text-right">
                      @if(count($sellOptionPrices) > 1)
                        @foreach($sellOptionPrices as $packageId => $price)
                          @php
                            $pkg = $packages[(int) $packageId] ?? null;
                            $sellUnit = $pkg
                              ? (trim((string) ($pkg->abbreviation ?? '')) ?: trim((string) ($pkg->name ?? '')))
                              : 'Unit';
                          @endphp
                          <div class="small">
                            <strong>{{ number_format((float) $price, 2) }}</strong>
                            <span class="text-muted">/ {{ $sellUnit ?: 'unit' }}</span>
                          </div>
                        @endforeach
                      @elseif($line->selling_unit_price !== null && (float) $line->selling_unit_price > 0)
                        <strong>{{ number_format((float) $line->selling_unit_price, 2) }}</strong>
                        @php
                          $sellUnit = $line->sellingPackage
                            ? (trim((string) ($line->sellingPackage->abbreviation ?? '')) ?: trim((string) ($line->sellingPackage->name ?? '')))
                            : '';
                        @endphp
                        @if($sellUnit !== '')
                          <span class="text-muted d-block small">per {{ $sellUnit }}</span>
                        @endif
                      @elseif(count($sellOptionPrices) === 1)
                        @php
                          $price = (float) reset($sellOptionPrices);
                          $packageId = (int) array_key_first($sellOptionPrices);
                          $pkg = $packages[$packageId] ?? null;
                          $sellUnit = $pkg
                            ? (trim((string) ($pkg->abbreviation ?? '')) ?: trim((string) ($pkg->name ?? '')))
                            : '';
                        @endphp
                        <strong>{{ number_format($price, 2) }}</strong>
                        @if($sellUnit !== '')
                          <span class="text-muted d-block small">per {{ $sellUnit }}</span>
                        @endif
                      @else
                        —
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <form method="POST" action="{{ route('hotel.bar.purchase-lists.market-stock-receive', $purchaseList) }}" class="text-right mt-3">
            @csrf
            <a href="{{ route('hotel.bar.index', ['tab' => 'stock-receive']) }}" class="btn btn-light border btn-sm mr-1">Back</a>
            <button type="submit" class="btn btn-success btn-sm">
              <i class="fa fa-check"></i> Confirm receive &amp; view stock
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
