<div class="modal fade" id="mhLostFoundModal" tabindex="-1" role="dialog" aria-labelledby="mhLostFoundModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="POST" action="{{ route('hotel.lost-found.store') }}" class="modal-content js-swal-confirm" enctype="multipart/form-data" id="lfLogForm">
      @csrf
      @if(!empty($lostFoundReturnTo))
        <input type="hidden" name="return_to" value="{{ $lostFoundReturnTo }}">
      @endif
      <div class="modal-header">
        <h5 class="modal-title" id="mhLostFoundModalLabel">Log found item</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Record an item found on the property. Guest lost-property reports are handled at the front desk.</p>
        <div class="form-group">
          <label for="lf_category">Category</label>
          <select class="form-control" id="lf_category" name="category">
            @foreach($ui['lost_found']['categories'] ?? [] as $value => $label)
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label for="lf_item_name">Item description <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="lf_item_name" name="item_name" required maxlength="255" placeholder="e.g. Black wallet, iPhone 13">
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="lf_unit">Room (optional)</label>
              <select class="form-control" id="lf_unit" name="hotel_room_unit_id">
                <option value="">— Not linked to a room —</option>
                @foreach($roomUnits as $unit)
                  <option value="{{ $unit['value'] }}">{{ $unit['label'] }} · {{ $unit['room_type'] }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="lf_location">Found at</label>
              <input type="text" class="form-control" id="lf_location" name="location" maxlength="128" placeholder="Lobby, restaurant, pool…">
            </div>
          </div>
        </div>
        <div class="form-group">
          <label for="lf_storage">Storage location</label>
          <input type="text" class="form-control" id="lf_storage" name="storage_location" maxlength="128" placeholder="Front desk safe, HK office…">
        </div>

        <div class="form-group">
          <label>Item photo</label>
          <div class="d-flex flex-wrap align-items-start" style="gap:10px;">
            <div>
              <input type="file" class="d-none js-lf-photo-upload" id="lf_photo_upload" name="photo" accept="image/*">
              <button type="button" class="btn btn-outline-secondary btn-sm js-lf-pick-upload">
                <i class="fa fa-upload"></i> Upload photo
              </button>
            </div>
            <div>
              <input type="file" class="d-none js-lf-photo-camera" id="lf_photo_camera" accept="image/*" capture="environment">
              <button type="button" class="btn btn-outline-secondary btn-sm js-lf-pick-camera">
                <i class="fa fa-camera"></i> Take picture
              </button>
            </div>
            <button type="button" class="btn btn-link btn-sm text-danger d-none js-lf-photo-clear">Remove</button>
          </div>
          <div class="mt-2 d-none js-lf-photo-preview-wrap">
            <img src="" alt="Item preview" class="js-lf-photo-preview img-thumbnail" style="max-height:160px;max-width:100%;">
          </div>
          <small class="form-text text-muted">Optional. JPG or PNG, max 5 MB.</small>
        </div>

        <div class="form-group mb-0">
          <label for="lf_description">Notes</label>
          <textarea class="form-control" id="lf_description" name="description" rows="3" maxlength="2000"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"
          data-title="Log found item?"
          data-text="Save this item to the Lost &amp; Found register?"
          data-confirm="Yes, save">Save entry</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('lfLogForm');
      if (!form) return;

      var uploadInput = form.querySelector('.js-lf-photo-upload');
      var cameraInput = form.querySelector('.js-lf-photo-camera');
      var previewWrap = form.querySelector('.js-lf-photo-preview-wrap');
      var previewImg = form.querySelector('.js-lf-photo-preview');
      var clearBtn = form.querySelector('.js-lf-photo-clear');

      function showPreview(file) {
        if (!file || !file.type.match(/^image\//)) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          previewImg.src = e.target.result;
          previewWrap.classList.remove('d-none');
          clearBtn.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
      }

      function assignToSubmitInput(file) {
        if (!file || typeof DataTransfer === 'undefined') return;
        var dt = new DataTransfer();
        dt.items.add(file);
        uploadInput.files = dt.files;
      }

      function clearPhoto() {
        uploadInput.value = '';
        cameraInput.value = '';
        previewImg.src = '';
        previewWrap.classList.add('d-none');
        clearBtn.classList.add('d-none');
      }

      form.querySelector('.js-lf-pick-upload').addEventListener('click', function () {
        uploadInput.click();
      });

      form.querySelector('.js-lf-pick-camera').addEventListener('click', function () {
        cameraInput.click();
      });

      uploadInput.addEventListener('change', function () {
        var file = uploadInput.files && uploadInput.files[0];
        if (file) {
          assignToSubmitInput(file);
          showPreview(file);
        }
      });

      cameraInput.addEventListener('change', function () {
        var file = cameraInput.files && cameraInput.files[0];
        if (file) {
          assignToSubmitInput(file);
          showPreview(file);
        }
      });

      clearBtn.addEventListener('click', clearPhoto);

      jQuery('#mhLostFoundModal').on('hidden.bs.modal', clearPhoto);
    })();
  </script>
@endpush
