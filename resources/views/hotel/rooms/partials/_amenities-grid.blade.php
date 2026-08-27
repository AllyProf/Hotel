@php
  $selectedAmenities = old('amenities', $selectedAmenities ?? []);
@endphp

<h5 class="settings-section-title">Amenities</h5>
<p class="text-muted small mb-2">
  Up to 9. <a href="{{ route('hotel.settings.index', ['tab' => 'amenities']) }}">Add custom</a>
</p>

@error('amenities')
  <div class="alert alert-danger py-2 small">{{ $message }}</div>
@enderror

<div class="amenity-grid mb-4" id="roomAmenityGrid">
  @foreach($amenities as $key => $amenity)
    <label class="amenity-grid__item">
      <input type="checkbox" name="amenities[]" value="{{ $key }}" class="room-amenity-check" {{ in_array($key, $selectedAmenities, true) ? 'checked' : '' }}>
      <i class="{{ $amenity['icon'] }}"></i> {{ $amenity['label'] }}
      @if(!empty($amenity['custom']))
        <span class="badge badge-secondary badge-sm ml-1">Custom</span>
      @endif
    </label>
  @endforeach
</div>

@push('styles')
  <style>
    .amenity-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 8px;
    }
    .amenity-grid__item {
      font-size: 13px;
      font-weight: 400;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
    }
    .amenity-grid__item.is-disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  </style>
@endpush

@once
  @push('scripts')
    <script>
      jQuery(function ($) {
        var maxAmenities = 9;

        function refreshAmenityLimit() {
          var $checks = $('.room-amenity-check');
          var checked = $checks.filter(':checked').length;
          var atLimit = checked >= maxAmenities;

          $checks.not(':checked').each(function () {
            $(this).prop('disabled', atLimit);
            $(this).closest('.amenity-grid__item').toggleClass('is-disabled', atLimit);
          });
        }

        $(document).on('change', '.room-amenity-check', refreshAmenityLimit);
        refreshAmenityLimit();
      });
    </script>
  @endpush
@endonce
