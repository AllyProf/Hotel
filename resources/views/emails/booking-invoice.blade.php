@php
  $payload = is_array($reservation->payload) ? $reservation->payload : [];
  $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];
  $hotelName = $hotel->display_name ?: $hotel->name;
@endphp

<p>Dear {{ $reservation->guestName() }},</p>

<p>Please find your booking invoice details below for <strong>{{ $hotelName }}</strong>.</p>

<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;font-size:14px;">
  <tr><th align="left">Booking ID</th><td>{{ $reservation->booking_id }}</td></tr>
  <tr><th align="left">Guest</th><td>{{ $reservation->guestName() }}</td></tr>
  <tr><th align="left">Check-in</th><td>{{ $reservation->checkinLabel() }}</td></tr>
  <tr><th align="left">Check-out</th><td>{{ $reservation->checkoutLabel() }}</td></tr>
  <tr><th align="left">Room</th><td>{{ $reservation->roomLabel() }}</td></tr>
  <tr><th align="left">Rooms</th><td>{{ $reservation->roomCount() ?: '—' }}</td></tr>
  <tr><th align="left">Guests</th><td>{{ $reservation->guestCount() ?: '—' }}</td></tr>
  <tr><th align="left">Total</th><td>{{ $reservation->priceLabel() }}</td></tr>
  <tr><th align="left">Amount paid</th><td>{{ number_format($reservation->effectiveAmountPaid(), 2) }} {{ $reservation->currency }}</td></tr>
  <tr><th align="left">Balance due</th><td>{{ $reservation->balanceLabel() }}</td></tr>
  @if(!empty($payload['bill_to']))
    <tr><th align="left">Bill to</th><td>{{ $payload['bill_to'] }}</td></tr>
  @endif
  @if(!empty($guest['email']))
    <tr><th align="left">Guest email</th><td>{{ $guest['email'] }}</td></tr>
  @endif
</table>

<p>Thank you for choosing {{ $hotelName }}.</p>
