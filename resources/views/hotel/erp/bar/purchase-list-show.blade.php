@extends('layouts.app')

@section('title', 'Purchase list · '.$purchaseList->reference_no)

@section('content')
  @php
    $receiverHasEdited = $purchaseList->received_at
      || $purchaseList->recipient_status !== \App\Models\HotelBarPurchaseList::RECIPIENT_AWAITING;
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-shopping-cart"></i> {{ $purchaseList->reference_no }}</h1>
      <p>{{ $purchaseList->department?->name ?? 'Bar purchase list' }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      @php
        $procW = $procWorkflow ?? $purchaseList->workflowKey();
        $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
      @endphp
      <li class="breadcrumb-item"><a href="{{ \App\Support\DepartmentProcurement::indexUrl($procW, 'purchase-lists') }}">{{ \App\Support\DepartmentProcurement::label($procW) }}</a></li>
      <li class="breadcrumb-item">{{ $purchaseList->reference_no }}</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($purchaseList->isReturnedToDepartment())
    @php
      $procW = $procWorkflow ?? $purchaseList->workflowKey();
      $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
      $returnMessage = $purchaseList->storeReturnNotes();
    @endphp
    <div class="alert alert-warning mb-3">
      <strong>Returned by store.</strong>
      @if($returnMessage)
        {{ $returnMessage }}
      @endif
      <a href="{{ $procRoute('purchase-lists.edit', $purchaseList) }}" class="btn btn-sm btn-primary ml-2">Edit &amp; send again</a>
    </div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="tile-title">List details</h3>
          <div>
            <a href="{{ $procRoute('purchase-lists.print', $purchaseList) }}" class="btn btn-primary mr-2" target="_blank" rel="noopener">
              <i class="fa fa-print"></i> Print
            </a>
            <a href="{{ \App\Support\DepartmentProcurement::indexUrl($procW, 'purchase-lists') }}" class="btn btn-secondary">
              <i class="fa fa-arrow-left"></i> Back
            </a>
          </div>
        </div>
        <div class="tile-body">
          <div class="row mb-4">
            <div class="col-md-3"><strong>Sent</strong><br>{{ $purchaseList->sent_at?->format('d M Y H:i') ?? '—' }}</div>
            <div class="col-md-3"><strong>Prepared by</strong><br>{{ $purchaseList->creator?->name ?? '—' }}</div>
            <div class="col-md-3"><strong>Email</strong><br>
              @if($purchaseList->status === \App\Models\HotelBarPurchaseList::STATUS_SENT)
                <span class="badge badge-success">Sent</span>
              @else
                <span class="badge badge-warning">Not sent</span>
              @endif
            </div>
            <div class="col-md-3"><strong>Progress</strong><br>
              @if($purchaseList->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
                <span class="badge badge-success">{{ $purchaseList->recipientStatusLabel() }}</span>
              @elseif($purchaseList->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_IN_PROGRESS)
                <span class="badge badge-info">{{ $purchaseList->recipientStatusLabel() }}</span>
              @else
                <span class="badge badge-secondary">{{ $purchaseList->recipientStatusLabel() }}</span>
              @endif
              @if($purchaseList->effectiveAccountingStatus())
                ·
                @if($purchaseList->effectiveAccountingStatus() === \App\Models\HotelBarPurchaseList::ACCOUNTING_APPROVED)
                  <span class="badge badge-success">{{ $purchaseList->accountingStatusLabel() }}</span>
                @elseif($purchaseList->effectiveAccountingStatus() === \App\Models\HotelBarPurchaseList::ACCOUNTING_REJECTED)
                  <span class="badge badge-danger">{{ $purchaseList->accountingStatusLabel() }}</span>
                @else
                  <span class="badge badge-warning">{{ $purchaseList->accountingStatusLabel() }}</span>
                @endif
              @endif
              @if($purchaseList->market_status === \App\Models\HotelBarPurchaseList::MARKET_RELEASED)
                · <span class="badge badge-primary">{{ $purchaseList->marketStatusLabel() }}</span>
              @elseif($purchaseList->market_status === \App\Models\HotelBarPurchaseList::MARKET_COMPLETED)
                · <span class="badge badge-success">{{ $purchaseList->marketStatusLabel() }}</span>
              @endif
            </div>
          </div>

          @if($purchaseList->purchaseOrder)
            <p class="mb-3">
              <strong>Payables PO:</strong>
              <a href="{{ route('hotel.accounts.index', ['tab' => 'payables', 'search' => $purchaseList->purchaseOrder->po_number]) }}">
                {{ $purchaseList->purchaseOrder->po_number }}
              </a>
              · {{ $purchaseList->purchaseOrder->vendor?->name ?? 'Vendor' }}
              · {{ number_format((float) $purchaseList->purchaseOrder->total, 2) }} {{ $purchaseList->resolvedBudgetCurrency() }}
              · {{ $purchaseList->purchaseOrder->statusLabel() }}
            </p>
          @endif

          @if($purchaseList->notes)
            <p><strong>Bar notes:</strong> {{ $purchaseList->notes }}</p>
          @endif

          @if($purchaseList->market_status === \App\Models\HotelBarPurchaseList::MARKET_COMPLETED)
            @php
              $paymentLabels = \App\Models\HotelExpense::paymentTypeLabels();
            @endphp
            <p class="mb-3">
              <strong>Market purchase:</strong>
              {{ $paymentLabels[$purchaseList->market_payment_type] ?? $purchaseList->market_payment_type ?? '—' }}
              @if($purchaseList->market_completed_at)
                · {{ $purchaseList->market_completed_at->format('d M Y H:i') }}
              @endif
              @if($purchaseList->market_completion_notes)
                · {{ $purchaseList->market_completion_notes }}
              @endif
              @if($purchaseList->hasMarketBudgetReturn())
                · <strong>Return:</strong> {{ number_format((float) $purchaseList->market_budget_return_amount, 2) }} {{ $purchaseList->resolvedBudgetCurrency() }}
                (posted to Expenses as paid in)
              @endif
            </p>
          @endif

          @if($receiverHasEdited)
            <h4 class="mt-2 mb-2" style="font-size:16px;">Procurement list</h4>
            <p class="text-muted small mb-3">
              @if($purchaseList->budget_prepared_by_name)
                Last updated by {{ $purchaseList->budget_prepared_by_name }}
                @if($purchaseList->budget_prepared_by_email) ({{ $purchaseList->budget_prepared_by_email }}) @endif
                @if($purchaseList->budget_prepared_at)
                  · {{ $purchaseList->budget_prepared_at->format('d M Y H:i') }}
                @elseif($purchaseList->received_at)
                  · opened {{ $purchaseList->received_at->format('d M Y H:i') }}
                @endif
              @elseif($purchaseList->received_at)
                Opened by recipient {{ $purchaseList->received_at->format('d M Y H:i') }}
              @endif
              @if($purchaseList->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_IN_PROGRESS)
                <span class="badge badge-info ml-1">Draft — not submitted yet</span>
              @endif
            </p>
            @if($purchaseList->recipient_notes)
              <p><strong>Recipient notes:</strong> {{ $purchaseList->recipient_notes }}</p>
            @endif
            @if($purchaseList->accounting_notes)
              <p><strong>Accountant notes:</strong> {{ $purchaseList->accounting_notes }}</p>
            @endif
            @include('hotel.erp.bar.partials._purchase-list-budget-table', [
              'purchaseList' => $purchaseList,
              'currency' => $purchaseList->resolvedBudgetCurrency(),
            ])
          @else
            <h4 class="mb-3" style="font-size:16px;">Items requested</h4>
            @if($purchaseList->recipient_notes)
              <p><strong>Recipient notes:</strong> {{ $purchaseList->recipient_notes }}</p>
            @endif
            <div class="table-responsive">
              <table class="table table-bordered table-hover mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th>Unit</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($purchaseList->lines as $index => $line)
                    <tr>
                      <td>{{ $index + 1 }}</td>
                      <td>{{ $line->item_name }}</td>
                      <td class="text-right">{{ (int) max(1, round((float) $line->quantity)) }}</td>
                      <td>{{ $line->unit ?: '—' }}</td>
                      <td>{{ $line->line_notes ?: '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
