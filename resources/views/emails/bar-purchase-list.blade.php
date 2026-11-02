@php
  $hotel = $purchaseList->hotel;
  $hotelName = $hotel->display_name ?: $hotel->name;
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Bar purchase list</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; line-height:1.5;">
  <h2 style="color:#940000;margin:0 0 8px;">Bar purchase list</h2>
  <p style="margin:0 0 16px;">
    <strong>{{ $hotelName }}</strong><br>
    Reference: <strong>{{ $purchaseList->reference_no }}</strong><br>
    Prepared by: {{ $purchaseList->creator?->name ?? 'Bar team' }}<br>
    Date: {{ $purchaseList->sent_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}
  </p>

  @if($purchaseList->notes)
    <p style="margin:0 0 16px;"><strong>Notes:</strong> {{ $purchaseList->notes }}</p>
  @endif

  <p style="margin:0 0 20px;">
    <a href="{{ $viewUrl }}" style="display:inline-block;background:#940000;color:#fff;text-decoration:none;padding:10px 18px;border-radius:4px;font-weight:600;">
      Open list — review &amp; prepare budget
    </a>
  </p>
  <p style="margin:0 0 20px;font-size:12px;color:#666;">Adjust quantities or costs if needed, then submit the budget. No login required. Link expires after 90 days.</p>

  <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;max-width:640px;font-size:14px;">
    <thead style="background:#555;color:#fff;">
      <tr>
        <th align="left">#</th>
        <th align="left">Item</th>
        <th align="right">Qty</th>
        <th align="left">Unit</th>
        <th align="left">Notes</th>
      </tr>
    </thead>
    <tbody>
      @foreach($purchaseList->lines as $index => $line)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td>{{ $line->item_name }}</td>
          <td align="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }}</td>
          <td>{{ $line->unit ?: '—' }}</td>
          <td>{{ $line->line_notes ?: '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <p style="margin:24px 0 0;font-size:12px;color:#666;">Sent from {{ config('app.name') }} · Bar module</p>
</body>
</html>
