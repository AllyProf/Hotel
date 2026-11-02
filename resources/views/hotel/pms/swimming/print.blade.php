@extends('layouts.print-document')

@section('title', 'Pool bill '.$visit->visit_no)

@push('styles')
  <style>
    :root { --co-brand: #940000; }
    .co-print-wrap { padding: 0; }
    .co-print-head { text-align: center; border-bottom: 3px solid var(--co-brand); padding-bottom: 16px; margin-bottom: 20px; }
    .co-print-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; font-size: 13px; margin-bottom: 20px; }
    .co-print-meta dt { font-weight: 600; color: #6b7280; margin: 0; }
    .co-print-meta dd { margin: 0 0 8px; font-weight: 600; }
    .co-print-totals { max-width: 320px; margin-left: auto; font-size: 16px; font-weight: 700; color: var(--co-brand); }
    @media screen { .co-print-wrap { border: 1px solid #e5e7eb; border-radius: 4px; padding: 24px; } }
  </style>
@endpush

@section('toolbar')
  <div class="print-doc-toolbar">
    <button type="button" class="btn btn-print-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
    <a href="{{ route('hotel.swimming.show', $visit) }}" class="btn btn-print-light">Back</a>
  </div>
@endsection

@section('content')
  <div class="co-print-wrap">
    <div class="co-print-head">
      <h2>{{ $hotel->display_name ?: $hotel->name }}</h2>
      <p style="font-weight:700;color:var(--co-brand);margin-top:8px;">SWIMMING POOL RECEIPT</p>
    </div>

    <dl class="co-print-meta">
      <dt>Reference</dt><dd>{{ $visit->visit_no }}</dd>
      <dt>Pool date</dt><dd>{{ $visit->scheduled_date?->format('d/m/Y') ?? '—' }}</dd>
      <dt>Checked in</dt><dd>{{ $visit->entered_at?->format('d/m/Y H:i') ?? 'Scheduled' }}</dd>
      <dt>Guest</dt><dd>{{ $visit->guest_name ?? '—' }}</dd>
      @if($visit->guest_phone)
        <dt>Phone</dt><dd>{{ $visit->guest_phone }}</dd>
      @endif
      @if($visit->guest_email)
        <dt>Email</dt><dd>{{ $visit->guest_email }}</dd>
      @endif
      <dt>Room</dt><dd>{{ $visit->room_no ?? '—' }}</dd>
      <dt>Rate plan</dt><dd>{{ $visit->rate_label ?? $visit->category?->name ?? 'Standard' }}</dd>
      <dt>Adults / children</dt><dd>{{ $visit->adults }} / {{ $visit->children }}</dd>
      <dt>Unit price</dt><dd>{{ number_format((float) $visit->unit_price, 2) }} {{ $visit->currency }}</dd>
    </dl>

    <div class="co-print-totals text-right">
      @if((float) ($visit->discount_amount ?? 0) > 0)
        <div>Subtotal: {{ number_format((float) ($visit->subtotal ?? $visit->total), 2) }} {{ $visit->currency }}</div>
        <div>Discount:
          @if($visit->discount_type === 'percent')
            {{ number_format((float) $visit->discount_value, 2) }}% (−{{ number_format((float) $visit->discount_amount, 2) }} {{ $visit->currency }})
          @else
            −{{ number_format((float) $visit->discount_amount, 2) }} {{ $visit->currency }}
          @endif
        </div>
      @endif
      <div><strong>Total: {{ $visit->formattedTotal($hotel) }}</strong></div>
      @if(! $visit->cm_reservation_id && (float) $visit->total > 0.009 && ($visit->payment_mode || (float) $visit->amount_paid > 0))
        <div class="mt-2" style="font-size:13px;">
          Walk-in payment: {{ $visit->payment_mode ?? '—' }}
          @if($visit->payment_provider) · {{ $visit->payment_provider }} @endif
          @if((float) $visit->amount_paid > 0)
            · Paid {{ number_format((float) $visit->amount_paid, 2) }} {{ $visit->currency }}
          @endif
          @if($visit->payment_notes)
            <br><span class="text-muted">{{ $visit->payment_notes }}</span>
          @endif
        </div>
      @endif
    </div>

    @if($visit->notes)
      <p class="mt-3 mb-0" style="font-size:13px;"><strong>Notes:</strong> {{ $visit->notes }}</p>
    @endif

    <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">Thank you — enjoy your swim.</p>
  </div>
@endsection
