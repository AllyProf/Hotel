@php
  $storeCategories = $storeCategories ?? collect();
@endphp
<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="store-categories">

  <h4 class="settings-section-title">Store item categories</h4>
  <p class="text-muted">Category names used in <strong>ERP → Stores → Settings → Item Category</strong> and Master Items. Add or rename here or on the Stores screen.</p>

  <div class="table-responsive">
    <table class="table table-bordered settings-table">
      <thead>
        <tr>
          <th style="width:56px">Sr.</th>
          <th>Category name</th>
        </tr>
      </thead>
      <tbody>
        @foreach($storeCategories as $index => $cat)
          <tr>
            <td>{{ $index + 1 }}<input type="hidden" name="categories[{{ $index }}][id]" value="{{ $cat->id }}"></td>
            <td><input class="form-control form-control-sm" name="categories[{{ $index }}][name]" value="{{ $cat->name }}" maxlength="120" required></td>
          </tr>
        @endforeach
        <tr>
          <td>{{ $storeCategories->count() + 1 }}</td>
          <td><input class="form-control form-control-sm" name="categories[{{ $storeCategories->count() }}][name]" value="" maxlength="120" placeholder="New category name"></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save categories</button>
  </div>
</form>
