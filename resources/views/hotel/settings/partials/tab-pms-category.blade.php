<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="pms-category">

  <h4 class="settings-section-title">Category</h4>
  <div class="table-responsive">
    <table class="table table-bordered settings-table">
      <thead><tr><th>Sr.</th><th>Category Name</th><th>Services</th><th>Comments</th></tr></thead>
      <tbody>
        @foreach($hotel->pmsCategories as $index => $cat)
          <tr>
            <td>{{ $index + 1 }}<input type="hidden" name="categories[{{ $index }}][id]" value="{{ $cat->id }}"></td>
            <td><input class="form-control form-control-sm" name="categories[{{ $index }}][name]" value="{{ $cat->name }}"></td>
            <td><input class="form-control form-control-sm" name="categories[{{ $index }}][services]" value="{{ implode(', ', $cat->service_names ?? []) }}"></td>
            <td><input class="form-control form-control-sm" name="categories[{{ $index }}][comments]" value="{{ $cat->comments }}"></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Categories</button>
  </div>
</form>
