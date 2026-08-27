@php
  $modalOtas = $otas ?? config('otas', []);
@endphp

{{-- Availability — per-OTA or all channels --}}
<div class="modal fade rates-modal" id="availabilityConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md" role="document">
    <div class="modal-content rates-confirm-modal">
      <div class="modal-header">
        <h5 class="modal-title">Confirmation</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="{{ route('hotel.channel-manager.update-rates.availability') }}" id="availabilityForm">
        @csrf
        <input type="hidden" name="date" id="availFormDate" value="">
        <input type="hidden" name="start_date" value="{{ $startDate ?? '' }}">
        <input type="hidden" name="action" id="availFormAction" value="custom">
        <input type="hidden" name="ota_status" id="availFormOtaStatus" value="">

        <div class="modal-body">
          <p class="rates-confirm-lead mb-1"><strong>StopSell is applied on the following channels:</strong></p>
          <ul class="rates-confirm-channels mb-3" id="confirmStoppedChannels"></ul>
          <hr class="rates-confirm-divider">
          <p class="rates-confirm-prompt mb-3">Please specify which action you wish to do for ALL RATEPLANS ?</p>

          <p class="rates-confirm-section mb-2">Or set channels individually</p>
          @if(count($modalOtas))
            <div class="rates-ota-list" id="ratesOtaToggleList">
              @foreach($modalOtas as $ota)
                <div class="rates-ota-row">
                  <div class="rates-ota-info">
                    @if(!empty($ota['logo']))
                      <img src="{{ asset('panel-assets/img/otas/' . $ota['logo']) }}" alt="{{ $ota['name'] }}" class="rates-ota-logo">
                    @endif
                    <span class="rates-ota-name">{{ $ota['name'] }}</span>
                  </div>
                  <label class="rates-ota-switch mb-0">
                    <input type="checkbox" class="js-ota-channel-toggle" data-slug="{{ $ota['slug'] }}" checked>
                    <span class="rates-ota-switch-track"></span>
                    <span class="rates-ota-switch-label js-ota-switch-label">Open</span>
                  </label>
                </div>
              @endforeach
            </div>
          @else
            <div class="rates-ota-empty">
              <p class="mb-2">No channels connected yet.</p>
              <a href="{{ route('hotel.channel-manager.ota-mapping') }}" class="btn btn-sm rates-confirm-btn">Set up in OTA Mapping</a>
            </div>
          @endif
        </div>

        <div class="modal-footer rates-confirm-actions">
          @if(count($modalOtas))
            <button type="button" class="btn rates-confirm-btn js-avail-all" data-action="available">Available</button>
            <button type="button" class="btn rates-confirm-btn js-avail-all" data-action="stop_sell">Stop Sell</button>
            <button type="submit" class="btn rates-confirm-btn rates-confirm-btn-outline">Save</button>
          @else
            <a href="{{ route('hotel.channel-manager.ota-mapping') }}" class="btn rates-confirm-btn">OTA Mapping Setup</a>
          @endif
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Create Event --}}
<div class="modal fade rates-modal" id="createEventModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content rates-event-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="createEventModalTitle">Create Event</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="{{ route('hotel.channel-manager.update-rates.events') }}" id="createEventForm">
        @csrf
        <input type="hidden" name="event_date" id="eventDateField" value="">
        <input type="hidden" name="return_start_date" value="{{ $startDate ?? '' }}">
        <input type="hidden" name="return_view" id="eventReturnView" value="{{ $returnView ?? 'grid' }}">
        <input type="hidden" name="return_month" id="eventReturnMonth" value="{{ $month ?? '' }}">
        @if(!empty($calendar['selectedRoomId']))
          <input type="hidden" name="room_id" value="{{ $calendar['selectedRoomId'] }}">
        @endif

        <div class="modal-body">
          <div class="row align-items-end mb-3">
            <div class="col-md-4">
              <label class="rates-event-check mb-0">
                <input type="checkbox" name="demand_override" value="1" checked> Demand Override
              </label>
            </div>
            <div class="col-md-8">
              <label class="rates-event-label">Market Demand</label>
              <select name="market_demand" class="form-control form-control-sm">
                <option value="">Select</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="rates-event-label">Name *</label>
              <input type="text" name="name" class="form-control form-control-sm" placeholder="Event name" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="rates-event-label">Venue</label>
              <input type="text" name="venue" class="form-control form-control-sm" placeholder="Venue">
            </div>
          </div>

          <div class="row">
            <div class="col-md-3 mb-0">
              <label class="rates-event-label">Start date *</label>
              <input type="date" name="event_start" id="eventStartField" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3 mb-0">
              <label class="rates-event-label">End date *</label>
              <input type="date" name="event_end" id="eventEndField" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3 mb-0">
              <label class="rates-event-label">PAX</label>
              <input type="number" name="pax" min="0" class="form-control form-control-sm" placeholder="0">
            </div>
            <div class="col-md-3 mb-0">
              <label class="rates-event-label">Value</label>
              <input type="number" name="value" min="0" step="0.01" class="form-control form-control-sm" placeholder="0">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn rates-event-save">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('styles')
  <style>
    :root {
      --rates-brand: #940000;
      --rates-brand-hover: #7a0000;
    }

    .rates-confirm-modal .modal-header,
    .rates-event-modal .modal-header {
      background: var(--rates-brand);
      color: #fff;
      border: 0;
      padding: 10px 16px;
    }

    .rates-confirm-modal .modal-title,
    .rates-event-modal .modal-title {
      font-size: 15px;
      font-weight: 600;
    }

    .rates-confirm-modal .modal-body {
      padding: 16px 18px 12px;
    }

    .rates-confirm-lead {
      color: #333;
      font-size: 13px;
    }

    .rates-confirm-channels {
      list-style: none;
      padding: 0;
      margin: 0;
      min-height: 20px;
      color: #555;
      font-size: 13px;
    }

    .rates-confirm-channels li + li {
      margin-top: 2px;
    }

    .rates-confirm-channels li.is-none {
      color: #999;
      font-style: italic;
    }

    .rates-confirm-divider {
      border-color: #e8eaed;
      margin: 12px 0;
    }

    .rates-confirm-prompt {
      color: #666;
      font-size: 13px;
      margin: 0;
    }

    .rates-confirm-section {
      font-size: 12px;
      font-weight: 700;
      color: #888;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin: 0;
    }

    .rates-ota-list {
      max-height: 220px;
      overflow-y: auto;
      border: 1px solid #e8eaed;
      border-radius: 4px;
    }

    .rates-ota-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 8px 12px;
      border-bottom: 1px solid #f0f2f4;
    }

    .rates-ota-row:last-child {
      border-bottom: 0;
    }

    .rates-ota-info {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }

    .rates-ota-logo {
      width: 22px;
      height: 22px;
      object-fit: contain;
      flex-shrink: 0;
    }

    .rates-ota-name {
      font-size: 13px;
      font-weight: 600;
      color: #333;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .rates-ota-switch {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      flex-shrink: 0;
    }

    .rates-ota-switch input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .rates-ota-switch-track {
      position: relative;
      width: 36px;
      height: 20px;
      background: #c0392b;
      border-radius: 20px;
      transition: background 0.2s;
    }

    .rates-ota-switch-track:before {
      content: '';
      position: absolute;
      width: 14px;
      height: 14px;
      left: 3px;
      top: 3px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.2s;
    }

    .rates-ota-switch input:checked + .rates-ota-switch-track {
      background: var(--rates-brand);
    }

    .rates-ota-switch input:checked + .rates-ota-switch-track:before {
      transform: translateX(16px);
    }

    .rates-ota-switch-label {
      font-size: 11px;
      font-weight: 700;
      min-width: 58px;
      text-align: left;
      color: #666;
    }

    .rates-confirm-actions {
      justify-content: center;
      gap: 8px;
      border-top: 1px solid #e8eaed;
      padding: 12px 16px;
    }

    .rates-confirm-btn {
      background: var(--rates-brand) !important;
      border: 1px solid var(--rates-brand) !important;
      color: #fff !important;
      font-weight: 600;
      font-size: 13px;
      padding: 6px 18px;
      border-radius: 3px;
    }

    .rates-confirm-btn:hover {
      background: var(--rates-brand-hover) !important;
      border-color: var(--rates-brand-hover) !important;
      color: #fff !important;
    }

    .rates-confirm-btn-outline {
      background: #fff !important;
      color: var(--rates-brand) !important;
    }

    .rates-confirm-btn-outline:hover {
      background: #fdf5f5 !important;
      color: var(--rates-brand-hover) !important;
    }

    .rates-event-modal .modal-body {
      padding: 16px 18px;
    }

    .rates-event-modal .modal-footer {
      background: #fafafa;
      border-top: 1px solid #e8eaed;
      padding: 10px 16px;
      justify-content: flex-end;
      gap: 8px;
    }

    .rates-event-save {
      background: var(--rates-brand) !important;
      border: 1px solid var(--rates-brand) !important;
      color: #fff !important;
      font-weight: 600;
      font-size: 13px;
      padding: 6px 18px;
      border-radius: 3px;
    }

    .rates-event-save:hover {
      background: var(--rates-brand-hover) !important;
      border-color: var(--rates-brand-hover) !important;
      color: #fff !important;
    }

    .rates-event-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #444;
      margin-bottom: 4px;
    }

    .rates-event-check {
      font-size: 13px;
      font-weight: 600;
      color: #333;
    }

    .rates-ota-empty {
      text-align: center;
      padding: 20px 12px;
      border: 1px dashed #e0e4e8;
      border-radius: 4px;
      color: #666;
      font-size: 13px;
    }

    .rates-date-col-clickable {
      cursor: pointer;
    }

    .rates-available-cell {
      cursor: pointer;
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function () {
      var availabilityMeta = @json($availabilityMeta ?? []);
      var otaSlugs = @json(array_column($modalOtas, 'slug'));
      var confirmModal = $('#availabilityConfirmModal');
      var eventModal = $('#createEventModal');
      var availForm = document.getElementById('availabilityForm');

      function defaultOtaStatus() {
        var status = {};
        otaSlugs.forEach(function (slug) {
          status[slug] = true;
        });
        return status;
      }

      function normalizeOtaStatus(raw) {
        var status = defaultOtaStatus();
        if (!raw || typeof raw !== 'object') return status;
        otaSlugs.forEach(function (slug) {
          if (Object.prototype.hasOwnProperty.call(raw, slug)) {
            status[slug] = !!raw[slug];
          }
        });
        return status;
      }

      function stoppedChannelNames(status) {
        return Object.keys(status).filter(function (slug) {
          return !status[slug];
        }).map(function (slug) {
          var row = document.querySelector('.js-ota-channel-toggle[data-slug="' + slug + '"]');
          if (!row) return slug;
          var nameEl = row.closest('.rates-ota-row').querySelector('.rates-ota-name');
          return nameEl ? nameEl.textContent.trim() : slug;
        });
      }

      function renderStoppedChannels(names) {
        var list = document.getElementById('confirmStoppedChannels');
        list.innerHTML = '';
        if (!names.length) {
          var none = document.createElement('li');
          none.className = 'is-none';
          none.textContent = 'None';
          list.appendChild(none);
          return;
        }
        names.forEach(function (name) {
          var li = document.createElement('li');
          li.textContent = name;
          list.appendChild(li);
        });
      }

      function syncOtaToggleLabels() {
        document.querySelectorAll('.js-ota-channel-toggle').forEach(function (input) {
          var label = input.closest('.rates-ota-switch').querySelector('.js-ota-switch-label');
          if (label) {
            label.textContent = input.checked ? 'Open' : 'Stop Sell';
          }
        });
      }

      function loadOtaToggles(status) {
        document.querySelectorAll('.js-ota-channel-toggle').forEach(function (input) {
          var slug = input.getAttribute('data-slug');
          input.checked = !!status[slug];
        });
        syncOtaToggleLabels();
        renderStoppedChannels(stoppedChannelNames(status));
      }

      function collectOtaStatus() {
        var status = defaultOtaStatus();
        document.querySelectorAll('.js-ota-channel-toggle').forEach(function (input) {
          status[input.getAttribute('data-slug')] = input.checked;
        });
        return status;
      }

      function openAvailabilityModal(dateKey) {
        var meta = availabilityMeta[dateKey] || {};
        var status = normalizeOtaStatus(meta.ota_status || null);

        document.getElementById('availFormDate').value = dateKey;
        loadOtaToggles(status);
        confirmModal.modal('show');
      }

      function openEventModal(dateKey, label) {
        document.getElementById('eventDateField').value = dateKey;
        document.getElementById('eventStartField').value = dateKey;
        document.getElementById('eventEndField').value = dateKey;
        document.getElementById('createEventModalTitle').textContent = 'Create Event - ' + label;
        eventModal.modal('show');
      }

      document.querySelectorAll('.js-ota-channel-toggle').forEach(function (input) {
        input.addEventListener('change', function () {
          syncOtaToggleLabels();
          renderStoppedChannels(stoppedChannelNames(collectOtaStatus()));
        });
      });

      document.querySelectorAll('.js-avail-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
          document.getElementById('availFormAction').value = btn.getAttribute('data-action');
          document.getElementById('availFormOtaStatus').value = '';
          availForm.submit();
        });
      });

      if (availForm) {
        availForm.addEventListener('submit', function (e) {
          var action = document.getElementById('availFormAction').value;
          if (action === 'custom' || action === '') {
            document.getElementById('availFormAction').value = 'custom';
            document.getElementById('availFormOtaStatus').value = JSON.stringify(collectOtaStatus());
          }
        });
      }

      document.querySelectorAll('.js-availability-open').forEach(function (el) {
        el.addEventListener('click', function () {
          document.getElementById('availFormAction').value = 'custom';
          openAvailabilityModal(el.getAttribute('data-date'));
        });
      });

      document.querySelectorAll('.js-event-open').forEach(function (el) {
        el.addEventListener('click', function () {
          openEventModal(el.getAttribute('data-date'), el.getAttribute('data-label'));
        });
      });

      window.ratesOpenEventModal = openEventModal;
      window.ratesOpenAvailabilityModal = openAvailabilityModal;
    })();
  </script>
@endpush
