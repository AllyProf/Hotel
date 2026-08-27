@php
  $existingPhotos = $isEdit ? $room->photos : collect();
  $maxPhotos = 8;
@endphp

<h5 class="settings-section-title">Photos</h5>

@if($existingPhotos->isNotEmpty())
  <div class="row mb-3">
    @foreach($existingPhotos as $photo)
      <div class="col-6 col-md-3 mb-3">
        <div class="room-photo-card border rounded p-2">
          <img src="{{ $photo->url() }}" alt="Room photo" class="img-fluid rounded mb-2">
          <label class="animated-checkbox mb-0 small">
            <input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}">
            <span class="label-text">Remove</span>
          </label>
        </div>
      </div>
    @endforeach
  </div>
@endif

<div class="form-group mb-4">
  <label class="control-label">Upload <span class="text-muted">(max {{ $maxPhotos }}, JPG/PNG/WebP)</span></label>
  <input class="form-control-file @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
  @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  @error('photos.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

@push('styles')
  <style>
    .room-photo-card img { max-height: 120px; width: 100%; object-fit: cover; }
  </style>
@endpush
