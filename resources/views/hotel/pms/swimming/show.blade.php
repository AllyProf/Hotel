@extends('layouts.app')

@section('title', $visit->visit_no)

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-tint"></i> {{ $visit->visit_no }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.swimming.index') }}">Swimming</a></li>
      <li class="breadcrumb-item">{{ $visit->visit_no }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile sv-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        <div class="sv-section">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h3 class="mb-0">{{ $visit->guest_name }}</h3>
            <div class="d-flex flex-wrap gap-2">
              <a href="{{ route('hotel.swimming.print', $visit) }}" class="btn btn-light border" target="_blank" rel="noopener"><i class="fa fa-print"></i> Print</a>
              @if($visit->status === 'scheduled')
                <form method="POST" action="{{ route('hotel.swimming.check-in', $visit) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 18px;">Check in to pool</button>
                </form>
              @endif
              @if($visit->status === 'in_pool')
                <form method="POST" action="{{ route('hotel.swimming.complete', $visit) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 18px;">Mark left pool</button>
                </form>
              @endif
              @if(! $visit->posted_to_folio && $visit->reservation && (float) $visit->total > 0)
                <form method="POST" action="{{ route('hotel.swimming.post-folio', $visit) }}" class="d-inline js-swal-add">
                  @csrf
                  <button type="button" class="btn btn-sv-edit" style="width:auto;padding:8px 18px;"
                    data-title="Post to folio?"
                    data-text="Charge {{ $visit->formattedTotal($hotel) }} to room folio?"
                    data-confirm="Post charge"
                    data-cancel="Cancel">Post to folio</button>
                </form>
              @endif
            </div>
          </div>

          <table class="table table-bordered">
            <tr><th style="width:180px;">Status</th><td>{{ $visit->statusLabel() }}</td></tr>
            <tr><th>Pool date</th><td>{{ $visit->scheduled_date?->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th>Rate plan</th><td>{{ $visit->rate_label ?? $visit->category?->name ?? '—' }}</td></tr>
            <tr><th>Room</th><td>{{ $visit->room_no ?? '—' }}</td></tr>
            @if($visit->guest_phone || $visit->guest_email)
              <tr><th>Phone</th><td>{{ $visit->guest_phone ?? '—' }}</td></tr>
              <tr><th>Email</th><td>{{ $visit->guest_email ?? '—' }}</td></tr>
            @endif
            <tr><th>Adults / children</th><td>{{ $visit->adults }} / {{ $visit->children }}</td></tr>
            <tr><th>Charge by</th><td>{{ $visit->swimmingRate?->pricingUnitLabel() ?? ucfirst(str_replace('_', ' ', $visit->pricing_unit ?? '')) }}</td></tr>
            <tr><th>Unit price</th><td>{{ number_format((float) $visit->unit_price, 2) }} {{ $visit->currency }}</td></tr>
            @if((float) ($visit->subtotal ?? $visit->total) > 0 && (float) ($visit->discount_amount ?? 0) > 0)
              <tr><th>Subtotal</th><td>{{ number_format((float) ($visit->subtotal ?? $visit->total), 2) }} {{ $visit->currency }}</td></tr>
              <tr><th>Discount</th><td>
                @if($visit->discount_type === 'percent')
                  {{ number_format((float) $visit->discount_value, 2) }}% (−{{ number_format((float) $visit->discount_amount, 2) }} {{ $visit->currency }})
                @else
                  −{{ number_format((float) $visit->discount_amount, 2) }} {{ $visit->currency }}
                @endif
              </td></tr>
            @endif
            <tr><th>Total</th><td><strong>{{ $visit->formattedTotal($hotel) }}</strong></td></tr>
            @include('hotel.pms.partials._desk-payment-display', ['record' => $visit])
            <tr><th>Folio</th><td>{{ $visit->posted_to_folio ? 'Posted' : 'Not posted' }}</td></tr>
            <tr><th>Entered</th><td>{{ $visit->entered_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
            <tr><th>Exited</th><td>{{ $visit->exited_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
            <tr><th>Registered by</th><td>{{ $visit->creator?->name ?? '—' }}</td></tr>
            @if($visit->notes)
              <tr><th>Notes</th><td>{{ $visit->notes }}</td></tr>
            @endif
          </table>

          <a href="{{ route('hotel.swimming.index') }}" class="btn btn-light border"><i class="fa fa-arrow-left"></i> Back to pool desk</a>
        </div>
      </div>
    </div>
  </div>
@endsection
