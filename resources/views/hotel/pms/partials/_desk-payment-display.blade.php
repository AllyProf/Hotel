@php
  $isWalkIn = empty($record->cm_reservation_id);
  $total = (float) ($record->total ?? 0);
  $paid = (float) ($record->amount_paid ?? 0);
@endphp
@if($isWalkIn && $total > 0.009 && ($record->payment_mode || $paid > 0))
  <tr><th>Walk-in payment</th><td>
    <strong>{{ $record->payment_mode ?? '—' }}</strong>
    @if($record->payment_provider)
      · {{ $record->payment_provider }}
    @endif
    @if($paid > 0)
      · {{ number_format($paid, 2) }} {{ $record->currency ?? '' }} paid
      @php $remaining = max(0, round($total - $paid, 2)); @endphp
      @if($remaining > 0.009)
        (balance {{ number_format($remaining, 2) }})
      @endif
    @endif
    @if($record->payment_notes)
      <br><span class="text-muted small">{{ $record->payment_notes }}</span>
    @endif
  </td></tr>
@endif
