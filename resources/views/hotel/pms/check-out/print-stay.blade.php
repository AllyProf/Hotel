@extends('layouts.print-document')

@section('title', 'Guest bills — '.$folio['booking_id'])

@push('styles')
  <style>
    :root { --co-brand: #940000; --co-brand-dark: #7a0000; }

    .co-print-wrap {
      background: #fff;
      padding: 0;
    }

    .co-print-head {
      text-align: center;
      border-bottom: 3px solid var(--co-brand);
      padding-bottom: 16px;
      margin-bottom: 20px;
    }

    .co-print-head h2 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
    .co-print-head p { margin: 0; font-size: 13px; color: #666; }
    .co-print-title { margin-top: 10px; font-weight: 700; color: var(--co-brand); font-size: 14px; }

    .co-print-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 24px;
      font-size: 13px;
      margin-bottom: 24px;
    }

    .co-print-meta dt { font-weight: 600; color: #6b7280; margin: 0; }
    .co-print-meta dd { margin: 0 0 8px; font-weight: 600; color: #111; }

    .co-print-section { margin-bottom: 28px; }

    .co-print-section h3 {
      font-size: 15px;
      font-weight: 700;
      color: var(--co-brand);
      margin: 0 0 12px;
      padding-bottom: 6px;
      border-bottom: 1px solid #e5e7eb;
    }

    .co-print-table thead th {
      background: var(--co-brand) !important;
      color: #fff !important;
      font-size: 12px;
      border-color: var(--co-brand-dark) !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .co-print-totals {
      max-width: 360px;
      margin-left: auto;
      font-size: 14px;
    }

    .co-print-totals .row-line {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
      gap: 16px;
    }

    .co-print-totals .grand {
      border-top: 2px solid #111;
      margin-top: 8px;
      padding-top: 8px;
      font-size: 17px;
      font-weight: 700;
      color: var(--co-brand);
    }

    .co-laundry-block {
      margin-top: 32px;
      padding-top: 20px;
      border-top: 2px dashed #ccc;
    }

    .co-laundry-block h4 {
      font-size: 14px;
      font-weight: 700;
      margin: 0 0 12px;
    }

    .co-print-foot {
      margin-top: 28px;
      padding-top: 16px;
      border-top: 1px dashed #ccc;
      font-size: 12px;
      color: #666;
      text-align: center;
    }

    @media screen {
      .co-print-wrap {
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        padding: 24px;
      }
    }
  </style>
@endpush

@section('toolbar')
  <div class="print-doc-toolbar">
    <button type="button" class="btn btn-print-primary" onclick="window.print()">
      <i class="fa fa-print"></i> Print all bills
    </button>
    <a href="{{ $stayUrl }}" class="btn btn-print-light">Back to stay</a>
  </div>
@endsection

@section('content')
  <div class="co-print-wrap">
    <div class="co-print-head">
      <h2>{{ $hotel->display_name ?: $hotel->name }}</h2>
      @if($hotel->address)
        <p>{{ $hotel->address }}</p>
      @endif
      <p class="co-print-title">GUEST FOLIO &amp; SERVICE BILLS</p>
    </div>

    <dl class="co-print-meta">
      <dt>Guest</dt>
      <dd>{{ $folio['guest'] }}</dd>
      <dt>Booking</dt>
      <dd>{{ $folio['booking_id'] }}</dd>
      <dt>Room</dt>
      <dd>{{ $folio['room'] ?: '—' }}</dd>
      <dt>Status</dt>
      <dd>{{ $folio['pms_status'] }}</dd>
      <dt>Check-in</dt>
      <dd>{{ $folio['checkin'] }}</dd>
      <dt>Check-out</dt>
      <dd>{{ $folio['checkout'] }}</dd>
      <dt>Statement date</dt>
      <dd>{{ $folio['as_of']->format('d/m/Y') }}</dd>
    </dl>

    <div class="co-print-section">
      <h3><i class="fa fa-file-text-o"></i> Folio summary</h3>
      @if(! empty($folio['folio_lines']))
        <table class="table co-print-table mb-0">
          <thead>
            <tr>
              <th>Description</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            @foreach($folio['folio_lines'] as $line)
              <tr>
                <td>
                  {{ $line['label'] }}
                  @if(! empty($line['detail']))
                    <br><small class="text-muted">{{ $line['detail'] }}</small>
                  @endif
                </td>
                <td class="text-right">{{ $formatter->formatMoney((float) $line['amount'], (string) $line['currency'], $hotel) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted mb-0" style="font-size:13px;">No folio charges recorded yet.</p>
      @endif

      <div class="co-print-totals mt-3">
        <div class="row-line">
          <span>Folio total</span>
          <span>{{ $formatter->formatMoney((float) $folio['folio_total'], $folio['currency'], $hotel) }}</span>
        </div>
        @if((float) $folio['amount_paid'] > 0.009)
          <div class="row-line">
            <span>Amount paid</span>
            <span>{{ $formatter->formatMoney((float) $folio['amount_paid'], $folio['currency'], $hotel) }}</span>
          </div>
        @endif
        <div class="row-line grand">
          <span>Balance due (folio)</span>
          <span>
            @if((float) $folio['balance_due'] <= 0.009)
              Paid
            @else
              {{ $formatter->formatMoney((float) $folio['balance_due'], $folio['currency'], $hotel) }}
            @endif
          </span>
        </div>
        @if((float) ($folio['unposted_laundry_total'] ?? 0) > 0.009)
          <div class="row-line" style="margin-top:10px;font-size:12px;color:#666;">
            <span>Separate laundry (not on folio yet)</span>
            <span>{{ $formatter->formatMoney((float) $folio['unposted_laundry_total'], $folio['currency'], $hotel) }}</span>
          </div>
        @endif
      </div>
    </div>

    @foreach($folio['laundry_orders'] as $order)
      <div class="co-laundry-block">
        <h4>Laundry bill — {{ $order->ticket_no }}
          @if($order->is_express)
            <span class="badge badge-warning">Express</span>
          @endif
          @if(! $order->posted_to_folio)
            <small class="text-muted">(not on folio)</small>
          @endif
        </h4>
        <dl class="co-print-meta" style="margin-bottom:12px;">
          <dt>Date</dt>
          <dd>{{ $order->received_at?->format('d/m/Y H:i') ?? '—' }}</dd>
          <dt>Status</dt>
          <dd>{{ $order->statusLabel() }}</dd>
        </dl>
        <table class="table co-print-table mb-0">
          <thead>
            <tr>
              <th>Item</th>
              <th class="text-right">Qty</th>
              <th class="text-right">Unit</th>
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
                </td>
                <td class="text-right">{{ number_format((float) $line->quantity, $line->quantity == (int) $line->quantity ? 0 : 2) }}</td>
                <td class="text-right">{{ $order->formattedAmount((float) $line->unit_price, $hotel) }}</td>
                <td class="text-right">{{ $order->formattedAmount((float) $line->line_total, $hotel) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div class="co-print-totals mt-2">
          <div class="row-line grand">
            <span>Ticket total</span>
            <span>{{ $order->formattedTotal($hotel) }}</span>
          </div>
        </div>
      </div>
    @endforeach

    <div class="co-print-foot">
      <p class="mb-1">This document includes the guest folio and individual service bills for this stay.</p>
      <p class="mb-0">Thank you for choosing {{ $hotel->display_name ?: $hotel->name }}.</p>
    </div>
  </div>
@endsection
