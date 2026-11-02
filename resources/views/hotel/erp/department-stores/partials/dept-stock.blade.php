@push('styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@php
  $canView = $deptStockPermissions['view'] ?? false;
  $hkDeptStockPermissions = $deptStockPermissions ?? ['view' => false];
@endphp

@if(! $canView)
  <p class="text-muted mb-0">You do not have permission to view department stock.</p>
@else
  @include('hotel.erp.maintenance-hk.partials.dept-stock')
@endif
