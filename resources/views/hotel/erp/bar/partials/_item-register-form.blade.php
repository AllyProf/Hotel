@php
  $defaultRow = [
    'item_name' => '',
    'category_id' => '',
    'brand' => '',
    'package_id' => $defaultPackageId ?? '',
    'pieces_per_receive_unit' => '1',
    'selling_options' => [['package_id' => '', 'pieces_per_sell_unit' => '1']],
  ];
  $itemRows = old('items');
  if (! is_array($itemRows) || $itemRows === []) {
    $itemRows = [$defaultRow];
  }
@endphp

<div class="bar-item-register-batch">
  <div class="js-bar-register-items">
    @foreach($itemRows as $itemIndex => $itemRow)
      @include('hotel.erp.bar.partials._item-register-row', [
        'itemIndex' => $itemIndex,
        'itemRow' => is_array($itemRow) ? $itemRow : $defaultRow,
        'canRemove' => count($itemRows) > 1,
      ])
    @endforeach
  </div>

  <div class="mt-3">
    <button type="button" class="btn btn-sm btn-outline-secondary js-bar-add-register-item">
      <i class="fa fa-plus"></i> Add another item
    </button>
  </div>

  @error('items')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
</div>

<template id="barItemRegisterRowTemplate">
  @include('hotel.erp.bar.partials._item-register-row', [
    'itemIndex' => '__INDEX__',
    'itemRow' => $defaultRow,
    'canRemove' => true,
  ])
</template>
