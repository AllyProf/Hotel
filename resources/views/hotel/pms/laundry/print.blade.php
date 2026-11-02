@extends('layouts.app')

@section('title', 'Laundry bill '.$order->ticket_no)

@push('styles')
  <style>
    :root { --ld-brand: #940000; }

    .ld-bill-wrap {
      max-width: 720px;
      margin: 0 auto;
      background: #fff;
      padding: 24px;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
    }

    .ld-bill-head {
      text-align: center;
      border-bottom: 3px solid var(--ld-brand);
      padding-bottom: 16px;
      margin-bottom: 20px;
    }

    .ld-bill-head h2 {
      margin: 0 0 4px;
      font-size: 22px;
      font-weight: 700;
      color: #111;
    }

    .ld-bill-head p {
      margin: 0;
      font-size: 13px;
      color: #666;
    }

    .ld-bill-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 24px;
      font-size: 13px;
      margin-bottom: 20px;
    }

    .ld-bill-meta dt {
      font-weight: 600;
      color: #6b7280;
      margin: 0;
    }

    .ld-bill-meta dd {
      margin: 0 0 8px;
      font-weight: 600;
      color: #111;
    }

    .ld-bill-table thead th {
      background: var(--ld-brand);
      color: #fff;
      font-size: 12px;
      border-color: #7a0000;
    }

    .ld-bill-totals {
      margin-top: 16px;
      max-width: 320px;
      margin-left: auto;
      font-size: 14px;
    }

    .ld-bill-totals .row-line {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
    }

    .ld-bill-totals .grand {
      border-top: 2px solid #111;
      margin-top: 8px;
      padding-top: 8px;
      font-size: 18px;
      font-weight: 700;
      color: var(--ld-brand);
    }

    .ld-bill-foot {
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px dashed #ccc;
      font-size: 12px;
      color: #666;
      text-align: center;
    }

    @media print {
      .app-sidebar,
      .app-header,
      .app-title,
      .ld-bill-actions,
      .sidebar-mini,
      .breadcrumb {
        display: none !important;
      }

      .app-content {
        margin: 0 !important;
        padding: 0 !important;
      }

      .ld-bill-wrap {
        border: none;
        max-width: 100%;
        padding: 0;
      }

      body { background: #fff !important; }
    }
  </style>
@endpush

@section('content')
  <div class="app-title ld-bill-actions">
    <div>
      <h1><i class="fa fa-print"></i> Laundry bill</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.laundry.show', $order) }}">{{ $order->ticket_no }}</a></li>
      <li class="breadcrumb-item">Print</li>
    </ul>
  </div>

  <div class="ld-bill-actions mb-3 text-center">
    <button type="button" class="btn btn-sv" style="width:auto;padding:8px 22px;" onclick="window.print()">
      <i class="fa fa-print"></i> Print bill
    </button>
    <a href="{{ route('hotel.laundry.show', $order) }}" class="btn btn-light border ml-2">Back to ticket</a>
  </div>

  <div class="ld-bill-wrap">
    <div class="ld-bill-head">
      <h2>{{ $hotel->display_name ?: $hotel->name }}</h2>
      @if($hotel->address)
        <p>{{ $hotel->address }}</p>
      @endif
      <p style="margin-top:10px;font-weight:700;color:var(--ld-brand);">LAUNDRY BILL</p>
    </div>

    <dl class="ld-bill-meta">
      <dt>Ticket</dt>
      <dd>{{ $order->ticket_no }}</dd>
      <dt>Date</dt>
      <dd>{{ $order->received_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</dd>
      <dt>Guest</dt>
      <dd>{{ $order->guest_name ?? '—' }}</dd>
      <dt>Room</dt>
      <dd>{{ $order->room_no ?? '—' }}</dd>
      <dt>Status</dt>
      <dd>{{ $order->statusLabel() }}</dd>
      @if($order->is_express)
        <dt>Service</dt>
        <dd>Express</dd>
      @endif
    </dl>

    <table class="table table-bordered ld-bill-table mb-0">
      <thead>
        <tr>
          <th>Item</th>
          <th class="text-right">Qty</th>
          <th class="text-right">Unit price</th>
          <th class="text-right">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->lines as $line)
          <tr>
            <td>
              {{ $line->item_name }}
              @if($line->is_complimentary)
                (Complimentary)
              @endif
              @if($line->category?->name)
                <br><small class="text-muted">{{ $line->category->name }}</small>
              @endif
            </td>
            <td class="text-right">{{ number_format((float) $line->quantity, $line->quantity == (int) $line->quantity ? 0 : 2) }}</td>
            <td class="text-right">{{ $order->formattedAmount((float) $line->unit_price, $hotel) }}</td>
            <td class="text-right">{{ $order->formattedAmount((float) $line->line_total, $hotel) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="ld-bill-totals">
      <div class="row-line">
        <span>Subtotal</span>
        <span>{{ $order->formattedAmount((float) $order->subtotal, $hotel) }}</span>
      </div>
      @if((float) $order->surcharge_amount > 0)
        <div class="row-line">
          <span>Express surcharge</span>
          <span>{{ $order->formattedAmount((float) $order->surcharge_amount, $hotel) }}</span>
        </div>
      @endif
      <div class="row-line grand">
        <span>Total due</span>
        <span>{{ $order->formattedTotal($hotel) }}</span>
      </div>
      @if(! $order->cm_reservation_id && (float) $order->total > 0 && ($order->payment_mode || (float) $order->amount_paid > 0))
        <div class="row-line text-muted" style="font-size:12px;">
          <span>Paid — {{ $order->payment_mode }}</span>
          <span>
            @if((float) $order->amount_paid > 0)
              Paid {{ $order->formattedAmount((float) $order->amount_paid, $hotel) }}
            @else
              —
            @endif
          </span>
        </div>
        @if($order->payment_notes)
          <p class="mb-0 text-muted" style="font-size:11px;">{{ $order->payment_notes }}</p>
        @endif
      @endif
    </div>

    @if($order->notes)
      <p class="mt-3 mb-0" style="font-size:13px;"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <div class="ld-bill-foot">
      <p class="mb-1">Please pay at the front desk or charge to your room folio as agreed.</p>
      <p class="mb-0">Thank you for your business.</p>
    </div>
  </div>
@endsection

@push('styles')
  <style>
    .btn-sv {
      background: #940000 !important;
      border-color: #940000 !important;
      color: #fff !important;
    }
  </style>
@endpush
