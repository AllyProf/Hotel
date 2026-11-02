<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoice {{ $order->invoice_number }} — {{ $order->po_number }}</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 13px; color: #222; margin: 24px; }
    h1 { font-size: 18px; margin: 0 0 8px; }
    .meta { margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background: #940000; color: #fff; text-align: left; border-color: #7a0000; }
    .text-right { text-align: right; }
    tfoot th { background: #f5f5f5; color: #222; }
    @media print { body { margin: 12px; } }
  </style>
</head>
<body onload="window.print()">
  <h1>Order # {{ $order->po_number }}</h1>
  <div class="meta">
    <div><strong>Vendor:</strong> {{ $order->vendor?->name ?? '—' }}</div>
    <div><strong>Invoice #:</strong> {{ $order->invoice_number ?? '—' }}</div>
    <div><strong>Invoice date:</strong> {{ $order->invoiced_at?->format('d-m-Y') ?? '—' }}</div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th class="text-right">Qty</th>
        <th class="text-right">Rate</th>
        <th class="text-right">Pre tax</th>
        <th class="text-right">Tax</th>
        <th class="text-right">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->items as $line)
        <tr>
          <td>{{ $line->name }}</td>
          <td class="text-right">{{ $line->quantity }}</td>
          <td class="text-right">{{ number_format((float) $line->rate, $moneyDecimals) }}</td>
          <td class="text-right">{{ number_format((float) $line->pre_tax, $moneyDecimals) }}</td>
          <td class="text-right">{{ number_format((float) $line->tax, $moneyDecimals) }}</td>
          <td class="text-right">{{ number_format((float) $line->total, $moneyDecimals) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <th>Total</th>
        <th class="text-right">{{ $order->items->sum('quantity') }}</th>
        <th></th>
        <th class="text-right">{{ number_format((float) $order->pre_tax, $moneyDecimals) }}</th>
        <th class="text-right">{{ number_format((float) $order->tax, $moneyDecimals) }}</th>
        <th class="text-right">{{ number_format((float) $order->total, $moneyDecimals) }}</th>
      </tr>
    </tfoot>
  </table>
</body>
</html>
