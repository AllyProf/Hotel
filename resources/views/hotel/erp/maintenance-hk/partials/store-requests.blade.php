@push('styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@include('hotel.erp.stores.partials.indents', [
  'storeIndentPermissions' => array_merge(
    ['approve' => false],
    $hkStoreRequestPermissions ?? ['create' => false, 'edit' => false, 'delete' => false, 'receive' => false],
  ),
])
