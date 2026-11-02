@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $barStoreIndentsList = $barStoreIndentsList ?? null;
  $receiveKey = config('hotel_department_stores.store_request_permission_keys.receive', 'dept_stores.store_request.receive');
  $canReceive = auth()->user()?->hasPermission($receiveKey) ?? false;
@endphp

<p class="text-muted small px-1 mb-3">
  After the main store <strong>approves</strong> an indent for Bar, receive it here. Quantities are added to <strong>Bar → Stock</strong> (register items with the same name as store master items).
</p>

<div class="table-responsive">
  <table class="table table-bordered table-hover mb-0">
    <thead>
      <tr>
        <th>Indent #</th>
        <th>Date</th>
        <th>Status</th>
        <th>Department</th>
        <th>Items</th>
        <th class="text-nowrap">Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse($barStoreIndentsList ?? [] as $indent)
        <tr>
          <td>{{ $indent->indent_number }}</td>
          <td class="text-nowrap">{{ $indent->dateTimeLabel() }}</td>
          <td>{{ $indent->statusLabel() }}</td>
          <td>{{ $storeItemService->requesterLabelForIndent($hotel, $indent) }}</td>
          <td>{{ $indent->items->count() }}</td>
          <td class="text-nowrap">
            @if($indent->isIssued() && $canReceive)
              <form method="POST" action="{{ route('hotel.bar.store-indents.receive', $indent) }}" class="d-inline js-swal-confirm">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary"
                  data-title="Receive into bar stock?"
                  data-text="Add items from {{ $indent->indent_number }} to Bar register stock?"
                  data-confirm="Receive">Receive</button>
              </form>
            @elseif($indent->isIssued())
              <span class="text-muted small">Awaiting receive</span>
            @elseif($indent->isApplied())
              <span class="text-muted small">Awaiting store approval</span>
            @elseif($indent->isReceived())
              <span class="badge badge-success">Received</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center text-muted py-4">
            No store indents for Bar. Create one under <strong>ERP → Stores → Indent</strong> (requester department: Bar), then approve it from the store desk.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($barStoreIndentsList && method_exists($barStoreIndentsList, 'hasPages') && $barStoreIndentsList->hasPages())
  <div class="pt-3">{{ $barStoreIndentsList->links() }}</div>
@endif
