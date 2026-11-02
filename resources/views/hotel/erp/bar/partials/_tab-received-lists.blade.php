@php
  $procW = $procWorkflow ?? 'bar';
  $procSettingsTab = $procSettingsTab ?? \App\Support\DepartmentProcurement::workflow($procW)['settings_tab'];
  $procIndexRoute = \App\Support\DepartmentProcurement::workflow($procW)['index_route'];
  $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
@endphp
@if(! $isPurchaseListRecipient)
  <div class="alert alert-info mb-3 py-2">
    This tab is for stores / procurement recipients. Add your email or staff user under
    <a href="{{ route('hotel.settings.index', ['tab' => $procSettingsTab]) }}">Settings</a>, or sign in as <strong>hotel admin</strong>.
  </div>
@endif

@if($receivedDepartments->isNotEmpty())
  <ul class="nav nav-pills pms-brand-tabs mb-3 flex-wrap">
    @if($receivedDepartments->count() > 1)
      <li class="nav-item">
        <a class="nav-link {{ empty($receivedDepartmentId) ? 'active' : '' }}"
          href="{{ route($procIndexRoute, ['tab' => 'received']) }}">All</a>
      </li>
    @endif
    @foreach($receivedDepartments as $department)
      @php
        $isActive = $receivedDepartments->count() === 1
          || (int) ($receivedDepartmentId ?? 0) === (int) $department->id;
      @endphp
      <li class="nav-item">
        <a class="nav-link {{ $isActive ? 'active' : '' }}"
          href="{{ route($procIndexRoute, ['tab' => 'received', 'department' => $department->id]) }}">
          {{ $department->name }}
        </a>
      </li>
    @endforeach
  </ul>
@endif

<div class="table-responsive">
  <table class="table table-hover table-bordered mb-0">
    <thead>
      <tr>
        <th>Reference</th>
        <th>Department</th>
        <th>Sent</th>
        <th>From</th>
        <th>Items</th>
        <th>Status</th>
        <th>Budget</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($receivedLists as $list)
        <tr>
          <td><strong>{{ $list->reference_no }}</strong></td>
          <td>{{ $list->department?->name ?? '—' }}</td>
          <td>{{ $list->sent_at?->format('d M Y H:i') ?? '—' }}</td>
          <td>{{ $list->creator?->name ?? '—' }}</td>
          <td>{{ $list->lines->count() }}</td>
          <td>
            @if($list->isAccountingRejected())
              <span class="badge badge-danger">{{ $list->accountingStatusLabel() }}</span>
            @elseif($list->isReturnedToDepartment())
              <span class="badge badge-danger">{{ $list->recipientStatusLabel() }}</span>
            @elseif($list->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
              <span class="badge badge-success">{{ $list->recipientStatusLabel() }}</span>
            @elseif($list->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_IN_PROGRESS)
              <span class="badge badge-info">{{ $list->recipientStatusLabel() }}</span>
            @else
              <span class="badge badge-warning">{{ $list->recipientStatusLabel() }}</span>
            @endif
          </td>
          <td>
            @php
              $budgetPrepared = $list->budget_total !== null;
              $estimate = $budgetPrepared ? (float) $list->budget_total : app(\App\Services\Bar\BarPurchaseListService::class)->estimatedLineBudgetTotal($list);
            @endphp
            @if($budgetPrepared)
              {{ number_format((float) $list->budget_total, 2) }} {{ $list->resolvedBudgetCurrency() }}
            @elseif($estimate > 0)
              <span title="From store master buying prices">{{ number_format($estimate, 2) }} {{ $list->resolvedBudgetCurrency() }}</span>
              <span class="text-muted small d-block">Reference</span>
            @else
              —
            @endif
          </td>
          <td class="text-nowrap">
            @php
              $canVerify = app(\App\Services\Bar\BarPurchaseListService::class)->userCanVerifyPurchaseList($hotel ?? auth()->user()->hotel, auth()->user(), $list);
            @endphp
            <a href="{{ $procRoute('purchase-lists.received.show', $list) }}" class="btn btn-sm btn-light border">View</a>
            @if($canVerify)
              <a href="{{ $procRoute('purchase-lists.respond', $list) }}" class="btn btn-sm btn-primary">
                @if($list->isAccountingRejected())
                  Revise
                @elseif($list->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
                  Budget
                @else
                  Edit
                @endif
              </a>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            @if($isPurchaseListRecipient)
              No purchase lists sent yet.
            @else
              No lists to show for your account.
            @endif
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
