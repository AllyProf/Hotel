@extends('layouts.app')

@section('title', $booking->booking_no)

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
@endpush

@section('content')
  <div class="app-title">
    <div><h1><i class="fa fa-users"></i> {{ $booking->booking_no }}</h1></div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.conference.index') }}">Conference</a></li>
      <li class="breadcrumb-item">{{ $booking->booking_no }}</li>
    </ul>
  </div>

  <div class="row"><div class="col-md-10"><div class="tile sv-page sv-section">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="d-flex flex-wrap justify-content-between mb-3">
      <h3 class="mb-0" style="font-weight:400;">{{ $booking->event_name }}</h3>
      <div>
        <a href="{{ route('hotel.conference.print', $booking) }}" class="btn btn-light border" target="_blank"><i class="fa fa-print"></i> Print</a>
        @if($booking->reservation && ! $booking->posted_to_folio && (float) $booking->total > 0)
          <form method="POST" action="{{ route('hotel.conference.post-folio', $booking) }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-sv" style="width:auto;padding:6px 14px;">Post to folio</button>
          </form>
        @endif
      </div>
    </div>

    <table class="table table-bordered">
      <tr><th style="width:180px;">Status</th><td>{{ $booking->statusLabel() }}</td></tr>
      <tr><th>Schedule</th><td>{{ $booking->starts_at?->format('d/m/Y H:i') }} – {{ $booking->ends_at?->format('d/m/Y H:i') }}</td></tr>
      <tr><th>Package</th><td>{{ $booking->rate_label ?? '—' }}</td></tr>
      <tr><th>Delegates</th><td>{{ $booking->delegates }}</td></tr>
      @if($booking->company_name)<tr><th>Company</th><td>{{ $booking->company_name }}</td></tr>@endif
      @if($booking->organizer_name)<tr><th>Organizer</th><td>{{ $booking->organizer_name }}</td></tr>@endif
      @if($booking->contact_phone)<tr><th>Phone</th><td>{{ $booking->contact_phone }}</td></tr>@endif
      @if($booking->room_no)<tr><th>Room</th><td>{{ $booking->room_no }}</td></tr>@endif
      <tr><th>Total</th><td><strong>{{ $booking->formattedTotal($hotel) }}</strong></td></tr>
      @include('hotel.pms.partials._desk-payment-display', ['record' => $booking])
      <tr><th>Folio</th><td>{{ $booking->posted_to_folio ? 'Posted' : 'Not posted' }}</td></tr>
      @if($booking->notes)<tr><th>Notes</th><td>{{ $booking->notes }}</td></tr>@endif
    </table>

    <a href="{{ route('hotel.conference.index') }}" class="btn btn-light border"><i class="fa fa-arrow-left"></i> Back</a>
  </div></div></div>
@endsection
