@php
  $laundry = $service->laundrySettings();
  $items = old('laundry_items', $laundry['items'] ?? []);
  if (! is_array($items)) {
      $items = [];
  }
@endphp

<div class="sv-laundry-rules border-top pt-3 mt-1">
  <h5 class="sv-section-title mb-3" style="font-size:14px;"><i class="fa fa-shirtsinbulk"></i> Laundry Rules</h5>

  <div class="row align-items-end">
    <div class="col-md-3">
      <div class="form-group mb-3">
        <label class="control-label">Turnaround</label>
        <div class="input-group">
          <input type="number" class="form-control" name="turnaround_hours" min="1" max="168" step="1"
            value="{{ old('turnaround_hours', $laundry['turnaround_hours'] ?? 24) }}">
          <span class="input-group-text">hrs</span>
        </div>
        @error('turnaround_hours')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group mb-3">
        <label class="control-label">Express surcharge</label>
        <div class="input-group">
          <input type="number" class="form-control" name="express_surcharge_percent" min="0" max="500" step="1"
            value="{{ old('express_surcharge_percent', $laundry['express_surcharge_percent'] ?? 0) }}">
          <span class="input-group-text">%</span>
        </div>
        @error('express_surcharge_percent')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group mb-3">
        <label class="control-label">Free pieces (in-house)</label>
        <input type="number" class="form-control" name="complimentary_pieces" min="0" max="99" step="1"
          value="{{ old('complimentary_pieces', $laundry['complimentary_pieces'] ?? 0) }}">
        @error('complimentary_pieces')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>
    </div>
    <div class="col-md-3">
      <div class="sv-settings-toggles mb-3">
        <label class="animated-checkbox">
          <input type="checkbox" name="require_room_number" value="1"
            {{ old('require_room_number', $laundry['require_room_number'] ?? true) ? 'checked' : '' }}
            title="Applies when an in-house guest is selected on the laundry desk">
          <span class="label-text">Require room for in-house guests</span>
        </label>
        <label class="animated-checkbox">
          <input type="checkbox" name="post_to_folio" value="1"
            {{ old('post_to_folio', $laundry['post_to_folio'] ?? true) ? 'checked' : '' }}>
          <span class="label-text">Post to room folio</span>
        </label>
      </div>
    </div>
  </div>

  <div class="sv-laundry-items mt-2">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <label class="control-label mb-0">Garment / item types</label>
      <button type="button" class="btn btn-sm btn-light border js-sv-laundry-add-row">
        <i class="fa fa-plus"></i> Add item
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0">
        <thead>
          <tr>
            <th style="width:48px;">#</th>
            <th>Item name</th>
            <th style="width:56px;"></th>
          </tr>
        </thead>
        <tbody class="js-sv-laundry-items-body">
          @forelse($items as $index => $item)
            <tr class="js-sv-laundry-row">
              <td class="js-sv-laundry-index">{{ $index + 1 }}</td>
              <td>
                <input type="text" class="form-control form-control-sm" name="laundry_items[{{ $index }}][name]"
                  maxlength="120" value="{{ $item['name'] ?? '' }}" placeholder="e.g. Shirt">
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-danger js-sv-laundry-remove-row" title="Remove">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr class="js-sv-laundry-empty">
              <td colspan="3" class="text-muted text-center py-2">No items yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<template id="svLaundryRowTemplate">
  <tr class="js-sv-laundry-row">
    <td class="js-sv-laundry-index"></td>
    <td>
      <input type="text" class="form-control form-control-sm js-sv-laundry-name-input" maxlength="120" placeholder="e.g. Shirt">
    </td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-danger js-sv-laundry-remove-row" title="Remove">
        <i class="fa fa-trash"></i>
      </button>
    </td>
  </tr>
</template>

@push('scripts')
  <script>
    (function () {
      var tbody = document.querySelector('.js-sv-laundry-items-body');
      var template = document.getElementById('svLaundryRowTemplate');
      var addBtn = document.querySelector('.js-sv-laundry-add-row');
      if (!tbody || !template || !addBtn) return;

      function reindexRows() {
        var rows = tbody.querySelectorAll('.js-sv-laundry-row');
        rows.forEach(function (row, index) {
          var indexCell = row.querySelector('.js-sv-laundry-index');
          var nameInput = row.querySelector('.js-sv-laundry-name-input') || row.querySelector('[name^="laundry_items"]');
          if (indexCell) indexCell.textContent = String(index + 1);
          if (nameInput) nameInput.setAttribute('name', 'laundry_items[' + index + '][name]');
        });

        var emptyRow = tbody.querySelector('.js-sv-laundry-empty');
        if (emptyRow) {
          emptyRow.style.display = rows.length ? 'none' : '';
        }
      }

      addBtn.addEventListener('click', function () {
        var emptyRow = tbody.querySelector('.js-sv-laundry-empty');
        if (emptyRow) emptyRow.remove();

        var clone = template.content.cloneNode(true);
        var input = clone.querySelector('.js-sv-laundry-name-input');
        if (input) input.classList.remove('js-sv-laundry-name-input');
        tbody.appendChild(clone);
        reindexRows();
      });

      tbody.addEventListener('click', function (event) {
        var btn = event.target.closest('.js-sv-laundry-remove-row');
        if (!btn) return;
        var row = btn.closest('.js-sv-laundry-row');
        if (row) row.remove();
        if (!tbody.querySelector('.js-sv-laundry-row')) {
          var empty = document.createElement('tr');
          empty.className = 'js-sv-laundry-empty';
          empty.innerHTML = '<td colspan="3" class="text-muted text-center py-2">No items yet.</td>';
          tbody.appendChild(empty);
        }
        reindexRows();
      });

      reindexRows();
    })();
  </script>
@endpush
