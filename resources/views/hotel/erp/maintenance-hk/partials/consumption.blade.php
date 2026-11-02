@push('styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@if(! ($hkConsumptionPermissions['view'] ?? false))
  <p class="text-muted mb-0">You do not have permission to view consumption.</p>
@else
  @include('hotel.erp.stores.partials.consumption', [
    'storeConsumptionPermissions' => $hkConsumptionPermissions ?? ['create' => false],
  ])
@endif
