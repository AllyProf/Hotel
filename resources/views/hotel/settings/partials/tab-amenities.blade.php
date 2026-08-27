<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="amenities">

  <h4 class="settings-section-title">Built-in amenities</h4>
  <div class="amenity-grid mb-4">
    @foreach(config('hotel_amenities', []) as $key => $amenity)
      <span class="badge badge-light border p-2 mb-1">
        <i class="{{ $amenity['icon'] }}"></i> {{ $amenity['label'] }}
      </span>
    @endforeach
  </div>

  <h4 class="settings-section-title">Your custom amenities</h4>

  @php $customAmenities = $settings->custom_amenities ?? []; @endphp

  @if(count($customAmenities))
    <div class="table-responsive mb-4">
      <table class="table table-bordered settings-table">
        <thead>
          <tr>
            <th>Label</th>
            <th>Icon class</th>
            <th>Remove</th>
          </tr>
        </thead>
        <tbody>
          @foreach($customAmenities as $index => $item)
            <tr>
              <td>
                <input type="hidden" name="custom_amenities[{{ $index }}][key]" value="{{ $item['key'] }}">
                <input type="hidden" name="custom_amenities[{{ $index }}][label]" value="{{ $item['label'] }}">
                <input type="hidden" name="custom_amenities[{{ $index }}][icon]" value="{{ $item['icon'] ?? 'fa fa-star' }}">
                <i class="{{ $item['icon'] ?? 'fa fa-star' }}"></i> {{ $item['label'] }}
              </td>
              <td><code>{{ $item['icon'] ?? 'fa fa-star' }}</code></td>
              <td>
                <label class="animated-checkbox mb-0">
                  <input type="checkbox" name="custom_amenities[{{ $index }}][_delete]" value="1">
                  <span class="label-text">Delete</span>
                </label>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="text-muted small mb-4">No custom amenities yet.</p>
  @endif

  <h4 class="settings-section-title">Add custom amenity</h4>
  <div class="row">
    <div class="col-md-5">
      <div class="form-group">
        <label class="control-label">Amenity name</label>
        <input class="form-control" type="text" name="new_amenity_label" value="{{ old('new_amenity_label') }}" placeholder="e.g. Rooftop terrace">
      </div>
    </div>
    <div class="col-md-5">
      <div class="form-group">
        <label class="control-label">Icon class <span class="text-muted">(optional)</span></label>
        <input class="form-control" type="text" name="new_amenity_icon" value="{{ old('new_amenity_icon') }}" placeholder="fa fa-star">
        <small class="form-text text-muted">Font Awesome class, e.g. <code>fa fa-leaf</code></small>
      </div>
    </div>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Amenities</button>
  </div>
</form>

@push('styles')
  <style>
    .amenity-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
  </style>
@endpush
