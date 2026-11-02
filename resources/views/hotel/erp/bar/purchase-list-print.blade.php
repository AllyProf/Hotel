@extends('layouts.print-document')

@section('title', 'Bar purchase list '.$purchaseList->reference_no)

@push('styles')
  <style>
    :root { --co-brand: #940000; }
    .co-print-wrap { padding: 0; }
    .co-print-head {
      text-align: center;
      border-bottom: 3px solid var(--co-brand);
      padding-bottom: 16px;
      margin-bottom: 20px;
    }
    .co-print-head h2 { margin: 0 0 4px; font-size: 22px; }
    .co-print-head p { margin: 0; font-size: 13px; color: #666; }
    .co-print-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 24px;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .co-print-meta dt { font-weight: 600; color: #6b7280; margin: 0; }
    .co-print-meta dd { margin: 0 0 8px; font-weight: 600; color: #111; }
    .co-print-table thead th {
      background: var(--co-brand);
      color: #fff;
      font-size: 12px;
      border-color: #7a0000;
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
    <button type="button" class="btn btn-print-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
    @if(empty($sharedView))
      <a href="{{ route('hotel.bar.purchase-lists.show', $purchaseList) }}" class="btn btn-print-light">Back</a>
    @endif
  </div>
@endsection

@section('content')
  @php
    $qty = fn ($line) => rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.');
  @endphp
  <div class="co-print-wrap">
    <div class="co-print-head">
      <h2>{{ $hotel->display_name ?: $hotel->name }}</h2>
      @if($hotel->address)
        <p>{{ $hotel->address }}</p>
      @endif
      <p style="font-weight:700;color:var(--co-brand);margin-top:10px;">BAR PURCHASE LIST</p>
      <p style="margin-top:6px;">Reference <strong>{{ $purchaseList->reference_no }}</strong></p>
    </div>

    <dl class="co-print-meta">
      <dt>Sent</dt>
      <dd>{{ $purchaseList->sent_at?->format('d/m/Y H:i') ?? '—' }}</dd>
      <dt>Prepared by</dt>
      <dd>{{ $purchaseList->creator?->name ?? '—' }}</dd>
      <dt>Status</dt>
      <dd>{{ $purchaseList->status === \App\Models\HotelBarPurchaseList::STATUS_SENT ? 'Sent' : 'Failed' }}</dd>
      <dt>Recipients</dt>
      <dd>{{ implode(', ', $purchaseList->sent_to ?? []) ?: '—' }}</dd>
    </dl>

    @if($purchaseList->notes)
      <p class="mb-3" style="font-size:13px;"><strong>Notes for stores / procurement:</strong> {{ $purchaseList->notes }}</p>
    @endif

    <table class="table co-print-table mb-0">
      <thead>
        <tr>
          <th style="width:36px;">#</th>
          <th>Item</th>
          <th class="text-right" style="width:80px;">Qty</th>
          <th style="width:100px;">Unit</th>
          <th>Line notes</th>
        </tr>
      </thead>
      <tbody>
        @foreach($purchaseList->lines as $index => $line)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $line->item_name }}</td>
            <td class="text-right">{{ $qty($line) }}</td>
            <td>{{ $line->unit ?: '—' }}</td>
            <td>{{ $line->line_notes ?: '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">
      Generated {{ now()->format('d/m/Y H:i') }} · {{ config('app.name') }}
    </p>
  </div>
@endsection
