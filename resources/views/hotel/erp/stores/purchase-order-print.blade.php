<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Purchase list {{ $order->po_number }}</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 13px; color: #222; margin: 24px; }
    h1 { font-size: 18px; margin: 0 0 8px; }
    .meta { margin-bottom: 16px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background: #940000; color: #fff; text-align: left; border-color: #7a0000; }
    .text-right { text-align: right; }
    tfoot th { background: #f5f5f5; color: #222; }
    @media print { body { margin: 12px; } .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print" style="margin-bottom:16px;">
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}">Back to Stores</a>
  </div>
  <h1>Store purchase list — {{ $order->po_number }}</h1>
  <div class="meta">
    <div><strong>Vendor:</strong> {{ $order->vendor?->name ?? '—' }}</div>
    <div><strong>Department:</strong> {{ $order->storeDepartment?->name ?? '—' }}</div>
    <div><strong>Prepared by:</strong> {{ $order->creator?->name ?? '—' }}</div>
    <div><strong>Approved for market:</strong> {{ $order->accounts_reviewed_at?->format('d M Y H:i') ?? '—' }}</div>
    <div><strong>Currency:</strong> {{ $currency }}</div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Item</th>
        <th class="text-right">Qty</th>
        <th class="text-right">Buying price</th>
        <th class="text-right">Tax</th>
        <th class="text-right">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->items as $index => $line)
        @php
          $qty = (float) $line->quantity;
          $qtyDisplay = abs($qty - round($qty)) < 0.001 ? (string) (int) round($qty) : number_format($qty, 2, '.', ',');
          $pkg = trim((string) ($line->masterItem?->storePackage?->name ?? $line->masterItem?->unit ?? ''));
          $qtyLabel = $pkg !== '' ? $qtyDisplay.' '.$pkg : $qtyDisplay;
        @endphp
        <tr>
          <td>{{ $index + 1 }}</td>
          <td>{{ $line->name }}</td>
          <td class="text-right">{{ $qtyLabel }}</td>
          <td class="text-right">{{ number_format((float) $line->rate, $moneyDecimals) }}</td>
          <td class="text-right">{{ number_format((float) $line->tax, $moneyDecimals) }}</td>
          <td class="text-right">{{ number_format((float) $line->total, $moneyDecimals) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <th colspan="5" class="text-right">PO total ({{ $currency }})</th>
        <th class="text-right">{{ number_format((float) $order->total, $moneyDecimals) }}</th>
      </tr>
    </tfoot>
  </table>
</body>
</html>
