@extends('layouts.app')

@section('title', 'Received · '.$purchaseList->reference_no)

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-inbox"></i> {{ $purchaseList->reference_no }}</h1>
      <p>{{ $purchaseList->department?->name ?? 'Department' }} · received request</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.bar.index', ['tab' => 'received']) }}">Bar · Received</a></li>
      <li class="breadcrumb-item">{{ $purchaseList->reference_no }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="tile-title">What was sent</h3>
          <div>
            @if($purchaseList->isAccountingRejected())
              <a href="{{ route('hotel.bar.purchase-lists.respond', $purchaseList) }}" class="btn btn-primary mr-2">
                <i class="fa fa-edit"></i> Revise budget
              </a>
            @elseif($purchaseList->recipient_status !== \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
              <a href="{{ route('hotel.bar.purchase-lists.respond', $purchaseList) }}" class="btn btn-primary mr-2">
                <i class="fa fa-edit"></i> Edit &amp; expected prices
              </a>
            @else
              <a href="{{ route('hotel.bar.purchase-lists.respond', $purchaseList) }}" class="btn btn-light border mr-2">View budget</a>
            @endif
            <a href="{{ route('hotel.bar.index', ['tab' => 'received', 'department' => $purchaseList->hotel_department_id]) }}" class="btn btn-secondary">
              <i class="fa fa-arrow-left"></i> Back
            </a>
          </div>
        </div>
        <div class="tile-body">
          <div class="row mb-4">
            <div class="col-md-3"><strong>Department</strong><br>{{ $purchaseList->department?->name ?? '—' }}</div>
            <div class="col-md-3"><strong>Sent</strong><br>{{ $purchaseList->sent_at?->format('d M Y H:i') ?? '—' }}</div>
            <div class="col-md-3"><strong>From</strong><br>{{ $purchaseList->creator?->name ?? '—' }}</div>
            <div class="col-md-3"><strong>Status</strong><br>{{ $purchaseList->recipientStatusLabel() }}</div>
          </div>

          @if($purchaseList->isAccountingRejected())
            <div class="alert alert-danger py-2">
              <strong>Rejected by accountant.</strong>
              @if($purchaseList->accounting_notes)
                {{ $purchaseList->accounting_notes }}
              @endif
            </div>
          @endif

          @if($purchaseList->notes)
            <p><strong>Request notes:</strong> {{ $purchaseList->notes }}</p>
          @endif

          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Item</th>
                  <th class="text-right">Qty</th>
                  <th>Unit</th>
                  <th class="text-right">Expected price</th>
                  <th class="text-right">Line total</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                @foreach($purchaseList->lines as $index => $line)
                  <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $line->item_name }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $line->unit ?: '—' }}</td>
                    <td class="text-right">
                      @if($line->unit_cost !== null)
                        {{ number_format((float) $line->unit_cost, 2) }}
                      @else
                        <span class="text-muted">Not set</span>
                      @endif
                    </td>
                    <td class="text-right">
                      @if($line->line_budget !== null)
                        {{ number_format((float) $line->line_budget, 2) }}
                      @else
                        —
                      @endif
                    </td>
                    <td>{{ $line->line_notes ?: '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
              @if($purchaseList->budget_total !== null)
                <tfoot>
                  <tr>
                    <th colspan="5" class="text-right">Budget total ({{ $purchaseList->resolvedBudgetCurrency() }})</th>
                    <th class="text-right">{{ number_format((float) $purchaseList->budget_total, 2) }}</th>
                    <th></th>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>

          @if($purchaseList->recipient_notes)
            <p class="mt-3 mb-0"><strong>Recipient notes:</strong> {{ $purchaseList->recipient_notes }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
