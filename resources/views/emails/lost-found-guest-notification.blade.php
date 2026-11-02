@php
  $hotelName = $hotel->display_name ?: $hotel->name;
  $guestName = $reservation->guestName();
  $roomLabel = $item->room_no ? 'Room '.$item->room_no : ($reservation->assignedRoomLabel() !== '—' ? $reservation->assignedRoomLabel() : 'our property');
  $contactEmail = $frontDeskEmail !== '' ? $frontDeskEmail : ($hotel->email ?? '');
  $contactPhone = trim((string) ($frontDeskPhone ?? $hotel->phone ?? ''));
  $mailto = $contactEmail !== '' ? 'mailto:'.$contactEmail.'?subject='.rawurlencode('Lost property inquiry · '.$item->reference_no) : null;
  $tel = $contactPhone !== '' ? 'tel:'.preg_replace('/\s+/', '', $contactPhone) : null;
@endphp

<p>Dear {{ $guestName }},</p>

<p>
  We are reaching out from <strong>{{ $hotelName }}</strong> because an item was found that may belong to you
  following your recent stay{{ $item->room_no ? ' in '.$roomLabel : '' }}.
</p>

<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;font-size:14px;max-width:520px;">
  <tr><th align="left">Reference</th><td>{{ $item->reference_no }}</td></tr>
  <tr><th align="left">Item</th><td>{{ $item->item_name }}</td></tr>
  <tr><th align="left">Category</th><td>{{ $item->categoryLabel() }}</td></tr>
  @if($item->logged_at)
    <tr><th align="left">Logged</th><td>{{ $item->logged_at->format('d M Y') }}</td></tr>
  @endif
  <tr><th align="left">Booking</th><td>{{ $reservation->booking_id }}</td></tr>
</table>

<p>
  If this might be yours, please contact our front desk so we can verify ownership and arrange collection.
  You may be asked for a brief description or ID.
</p>

@if($mailto || $tel)
  <p style="margin:24px 0;">
    @if($mailto)
      <a href="{{ $mailto }}"
        style="display:inline-block;padding:12px 20px;background:#940000;color:#fff;text-decoration:none;font-weight:600;border-radius:4px;margin-right:8px;">
        Reply to front desk
      </a>
    @endif
    @if($tel)
      <a href="{{ $tel }}"
        style="display:inline-block;padding:12px 20px;background:#333;color:#fff;text-decoration:none;font-weight:600;border-radius:4px;">
        Call {{ $contactPhone }}
      </a>
    @endif
  </p>
@endif

<p style="font-size:13px;color:#666;">
  If this item is not yours, please ignore this message. We will keep unclaimed items for a limited time per hotel policy.
</p>

<p>Thank you,<br>{{ $hotelName }}</p>
