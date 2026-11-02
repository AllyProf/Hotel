@inject('posOrderService', 'App\Services\Pos\PosOrderService')
@php
  $posUi = config('hotel_pos.ui', []);
  $currency = $currency ?? (string) ($order->currency ?: 'TZS');
  $paymentComments = trim((string) ($order->payment_notes ?? ''));
  if ($paymentComments === '' && $order->company) {
    $paymentComments = (string) $order->company->name;
  }
  $paidPlain = number_format(
    $posOrderService->roundForCurrency((float) $order->amount_paid, $currency),
    $posOrderService->currencyDecimalPlaces($currency),
    '.',
    ''
  );
  $invoiceTitle = $order->invoice_no
    ? (($posUi['invoice_title_prefix'] ?? 'Invoice').' - '.$order->invoice_no)
    : $order->order_no;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $invoiceTitle }}</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 960px; margin: 24px auto; color: #222; padding: 0 16px; }
    h1 { font-size: 20px; margin: 0 0 16px; font-weight: 600; }
    .details { margin-bottom: 20px; }
    .details label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; }
    .details input { width: 100%; max-width: 480px; padding: 8px 10px; border: 1px solid #ced4da; background: #f1f3f5; }
    .grid { display: flex; flex-wrap: wrap; gap: 20px; }
    .grid-main { flex: 1 1 520px; min-width: 0; }
    .grid-side { flex: 0 1 280px; min-width: 220px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 0; }
    th, td { padding: 10px 12px; border: 1px solid #dee2e6; }
    thead th { background: #343a40; color: #fff; text-align: left; font-weight: 600; }
    thead th.text-right, td.text-right { text-align: right; }
    tfoot td { background: #f8f9fa; font-weight: 700; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print" style="text-align:center;margin-bottom:16px">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Close</button>
  </div>

  <h1>{{ $invoiceTitle }}</h1>

  <div class="details">
    <label>{{ $posUi['details'] ?? 'Details' }}</label>
    <input type="text" readonly value="{{ $order->details ?? '' }}">
  </div>
  @if($posOrderService->orderWaiterSummary($order))
    <div class="details">
      <label>{{ $posUi['waiter'] ?? 'Waiter' }}</label>
      <input type="text" readonly value="{{ $posOrderService->orderWaiterSummary($order) }}">
    </div>
  @endif

  <div class="grid">
    <div class="grid-main">
      <table>
        <thead>
          <tr>
            <th>{{ $posUi['table_service_name'] ?? 'Service Name' }}</th>
            <th class="text-right">{{ $posUi['table_rate'] ?? 'Rate' }}</th>
            <th class="text-right">{{ $posUi['table_qty'] ?? 'Qty' }}</th>
            <th class="text-right">{{ $posUi['table_amt'] ?? 'Amt' }}</th>
            <th class="text-right">{{ $posUi['table_tax'] ?? 'Tax' }}</th>
            <th class="text-right">{{ $posUi['table_total'] ?? 'Total' }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($order->lines as $line)
            <tr>
              <td>{{ $line->item_name }}</td>
              <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $line->unit_price, $currency) }}</td>
              <td class="text-right">{{ (float) $line->quantity == (int) $line->quantity ? (int) $line->quantity : number_format((float) $line->quantity, 2) }}</td>
              <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $line->line_amount, $currency) }}</td>
              <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $line->tax_amount, $currency) }}</td>
              <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $line->line_total, $currency) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td>{{ $posUi['table_grand_total'] ?? 'Total :' }}</td>
            <td></td>
            <td class="text-right">{{ (float) $order->lines->sum('quantity') == (int) $order->lines->sum('quantity') ? (int) $order->lines->sum('quantity') : number_format((float) $order->lines->sum('quantity'), 2) }}</td>
            <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $order->subtotal, $currency) }}</td>
            <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $order->tax_total, $currency) }}</td>
            <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $order->total, $currency) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="grid-side">
      <table>
        <thead>
          <tr>
            <th>{{ $posUi['view_type_of_payment'] ?? 'Type of Payment' }}</th>
            <th>{{ $posUi['view_payment_comments'] ?? 'Comments' }}</th>
            <th class="text-right">{{ $posUi['view_payment_amount'] ?? 'Amount' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ $order->payment_mode ?: '—' }}</td>
            <td>{{ $paymentComments !== '' ? $paymentComments : '—' }}</td>
            <td class="text-right">{{ $paidPlain }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
