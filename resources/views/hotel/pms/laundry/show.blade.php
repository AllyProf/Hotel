@extends('layouts.app')

@section('title', $order->ticket_no)

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sv-show-head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      padding: 18px 20px;
      border-bottom: 3px solid var(--sv-brand);
    }

    .sv-show-head h3 {
      margin: 0 0 8px;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .sv-price-badge {
      display: inline-block;
      font-size: 12px;
      padding: 4px 10px;
      border-radius: 3px;
      background: #f3f4f6;
      color: #374151;
      margin-right: 6px;
      margin-bottom: 4px;
    }

    .ld-total {
      font-size: 20px;
      font-weight: 700;
      color: var(--sv-brand);
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-ticket"></i> {{ $order->ticket_no }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.laundry.index') }}">Laundry</a></li>
      <li class="breadcrumb-item">{{ $order->ticket_no }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="tile sv-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        <div class="sv-show-head">
          <div>
            <h3>{{ $order->ticket_no }}</h3>
            <span class="sv-price-badge">{{ $order->statusLabel() }}</span>
            @if($order->room_no)
              <span class="sv-price-badge">Room {{ $order->room_no }}</span>
            @endif
            @if($order->guest_name)
              <span class="sv-price-badge">{{ $order->guest_name }}</span>
            @endif
            @if($order->posted_to_folio)
              <span class="sv-price-badge" style="background:#dcfce7;color:#15803d;">Posted to folio</span>
            @endif
            <p class="text-muted small mb-0 mt-2">Expected ready: {{ $expectedReady->format('d/m/Y H:i') }}</p>
            @if($order->creator)
              <p class="text-muted small mb-0">Created by {{ $order->creator->name }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p>
            @endif
            @if($order->completer)
              <p class="text-muted small mb-0">Marked done by {{ $order->completer->name }} · {{ $order->delivered_at?->format('d/m/Y H:i') }}</p>
            @endif
          </div>
          <div class="text-md-right">
            <div class="ld-total">{{ $order->formattedTotal($hotel) }}</div>
            @if($order->surcharge_amount > 0)
              <p class="mb-0 text-muted small">Express +{{ $order->formattedAmount((float) $order->surcharge_amount, $hotel) }}</p>
            @endif
          </div>
        </div>

        <div class="sv-section">
          <h4 class="sv-section-title"><i class="fa fa-list"></i> Items</h4>
          <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Category</th>
                  <th>Qty</th>
                  <th>Unit price</th>
                  <th>Line total</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                @foreach($order->lines as $line)
                  <tr>
                    <td>
                      {{ $line->item_name }}
                      @if($line->is_complimentary)
                        <span class="sv-badge-custom">Free</span>
                      @endif
                    </td>
                    <td>{{ $line->category?->name ?? '—' }}</td>
                    <td>{{ number_format((float) $line->quantity, $line->quantity == (int) $line->quantity ? 0 : 2) }}</td>
                    <td>{{ $order->formattedAmount((float) $line->unit_price, $hotel) }}</td>
                    <td>{{ $order->formattedAmount((float) $line->line_total, $hotel) }}</td>
                    <td>{{ $line->item_notes ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @if(! $order->cm_reservation_id && (float) $order->total > 0 && ($order->payment_mode || (float) $order->amount_paid > 0))
            <div class="mt-3 pt-3 border-top">
              <h5 class="mb-2">Walk-in payment</h5>
              <p class="mb-1"><strong>Mode:</strong> {{ $order->payment_mode ?? '—' }}
                @if($order->payment_provider) · {{ $order->payment_provider }} @endif
              </p>
              @if((float) $order->amount_paid > 0)
                <p class="mb-1"><strong>Amount paid:</strong> {{ $order->formattedAmount((float) $order->amount_paid, $hotel) }}</p>
              @endif
              @if($order->payment_notes)
                <p class="mb-0 text-muted small">{{ $order->payment_notes }}</p>
              @endif
            </div>
          @endif

          @if($order->notes)
            <p class="text-muted mb-0 mt-3"><strong>Notes:</strong> {{ $order->notes }}</p>
          @endif
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="tile sv-page">
        <div class="sv-section">
          <h4 class="sv-section-title"><i class="fa fa-flag"></i> Status</h4>
          <a href="{{ route('hotel.laundry.print', $order) }}" class="btn btn-sv-outline mb-3" style="width:100%;" target="_blank" rel="noopener">
            <i class="fa fa-print"></i> Print guest bill
          </a>
          @if($order->isOpen())
            <p class="text-muted small">Mark complete when laundry is returned to the guest.</p>
            <form method="POST" action="{{ route('hotel.laundry.update-status', $order) }}" class="mb-3">
              @csrf
              <input type="hidden" name="status" value="delivered">
              <button type="submit" class="btn btn-sv">Mark done</button>
            </form>
          @else
            <p class="mb-3 text-success"><i class="fa fa-check"></i> This ticket is done.</p>
          @endif

          @if($order->cm_reservation_id && ! $order->posted_to_folio && $order->total > 0)
            <form method="POST" action="{{ route('hotel.laundry.post-folio', $order) }}" class="mb-2">
              @csrf
              <button type="submit" class="btn btn-sv-outline" style="width:100%;">Post charge to folio</button>
            </form>
          @endif

          @if($order->reservation)
            <a href="{{ route('hotel.reservations.show', $order->reservation) }}" class="btn btn-sv-edit mt-2">View reservation</a>
          @endif

          <a href="{{ route('hotel.laundry.index') }}" class="btn btn-light border mt-2" style="width:100%;">Back to list</a>
        </div>
      </div>
    </div>
  </div>
@endsection
