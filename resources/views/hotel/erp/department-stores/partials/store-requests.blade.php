@push('styles')
  @include('hotel.erp.stores.partials._styles')
@endpush

@include('hotel.erp.stores.partials.indents', [
  'storeIndentPermissions' => array_merge(
    ['approve' => false],
    $deptStoreRequestPermissions ?? ['create' => false, 'edit' => false, 'delete' => false, 'receive' => false],
  ),
])
