@php $laundry = $settings->laundry ?? []; $items = $laundry['items'] ?? []; $stock = $laundry['stock'] ?? []; @endphp
<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="laundry">

  <h4 class="settings-section-title">Laundry Items</h4>
  <div class="table-responsive">
    <table class="table table-bordered settings-table">
      <thead><tr><th>#</th><th>Item</th></tr></thead>
      <tbody>
        @foreach($items as $index => $item)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td><input class="form-control form-control-sm" type="text" name="laundry_items[{{ $index }}][name]" value="{{ $item['name'] ?? '' }}"></td>
          </tr>
        @endforeach
        <tr>
          <td>+</td>
          <td><input class="form-control form-control-sm" type="text" name="laundry_items[{{ count($items) }}][name]" placeholder="Add new item"></td>
        </tr>
      </tbody>
    </table>
  </div>

  <h4 class="settings-section-title">Items Stock</h4>
  <div class="row">
    <div class="col-md-4"><div class="form-group"><label class="control-label">Item Name</label><input class="form-control" name="stock_item_name" value="{{ $stock['item_name'] ?? '' }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Total Items</label><input class="form-control" type="number" name="stock_total_items" value="{{ $stock['total_items'] ?? 1 }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label class="control-label">Current Balance</label><input class="form-control" type="number" name="stock_current_balance" value="{{ $stock['current_balance'] ?? 1 }}"></div></div>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Laundry</button>
  </div>
</form>
