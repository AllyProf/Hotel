@push('styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@php
  $hkConsumptionPermissions = $deptConsumptionPermissions ?? ['view' => false, 'create' => false];
@endphp

@if(! ($deptConsumptionPermissions['view'] ?? false))
  <p class="text-muted mb-0">You do not have permission to view consumption.</p>
@else
  @include('hotel.erp.stores.partials.consumption', [
    'storeConsumptionPermissions' => $deptConsumptionPermissions ?? ['create' => false],
  ])
@endif
