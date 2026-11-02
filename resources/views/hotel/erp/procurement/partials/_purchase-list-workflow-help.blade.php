@php
  $procW = $procWorkflow ?? 'bar';
  $procLabel = $procLabel ?? \App\Support\DepartmentProcurement::label($procW);
  $receivedUrl = \App\Support\DepartmentProcurement::indexUrl($procW, 'received');
@endphp
<div class="alert alert-light border mb-3 py-2 px-3" style="font-size: 13px;">
  <strong>How purchase lists work</strong>
  <ul class="mb-0 pl-3 mt-1">
    <li>Any team can open <strong>New list</strong>, add items, and <strong>Send</strong>. The department then waits — they cannot verify their own list.</li>
    @if($procW === 'bar')
      <li>The <strong>store keeper</strong> opens <a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}">ERP → Stores → Purchase orders</a>, edits buying prices, and submits to Accounts (same handoff as HK).</li>
    @else
      <li>Store / procurement recipients get an email and can open <a href="{{ $receivedUrl }}">{{ $procLabel }} → Received</a> or <strong>Stores → Purchase orders</strong> to review and prepare the budget.</li>
    @endif
    <li>After Accounts approval, market purchase and stock receive continue as today.</li>
    <li>The store team can also raise buying directly under <strong>ERP → Stores → Purchase Orders</strong> without a department list.</li>
  </ul>
</div>
