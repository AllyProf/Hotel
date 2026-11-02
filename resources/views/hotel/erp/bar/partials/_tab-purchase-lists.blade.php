@php
  $procW = $procWorkflow ?? 'bar';
  $procSettingsTab = $procSettingsTab ?? \App\Support\DepartmentProcurement::workflow($procW)['settings_tab'];
  $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
@endphp
@if(! $purchaseListRecipientsConfigured)
  <div class="alert alert-warning mb-3 py-2">
    Configure recipients in <a href="{{ route('hotel.settings.index', ['tab' => $procSettingsTab]) }}">Settings</a> before sending lists.
  </div>
@endif

@if($procW === 'bar')
  <div class="alert alert-light border mb-3 py-2 small">
    After you <strong>Send</strong>, the <strong>store keeper</strong> reviews the list under
    <a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}">ERP → Stores → Purchase orders</a>.
    Bar cannot verify its own lists here (same handoff as HK).
  </div>
@endif

<div class="table-responsive">
  <table class="table table-hover table-bordered mb-0">
    <thead>
      <tr>
        <th>Reference</th>
        <th>Department</th>
        <th>Sent</th>
        <th>Qty</th>
        <th>Prepared by</th>
        <th>Store manager</th>
        <th>Accountant</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($purchaseLists as $list)
        @php
          $recipient = $list->recipient_status ?: \App\Models\HotelBarPurchaseList::RECIPIENT_AWAITING;
          $atAccountant = $recipient === \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED;
          $accounting = $list->effectiveAccountingStatus();
        @endphp
        <tr>
          <td><strong>{{ $list->reference_no }}</strong></td>
          <td>{{ $list->department?->name ?? '—' }}</td>
          <td>
            {{ $list->sent_at?->format('d M Y H:i') ?? '—' }}
            @if($list->status === \App\Models\HotelBarPurchaseList::STATUS_SENT)
              <span class="badge badge-success ml-1">Emailed</span>
            @elseif($list->status === \App\Models\HotelBarPurchaseList::STATUS_FAILED)
              <span class="badge badge-danger ml-1">Email failed</span>
            @endif
          </td>
          <td>
            {{ $list->totalRequestedQuantityLabel() }}
            @if($list->lines->count() > 1)
              <span class="text-muted small d-block">{{ $list->lines->count() }} items</span>
            @endif
          </td>
          <td>{{ $list->creator?->name ?? '—' }}</td>
          <td>
            @if($list->isReturnedToDepartment())
              <span class="badge badge-danger">{{ $list->recipientStatusLabel() }}</span>
            @elseif($recipient === \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
              <span class="badge badge-success">Budget sent</span>
            @elseif($recipient === \App\Models\HotelBarPurchaseList::RECIPIENT_IN_PROGRESS)
              <span class="badge badge-info">{{ $list->recipientStatusLabel() }}</span>
            @else
              <span class="badge badge-warning">{{ $list->recipientStatusLabel() }}</span>
            @endif
          </td>
          <td>
            @if(! $atAccountant)
              <span class="text-muted small">After store</span>
            @elseif($accounting === \App\Models\HotelBarPurchaseList::ACCOUNTING_APPROVED)
              <span class="badge badge-success">{{ $list->accountingStatusLabel() }}</span>
            @elseif($accounting === \App\Models\HotelBarPurchaseList::ACCOUNTING_REJECTED)
              <span class="badge badge-danger">{{ $list->accountingStatusLabel() }}</span>
            @else
              <span class="badge badge-warning">{{ $list->accountingStatusLabel() }}</span>
            @endif
          </td>
          <td class="text-nowrap">
            @if($list->isReturnedToDepartment())
              <a href="{{ $procRoute('purchase-lists.edit', $list) }}" class="btn btn-sm btn-primary">Edit &amp; send</a>
            @else
              <a href="{{ $procRoute('purchase-lists.show', $list) }}" class="btn btn-sm btn-light border">View</a>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            No lists sent yet.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
