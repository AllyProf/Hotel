@extends('layouts.app')

@section('title', 'Update Available Rooms')

@push('styles')
  <style>
    .update-rooms-tile {
      padding: 0;
      overflow: hidden;
    }
    .update-rooms-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      padding: 18px 20px;
      border-bottom: 1px solid rgba(0,0,0,.08);
    }
    .update-rooms-toolbar h2 {
      margin: 0;
      font-size: 20px;
      font-weight: 700;
      color: #222;
    }
    .update-rooms-actions {
      display: flex;
      gap: 10px;
    }
    .update-rooms-datebar {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      padding: 16px 20px;
      background: #fafafa;
      border-bottom: 1px solid rgba(0,0,0,.08);
    }
    .update-rooms-datebar label {
      font-weight: 700;
      margin: 0;
      color: #333;
    }
    .update-rooms-date-nav {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .update-rooms-date-nav .btn-nav {
      width: 36px;
      height: 36px;
      padding: 0;
      line-height: 34px;
      font-weight: 700;
      color: #555;
      background: #fff;
      border: 1px solid rgba(0,0,0,.15);
    }
    .update-rooms-date-nav .btn-nav:hover {
      background: #f5f5f5;
    }
    .update-rooms-date-input {
      position: relative;
      width: 170px;
    }
    .update-rooms-date-input .fa-calendar {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
      pointer-events: none;
    }
    .update-rooms-date-input .form-control {
      padding-left: 32px;
    }
    .update-rooms-scroll {
      overflow-x: auto;
      padding: 0 0 8px;
    }
    .update-rooms-table {
      width: max-content;
      min-width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin: 0;
    }
    .update-rooms-table th,
    .update-rooms-table td {
      border: 1px solid rgba(0,0,0,.12);
      text-align: center;
      vertical-align: middle;
      min-width: 92px;
      padding: 10px 8px;
      white-space: nowrap;
    }
    .update-rooms-table thead th {
      background: #4a4a4a;
      color: #fff;
      font-weight: 700;
      font-size: 13px;
      border-color: #3d3d3d;
    }
    .update-rooms-table thead th.room-col {
      min-width: 180px;
      text-align: left;
      padding-left: 16px;
      position: sticky;
      left: 0;
      z-index: 3;
      background: #4a4a4a;
    }
    .update-rooms-table tbody td.room-col {
      text-align: left;
      padding-left: 16px;
      font-weight: 600;
      background: #fff;
      position: sticky;
      left: 0;
      z-index: 2;
    }
    .update-rooms-table tbody tr.summary-row td {
      background: #eceff1;
      font-weight: 700;
      color: #333;
    }
    .update-rooms-table tbody tr.summary-row td.room-col {
      background: #eceff1;
    }
    .update-rooms-table tbody tr.available-row td {
      background: #fff;
    }
    .update-rooms-open-toggle {
      width: 18px;
      height: 18px;
      border: none;
      border-radius: 2px;
      padding: 0;
      cursor: pointer;
      display: inline-block;
      background: #2ecc71;
    }
    .update-rooms-open-toggle.is-closed {
      background: #e74c3c;
    }
    .update-rooms-open-toggle.is-partial {
      background: #f1c40f;
    }
    .update-rooms-count-input {
      width: 56px;
      margin: 0 auto;
      text-align: center;
      font-weight: 600;
      border: 1px solid rgba(0,0,0,.2);
      border-radius: 2px;
      padding: 4px 6px;
    }
    .update-rooms-count-input:focus {
      border-color: #1877f2;
      outline: none;
      box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.15);
    }
    .btn-update-rooms-save {
      background: #1877f2 !important;
      border-color: #1877f2 !important;
      color: #fff !important;
      font-weight: 700;
      min-width: 90px;
    }
    .btn-update-rooms-save:hover,
    .btn-update-rooms-save:focus {
      background: #166fe0 !important;
      border-color: #166fe0 !important;
      color: #fff !important;
    }
    .update-rooms-empty {
      padding: 40px 20px;
      text-align: center;
      color: #666;
    }
    .ota-availability-modal .modal-header {
      background: #eceff1;
      border-bottom: 1px solid rgba(0,0,0,.08);
    }
    .ota-availability-modal .modal-title {
      font-weight: 700;
      font-size: 17px;
    }
    .ota-availability-modal .ota-all-row {
      padding: 10px 12px;
      background: #f8f9fa;
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 4px;
      margin-bottom: 14px;
      font-weight: 700;
    }
    .ota-availability-modal .ota-list {
      max-height: 280px;
      overflow-y: auto;
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 4px;
      padding: 8px 12px;
    }
    .ota-availability-modal .ota-list.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }
    .ota-availability-modal .ota-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 7px 0;
      border-bottom: 1px solid rgba(0,0,0,.05);
      margin: 0;
      cursor: pointer;
    }
    .ota-availability-modal .ota-item:last-child {
      border-bottom: none;
    }
    .ota-availability-modal .ota-item img {
      width: 22px;
      height: 22px;
      object-fit: contain;
    }
    .ota-availability-action {
      display: flex;
      gap: 16px;
      margin-bottom: 14px;
    }
    .ota-availability-action label {
      font-weight: 600;
      margin: 0;
      cursor: pointer;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-bed"></i> Channel Manager <small class="text-muted">Update Available Rooms</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">Channel Manager</a></li>
      <li class="breadcrumb-item">Update Rooms</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile update-rooms-tile">
        <form id="updateRoomsForm" method="POST" action="{{ route('hotel.channel-manager.update-rooms.store') }}">
          @csrf
          <input type="hidden" name="start_date" value="{{ $startDate }}">

          <div class="update-rooms-toolbar">
            <h2>Update Available Rooms</h2>
            <div class="update-rooms-actions">
              <button type="button" class="btn btn-light border" id="resetRoomsBtn">Reset</button>
              <button type="submit" class="btn btn-update-rooms-save">Save</button>
            </div>
          </div>

          <div class="update-rooms-datebar">
            <label for="startDatePicker">Choose Start Date:</label>
            <div class="update-rooms-date-nav">
              <a href="{{ route('hotel.channel-manager.update-rooms', ['start_date' => \Carbon\Carbon::parse($startDate)->subDays($windowDays)->format('Y-m-d')]) }}"
                 class="btn btn-nav" title="Previous {{ $windowDays }} days">&laquo;</a>
              <div class="update-rooms-date-input">
                <i class="fa fa-calendar"></i>
                <input class="form-control" type="date" id="startDatePicker" value="{{ $startDate }}">
              </div>
              <a href="{{ route('hotel.channel-manager.update-rooms', ['start_date' => \Carbon\Carbon::parse($startDate)->addDays($windowDays)->format('Y-m-d')]) }}"
                 class="btn btn-nav" title="Next {{ $windowDays }} days">&raquo;</a>
            </div>
          </div>

          @if(empty($grid['rooms']))
            <div class="update-rooms-empty">
              <p class="mb-2">No room types configured yet.</p>
              <a href="{{ route('hotel.settings.index', ['tab' => 'rooms']) }}" class="btn btn-primary btn-sm">Add rooms in Settings</a>
            </div>
          @else
            <div class="update-rooms-scroll">
              <table class="update-rooms-table" id="updateRoomsTable">
                <thead>
                  <tr>
                    <th class="room-col">Room</th>
                    @foreach($grid['dates'] as $date)
                      <th>{{ $date->format('jS M') }}</th>
                    @endforeach
                  </tr>
                </thead>
                <tbody>
                  <tr class="available-row">
                    <td class="room-col">Available</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      @php
                        $state = $grid['isOpen'][$dateKey] ?? 'open';
                        $otaJson = json_encode($grid['otaAvailability'][$dateKey] ?? []);
                      @endphp
                      <td>
                        <button type="button"
                          class="update-rooms-open-toggle js-availability-toggle {{ $state === 'closed' ? 'is-closed' : ($state === 'partial' ? 'is-partial' : '') }}"
                          data-date="{{ $dateKey }}"
                          data-label="{{ \Carbon\Carbon::parse($dateKey)->format('jS M') }}"
                          title="Set Available or Stop Sell by OTA"
                          aria-label="Update availability for {{ $dateKey }}"></button>
                        <input type="hidden"
                          class="js-availability-input"
                          name="availability[{{ $dateKey }}]"
                          value="{{ $otaJson }}"
                          data-original="{{ $otaJson }}"
                          data-date="{{ $dateKey }}">
                      </td>
                    @endforeach
                  </tr>

                  @foreach($grid['rooms'] as $room)
                    <tr class="room-row" data-room-id="{{ $room['id'] }}">
                      <td class="room-col">{{ $room['name'] }}</td>
                      @foreach($grid['dateKeys'] as $dateKey)
                        <td>
                          <input type="number"
                            class="update-rooms-count-input js-room-count"
                            name="rooms[{{ $room['id'] }}][{{ $dateKey }}]"
                            value="{{ $room['counts'][$dateKey] }}"
                            min="0"
                            max="999"
                            data-original="{{ $room['counts'][$dateKey] }}"
                            data-date="{{ $dateKey }}"
                            required>
                        </td>
                      @endforeach
                    </tr>
                  @endforeach

                  <tr class="summary-row">
                    <td class="room-col">Total Available Rooms</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      <td class="js-total-cell" data-date="{{ $dateKey }}">{{ $grid['totals'][$dateKey] }}</td>
                    @endforeach
                  </tr>

                  <tr class="summary-row">
                    <td class="room-col">Occupancy Percentage</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      <td class="js-occupancy-cell" data-date="{{ $dateKey }}">{{ number_format($grid['occupancy'][$dateKey], 0) }}%</td>
                    @endforeach
                  </tr>
                </tbody>
              </table>
            </div>
          @endif
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade ota-availability-modal" id="otaAvailabilityModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Update Availability — <span id="otaModalDateLabel"></span></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="ota-availability-action">
            <span class="font-weight-bold mr-2">Action:</span>
            <label><input type="radio" name="ota_action" value="available" checked> Available</label>
            <label><input type="radio" name="ota_action" value="stop_sell"> Stop Sell</label>
          </div>

          <div class="ota-all-row">
            <label class="mb-0">
              <input type="checkbox" id="otaSelectAll" checked>
              All OTAs
            </label>
          </div>

          <div class="ota-list is-disabled" id="otaSelectList">
            @foreach($otas as $ota)
              <label class="ota-item">
                <input type="checkbox" class="js-ota-checkbox" value="{{ $ota['slug'] }}">
                <img src="{{ asset('panel-assets/img/otas/' . $ota['logo']) }}" alt="{{ $ota['name'] }}">
                <span>{{ $ota['name'] }}</span>
              </label>
            @endforeach
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-update-rooms-save" id="otaAvailabilityApply">Apply</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('updateRoomsForm');
      var datePicker = document.getElementById('startDatePicker');
      var resetBtn = document.getElementById('resetRoomsBtn');
      var table = document.getElementById('updateRoomsTable');
      var modal = $('#otaAvailabilityModal');
      var activeDate = null;
      var activeToggle = null;
      var otaSelectAll = document.getElementById('otaSelectAll');
      var otaSelectList = document.getElementById('otaSelectList');
      var otaApplyBtn = document.getElementById('otaAvailabilityApply');
      var otaSlugs = @json(array_column($otas, 'slug'));

      if (datePicker) {
        datePicker.addEventListener('change', function () {
          if (!this.value) return;
          window.location.href = '{{ route('hotel.channel-manager.update-rooms') }}?start_date=' + encodeURIComponent(this.value);
        });
      }

      if (!table) return;

      function parseAvailability(value) {
        if (!value) return defaultAvailability();
        try {
          var parsed = JSON.parse(value);
          return normalizeAvailability(parsed);
        } catch (e) {
          return defaultAvailability();
        }
      }

      function defaultAvailability() {
        var status = {};
        otaSlugs.forEach(function (slug) {
          status[slug] = true;
        });
        return status;
      }

      function normalizeAvailability(raw) {
        var status = defaultAvailability();
        otaSlugs.forEach(function (slug) {
          if (Object.prototype.hasOwnProperty.call(raw, slug)) {
            status[slug] = !!raw[slug];
          }
        });
        return status;
      }

      function availabilityState(status) {
        var values = otaSlugs.map(function (slug) { return !!status[slug]; });
        var openCount = values.filter(Boolean).length;
        if (openCount === values.length) return 'open';
        if (openCount === 0) return 'closed';
        return 'partial';
      }

      function setToggleState(toggle, state) {
        toggle.classList.remove('is-closed', 'is-partial');
        if (state === 'closed') toggle.classList.add('is-closed');
        if (state === 'partial') toggle.classList.add('is-partial');
      }

      function getHiddenInput(date) {
        return table.querySelector('.js-availability-input[data-date="' + date + '"]');
      }

      function recalculateTotals() {
        var totals = {};
        table.querySelectorAll('.js-room-count').forEach(function (input) {
          var date = input.getAttribute('data-date');
          var value = parseInt(input.value, 10);
          if (isNaN(value) || value < 0) value = 0;
          totals[date] = (totals[date] || 0) + value;
        });

        table.querySelectorAll('.js-total-cell').forEach(function (cell) {
          var date = cell.getAttribute('data-date');
          cell.textContent = totals[date] || 0;
        });

        table.querySelectorAll('.js-occupancy-cell').forEach(function (cell) {
          cell.textContent = '0%';
        });
      }

      table.querySelectorAll('.js-room-count').forEach(function (input) {
        input.addEventListener('input', recalculateTotals);
        input.addEventListener('change', function () {
          var value = parseInt(this.value, 10);
          if (isNaN(value) || value < 0) this.value = 0;
          if (value > 999) this.value = 999;
          recalculateTotals();
        });
      });

      function syncOtaListDisabled() {
        if (!otaSelectList || !otaSelectAll) return;
        otaSelectList.classList.toggle('is-disabled', otaSelectAll.checked);
      }

      if (otaSelectAll) {
        otaSelectAll.addEventListener('change', syncOtaListDisabled);
      }

      table.querySelectorAll('.js-availability-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          activeDate = this.getAttribute('data-date');
          activeToggle = this;
          var label = this.getAttribute('data-label');
          var hidden = getHiddenInput(activeDate);
          var current = parseAvailability(hidden ? hidden.value : null);
          var state = availabilityState(current);

          document.getElementById('otaModalDateLabel').textContent = label;
          var availableRadio = document.querySelector('input[name="ota_action"][value="available"]');
          var stopSellRadio = document.querySelector('input[name="ota_action"][value="stop_sell"]');
          if (state === 'closed') {
            availableRadio.checked = true;
            stopSellRadio.checked = false;
          } else {
            availableRadio.checked = false;
            stopSellRadio.checked = true;
          }

          if (otaSelectAll) otaSelectAll.checked = true;
          document.querySelectorAll('#otaSelectList .js-ota-checkbox').forEach(function (cb) {
            cb.checked = false;
          });
          syncOtaListDisabled();
          modal.modal('show');
        });
      });

      if (otaApplyBtn) {
        otaApplyBtn.addEventListener('click', function () {
          if (!activeDate) return;

          var hidden = getHiddenInput(activeDate);
          if (!hidden) return;

          var current = parseAvailability(hidden.value);
          var action = document.querySelector('input[name="ota_action"]:checked');
          var makeAvailable = action && action.value === 'available';
          var selectedSlugs = [];

          if (otaSelectAll && otaSelectAll.checked) {
            selectedSlugs = otaSlugs.slice();
          } else {
            document.querySelectorAll('#otaSelectList .js-ota-checkbox:checked').forEach(function (cb) {
              selectedSlugs.push(cb.value);
            });
          }

          if (selectedSlugs.length === 0) {
            if (typeof swal === 'function') {
              swal('Select OTAs', 'Choose All OTAs or pick at least one OTA.', 'warning');
            }
            return;
          }

          selectedSlugs.forEach(function (slug) {
            current[slug] = makeAvailable;
          });

          hidden.value = JSON.stringify(current);
          if (activeToggle) {
            setToggleState(activeToggle, availabilityState(current));
          }

          modal.modal('hide');
        });
      }

      if (resetBtn) {
        resetBtn.addEventListener('click', function () {
          if (typeof swal === 'function') {
            swal({
              title: 'Reset changes?',
              text: 'All unsaved edits will be restored to the last saved values.',
              type: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Reset',
              cancelButtonText: 'Cancel'
            }, function (confirmed) {
              if (confirmed) applyReset();
            });
          } else if (window.confirm('Reset all unsaved changes?')) {
            applyReset();
          }
        });
      }

      function applyReset() {
        table.querySelectorAll('.js-room-count').forEach(function (input) {
          input.value = input.getAttribute('data-original');
        });
        table.querySelectorAll('.js-availability-input').forEach(function (hidden) {
          var original = hidden.getAttribute('data-original') || '{}';
          hidden.value = original;
          var toggle = table.querySelector('.js-availability-toggle[data-date="' + hidden.getAttribute('data-date') + '"]');
          if (toggle) {
            setToggleState(toggle, availabilityState(parseAvailability(original)));
          }
        });
        recalculateTotals();
      }

      if (form) {
        form.addEventListener('submit', function (e) {
          var invalid = false;
          table.querySelectorAll('.js-room-count').forEach(function (input) {
            var value = parseInt(input.value, 10);
            if (isNaN(value) || value < 0 || value > 999) invalid = true;
          });
          if (invalid) {
            e.preventDefault();
            if (typeof swal === 'function') {
              swal('Invalid value', 'Room counts must be between 0 and 999.', 'warning');
            }
          }
        });
      }
    })();
  </script>
@endpush
