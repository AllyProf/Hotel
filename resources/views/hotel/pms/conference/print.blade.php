@extends('layouts.print-document')

@section('title', 'Event '.$booking->booking_no)

@section('content')
  <div class="p-4">
    <h2 class="text-center mb-1">{{ $hotel->name ?? 'Hotel' }}</h2>
    <p class="text-center text-muted mb-4">Conference / event confirmation · {{ $booking->booking_no }}</p>

    <dl class="row mb-0" style="font-size:14px;">
      <dt class="col-sm-3">Event</dt><dd class="col-sm-9">{{ $booking->event_name }}</dd>
      <dt class="col-sm-3">When</dt><dd class="col-sm-9">{{ $booking->starts_at?->format('d/m/Y H:i') }} – {{ $booking->ends_at?->format('d/m/Y H:i') }}</dd>
      <dt class="col-sm-3">Package</dt><dd class="col-sm-9">{{ $booking->rate_label }}</dd>
      <dt class="col-sm-3">Delegates</dt><dd class="col-sm-9">{{ $booking->delegates }}</dd>
      @if($booking->company_name)<dt class="col-sm-3">Company</dt><dd class="col-sm-9">{{ $booking->company_name }}</dd>@endif
    </dl>

    <div class="text-right mt-4">
      <strong>Total: {{ $booking->formattedTotal($hotel) }}</strong>
      @if(! $booking->cm_reservation_id && (float) $booking->amount_paid > 0)
        <div class="text-muted small">Paid {{ number_format((float) $booking->amount_paid, 2) }} {{ $booking->currency }} — {{ $booking->payment_mode }}</div>
      @endif
    </div>
  </div>
@endsection
