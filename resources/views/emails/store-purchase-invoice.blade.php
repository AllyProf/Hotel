@php
  $hotelName = $hotel->display_name ?: $hotel->name;
@endphp
<p>Hello{{ $order->vendor?->contact_person ? ' '.$order->vendor->contact_person : '' }},</p>
<p>Please find invoice details for purchase order <strong>{{ $order->po_number }}</strong> from {{ $hotelName }}.</p>
<ul>
  <li><strong>Invoice #:</strong> {{ $order->invoice_number ?? '—' }}</li>
  <li><strong>Invoice date:</strong> {{ $order->invoiced_at?->format('d M Y') ?? '—' }}</li>
  <li><strong>Total:</strong> {{ number_format((float) $order->total, 2) }}</li>
</ul>
<p>Thank you.</p>
