@extends('layouts.app')

@section('title', $ui['title'] ?? 'Front Desk Check-in')

@push('styles')
  <style>
    :root {
      --ci-brand: #940000;
      --ci-brand-dark: #7a0000;
      --ci-brand-soft: rgba(148, 0, 0, 0.08);
    }

    .ci-topbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
      padding-bottom: 14px;
      border-bottom: 1px solid #eee;
    }

    .ci-topbar__left {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      min-width: 0;
    }

    .ci-topbar__left h3 {
      margin: 0;
      font-size: 18px;
      font-weight: 400;
      color: #333;
      white-space: nowrap;
    }

    .ci-topbar__left h3 .ci-count {
      color: #9ca3af;
      font-weight: 400;
      font-size: 14px;
    }

    .ci-topbar__left h3 .ci-count::before {
      content: '·';
      margin: 0 10px;
      color: #d1d5db;
      font-weight: 300;
    }

    .ci-topbar__right {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }

    .ci-topbar__right label {
      margin: 0;
      font-size: 12px;
      font-weight: 700;
      color: #555;
      white-space: nowrap;
    }

    .ci-date-field {
      position: relative;
      display: inline-flex;
      align-items: stretch;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
      height: 34px;
      min-width: 178px;
      cursor: pointer;
    }

    .ci-date-field__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      min-width: 38px;
      background: #f6f6f6;
      border-right: 1px solid #e5e5e5;
      color: var(--ci-brand);
      font-size: 14px;
      pointer-events: none;
    }

    .ci-date-field input[type="date"] {
      flex: 1;
      width: 140px;
      min-width: 140px;
      height: 100%;
      padding: 0 10px;
      font-size: 13px;
      font-weight: 500;
      color: #333;
      border: none;
      background: transparent;
      cursor: pointer;
    }

    .ci-date-field:focus-within {
      border-color: var(--ci-brand);
      box-shadow: 0 0 0 1px rgba(148, 0, 0, 0.12);
    }

    .ci-date-field input[type="date"]:focus {
      outline: none;
    }

    .ci-date-field input[type="date"]::-webkit-calendar-picker-indicator {
      opacity: 0.6;
      cursor: pointer;
    }

    .btn-ci-primary {
      background: var(--ci-brand) !important;
      border-color: var(--ci-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 6px 14px;
    }

    .btn-ci-primary:hover {
      background: var(--ci-brand-dark) !important;
      border-color: var(--ci-brand-dark) !important;
      color: #fff !important;
    }

    .btn-ci-outline {
      background: #fff !important;
      border-color: #d1d5db !important;
      color: #555 !important;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 12px;
      height: 34px;
      line-height: 1.2;
    }

    .ci-table thead th:last-child,
    .ci-table tbody td.ci-actions-cell {
      min-width: 220px;
      width: 220px;
      padding-left: 12px;
      padding-right: 12px;
    }

    .ci-group-btn {
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
      padding: 6px 10px;
      height: 36px;
    }

    .ci-room-progress {
      display: block;
      font-size: 11px;
      color: #6b7280;
      margin-top: 2px;
    }

    .ci-group-modal .modal-header {
      background: #940000;
      color: #fff;
    }

    .ci-group-modal .modal-header .close {
      color: #fff;
      opacity: 0.9;
    }

    .ci-group-section {
      font-size: 14px;
      font-weight: 700;
      color: #333;
      margin: 18px 0 12px;
      padding-bottom: 6px;
      border-bottom: 2px solid #940000;
      display: inline-block;
    }

    .ci-group-section:first-child {
      margin-top: 0;
    }

    .ci-group-slot.is-other-slot .control-label {
      color: #856404;
    }

    .ci-group-slot.is-other-slot .form-control {
      border-color: #ffc107;
      background: #fffdf5;
    }

    .ci-actions {
      display: inline-flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 10px;
      padding: 6px 8px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
    }

    .ci-action-form {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin: 0;
    }

    .ci-room-select {
      width: 118px;
      min-width: 118px;
      max-width: 118px;
      height: 36px;
      font-size: 13px;
      padding: 0 10px;
      border-color: #d1d5db;
      background: #fff;
    }

    .ci-room-select:focus {
      border-color: var(--ci-brand);
      box-shadow: 0 0 0 1px rgba(148, 0, 0, 0.12);
    }

    .ci-action-buttons {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .ci-icon-btn {
      width: 36px;
      height: 36px;
      padding: 0;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 14px;
    }

    .ci-icon-btn.btn-ci-primary {
      padding: 0;
    }

    .ci-icon-btn.btn-ci-outline {
      padding: 0;
    }

    .ci-icon-btn.btn-ci-outline:hover {
      background: var(--ci-brand-soft) !important;
      border-color: var(--ci-brand) !important;
      color: var(--ci-brand) !important;
    }

    .ci-table-wrap { overflow-x: auto; }

    .ci-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 980px;
    }

    .ci-table thead th {
      background: var(--ci-brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 10px;
      border: 1px solid var(--ci-brand-dark);
      white-space: nowrap;
      vertical-align: middle;
    }

    .ci-table tbody td {
      border: 1px solid #ddd;
      padding: 10px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
    }

    .ci-table tbody tr:hover td {
      background: #fafafa;
    }

    .ci-booking-id {
      font-size: 12px;
      font-weight: 600;
      color: #333;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 2px 6px;
      white-space: nowrap;
    }

    .ci-guest-name {
      font-weight: 700;
      color: #222;
    }

    .ci-status {
      display: inline-flex;
      align-items: center;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .ci-status.is-reserved { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .ci-status.is-assigned { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

    .ci-room-badge {
      display: inline-block;
      font-size: 12px;
      font-weight: 700;
      color: var(--ci-brand);
      background: var(--ci-brand-soft);
      border: 1px solid rgba(148, 0, 0, 0.15);
      border-radius: 4px;
      padding: 3px 8px;
    }

    .ci-room-muted {
      color: #9ca3af;
      font-style: italic;
      font-size: 12px;
    }

    .ci-empty {
      padding: 56px 20px;
      text-align: center;
      color: #888;
      border: 1px dashed #ddd;
      border-radius: 4px;
      background: #fafafa;
    }

    .ci-empty i {
      font-size: 36px;
      color: #ccc;
      margin-bottom: 12px;
    }

    .rd-source {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 4px;
      white-space: nowrap;
    }

    .rd-source-direct { background: #fef2f2; color: #940000; border: 1px solid #fecaca; }
    .rd-source-booking { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .rd-source-expedia { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .rd-source-agoda { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .rd-source-airbnb { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }
    .rd-source-goibibo { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .rd-source-mmt { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .rd-source-trip { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .rd-source-cleartrip { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
    .rd-source-hostelworld { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .rd-source-hotelbeds { background: #ecfdf5; color: #047857; border: 1px solid #bbf7d0; }
    .rd-source-traveloka { background: #eff6ff; color: #0284c7; border: 1px solid #bae6fd; }
    .rd-source-yatra { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .rd-source-ota { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .rd-source-neutral { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-sign-in"></i> {{ $ui['title'] ?? 'Front Desk Check-in' }}</h1>
      <p>Assign rooms and check guests in on arrival day</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Check-in</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="ci-topbar">
          <div class="ci-topbar__left">
            <h3>
              Today's Arrivals
              <span class="ci-count">{{ $bookings->count() }} {{ Str::plural('arrival', $bookings->count()) }}</span>
            </h3>
          </div>

          <form method="GET" action="{{ route('hotel.check-in.index') }}" class="ci-topbar__right">
            <label for="ci_date">Arrival Date</label>
            <div class="ci-date-field">
              <span class="ci-date-field__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
              <input type="date" id="ci_date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            @if($date->format('Y-m-d') !== now()->format('Y-m-d'))
              <a href="{{ route('hotel.check-in.index') }}" class="btn btn-sm btn-ci-outline">Today</a>
            @endif
          </form>
        </div>

        <div class="ci-table-wrap">
          @if($bookings->isEmpty())
            <div class="ci-empty">
              <i class="fa fa-bed"></i>
              <div>No arrivals for {{ $date->format('d M Y') }}.</div>
              <p class="text-muted mt-2 mb-0" style="font-size:13px;">
                Guests appear here when their check-in date matches the selected date and they are not yet checked in.
              </p>
            </div>
          @else
            <table class="ci-table table table-hover table-bordered mb-0">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Guest</th>
                  <th>Source</th>
                  <th>Room Type</th>
                  <th>Check-out</th>
                  <th>Guests</th>
                  <th>Room No</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($bookings as $booking)
                  @php
                    $plan = $assignmentPlans[$booking->id] ?? ['is_multi' => false, 'units' => []];
                    $isMulti = ! empty($plan['is_multi']);
                    $units = $isMulti ? [] : ($plan['units'] ?? []);
                    $selectedUnitId = old('room_unit_id', $booking->room_unit_id);
                    $pmsClass = match($booking->pms_status) {
                      'assigned' => 'is-assigned',
                      default => 'is-reserved',
                    };
                  @endphp
                  <tr class="js-ci-booking-row"
                      data-is-multi="{{ $isMulti ? '1' : '0' }}"
                      data-plan='@json($plan)'
                      data-guest="{{ $booking->guestName() }}"
                      data-booking-id="{{ $booking->booking_id }}"
                      data-checkin-url="{{ route('hotel.check-in.check-in', $booking) }}"
                      data-assign-url="{{ route('hotel.check-in.assign', $booking) }}">
                    <td><span class="ci-booking-id">{{ $booking->booking_id }}</span></td>
                    <td><span class="ci-guest-name js-ci-guest-name">{{ $booking->guestName() }}</span></td>
                    <td>
                      @if($booking->sourceDisplayLabel() === '—')
                        —
                      @else
                        <span class="rd-source {{ $booking->sourceBadgeClass() }}">{{ $booking->sourceDisplayLabel() }}</span>
                      @endif
                    </td>
                    <td>
                      {{ $booking->roomLabel() }}
                      @if($isMulti)
                        <span class="ci-room-progress">× {{ $booking->roomCount() }} rooms</span>
                      @endif
                    </td>
                    <td>{{ $booking->checkoutLabel() }}</td>
                    <td>{{ $booking->guestCount() }}</td>
                    <td>
                      @if($booking->isRoomAssigned())
                        <span class="ci-room-badge">{{ $booking->assignedRoomLabel() }}</span>
                        @if($isMulti && ! $booking->isFullyAssigned())
                          <span class="ci-room-progress">{{ $booking->assignedRoomCount() }}/{{ $booking->roomCount() }} assigned</span>
                        @endif
                      @else
                        <span class="ci-room-muted">Not assigned</span>
                      @endif
                    </td>
                    <td><span class="ci-status {{ $pmsClass }}">{{ $booking->pmsStatusLabel() }}</span></td>
                    <td class="ci-actions-cell">
                      <div class="ci-actions">
                        @if($isMulti)
                          <button type="button"
                                  class="btn btn-sm btn-ci-outline ci-group-btn js-ci-open-group-modal"
                                  title="Select rooms for group">
                            <i class="fa fa-bed"></i>
                            {{ $booking->assignedRoomCount() }}/{{ $booking->roomCount() }} rooms
                          </button>
                          <button type="button"
                                  class="btn btn-sm btn-ci-primary ci-icon-btn js-ci-group-checkin-btn"
                                  title="Check in group"
                                  aria-label="Check in group">
                            <i class="fa fa-check"></i>
                          </button>
                        @else
                          <form method="POST" action="{{ route('hotel.check-in.check-in', $booking) }}" class="ci-action-form js-ci-checkin-form">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                            <select name="room_unit_id" class="form-control form-control-sm ci-room-select js-ci-room-select" {{ $booking->isRoomAssigned() ? '' : 'required' }}>
                              @if($booking->isRoomAssigned())
                                <option value="{{ $booking->room_unit_id }}" selected>{{ $booking->assignedRoomLabel() }}</option>
                              @else
                                <option value="">Select room</option>
                              @endif
                              @foreach($units as $unit)
                                @if((int) $unit['id'] !== (int) $booking->room_unit_id)
                                  <option value="{{ $unit['id'] }}" @selected((int) $selectedUnitId === (int) $unit['id'])>
                                    {{ $unit['label'] }}
                                  </option>
                                @endif
                              @endforeach
                            </select>
                            <div class="ci-action-buttons">
                              <button type="button"
                                      class="btn btn-sm btn-ci-primary ci-icon-btn js-ci-checkin-btn"
                                      title="Check in guest"
                                      aria-label="Check in guest"
                                      data-loader-text="Checking in...">
                                <i class="fa fa-check"></i>
                              </button>
                              @if(! $booking->isRoomAssigned())
                                <button type="button"
                                        class="btn btn-sm btn-ci-outline ci-icon-btn js-ci-assign-btn"
                                        title="Assign room only"
                                        aria-label="Assign room only"
                                        data-assign-url="{{ route('hotel.check-in.assign', $booking) }}"
                                        data-loader-text="Assigning room...">
                                  <i class="fa fa-bed"></i>
                                </button>
                              @endif
                            </div>
                          </form>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade ci-group-modal" id="ciGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-bed"></i> Assign group rooms</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap">
            <div class="mb-2 mb-md-0">
              <p class="mb-1"><strong class="js-ci-group-guest">Guest</strong></p>
              <p class="text-muted mb-0 js-ci-group-subtitle" style="font-size:13px;"></p>
            </div>
            <span class="badge badge-danger js-ci-group-progress">0/0</span>
          </div>
          <p class="text-muted mb-3 js-ci-group-summary" style="font-size:13px;"></p>
          <div class="js-ci-group-slots-wrap"></div>
          <div class="alert alert-warning js-ci-group-lend mb-0 mt-3" style="display:none;">
            <div class="form-check mb-0">
              <input class="form-check-input js-ci-allow-other-types" type="checkbox" id="ciAllowOtherTypes">
              <label class="form-check-label" for="ciAllowOtherTypes">
                <strong>Use other room types</strong>
                <span class="d-block text-muted js-ci-group-lend-note" style="font-size:12px;font-weight:normal;"></span>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-ci-outline btn-sm js-ci-group-assign-btn">Save assignment</button>
          <button type="button" class="btn btn-primary btn-sm js-ci-group-save-checkin-btn" style="background:#940000;border-color:#940000;">
            <i class="fa fa-check"></i> Save &amp; check in
          </button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var modal = document.getElementById('ciGroupModal');
      var modalState = {
        row: null,
        plan: null,
        checkinUrl: '',
        assignUrl: '',
        guest: '',
        bookingId: '',
      };

      function guestNameFromRow(el) {
        var row = el.closest('tr');
        var nameEl = row ? row.querySelector('.js-ci-guest-name') : null;
        return nameEl ? nameEl.textContent.trim() : 'this guest';
      }

      function roomLabelFromRow(el) {
        var row = el.closest('tr');
        var select = row ? row.querySelector('.js-ci-room-select') : null;
        if (!select || !select.value) return '';
        return select.options[select.selectedIndex].text.trim();
      }

      function warn(title, text) {
        if (typeof swal === 'function') {
          swal(title, text, 'warning');
          return;
        }
        alert(text);
      }

      function confirmAction(options, onConfirm) {
        if (window.AppAlerts && typeof window.AppAlerts.confirm === 'function') {
          window.AppAlerts.confirm(options, onConfirm);
          return;
        }
        if (confirm((options.title || 'Confirm') + '\n' + (options.text || ''))) {
          onConfirm();
        }
      }

      function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
      }

      function selectedUnitIdsFromModal() {
        if (!modal) return [];
        return Array.from(modal.querySelectorAll('.js-ci-group-slot-select'))
          .map(function (select) { return select.value; })
          .filter(function (value) { return value !== ''; });
      }

      function slotByIndex(slotIndex) {
        var slots = (modalState.plan && modalState.plan.slots) || [];
        for (var i = 0; i < slots.length; i++) {
          if (Number(slots[i].index) === Number(slotIndex)) {
            return slots[i];
          }
        }
        return null;
      }

      function isOverflowSlot(slotIndex, allowOtherTypes) {
        if (!allowOtherTypes || !modalState.plan) return false;
        var primaryTotal = Number(modalState.plan.primary_total || 0);
        return Number(slotIndex) >= primaryTotal;
      }

      function unitOptionsForSlot(slot, allowOtherTypes, slotIndex) {
        var options = [{ id: '', label: 'Select room' }];
        var overflow = isOverflowSlot(slotIndex, allowOtherTypes);
        var seen = {};

        if (!overflow) {
          (slot.primary_units || []).forEach(function (unit) {
            var id = String(unit.id);
            seen[id] = true;
            options.push({ id: id, label: unit.label });
          });
        }

        if (overflow) {
          (modalState.plan.other_units || []).forEach(function (unit) {
            var id = String(unit.id);
            if (seen[id]) return;
            options.push({
              id: id,
              label: unit.label + ' · ' + unit.room_type,
            });
          });
        }

        return options;
      }

      function fillSlotSelect(select, allowOtherTypes) {
        var slotIndex = parseInt(select.getAttribute('data-slot-index'), 10);
        var slot = slotByIndex(slotIndex);
        if (!slot) return;

        var current = select.value || select.getAttribute('data-assigned-id') || '';
        var overflow = isOverflowSlot(slotIndex, allowOtherTypes);
        var slotWrap = select.closest('.ci-group-slot');

        if (slotWrap) {
          slotWrap.classList.toggle('is-other-slot', overflow);
        }

        select.innerHTML = '';
        unitOptionsForSlot(slot, allowOtherTypes, slotIndex).forEach(function (option) {
          var opt = document.createElement('option');
          opt.value = option.id;
          opt.textContent = option.label;
          if (current && current === option.id) {
            opt.selected = true;
          }
          select.appendChild(opt);
        });
      }

      function updateGroupProgress() {
        if (!modal || !modalState.plan) return;
        var progress = modal.querySelector('.js-ci-group-progress');
        if (!progress) return;
        var selected = selectedUnitIdsFromModal().length;
        progress.textContent = selected + '/' + modalState.plan.required;
      }

      function refreshGroupSlotOptions() {
        if (!modal) return;

        var selected = selectedUnitIdsFromModal();

        modal.querySelectorAll('.js-ci-group-slot-select').forEach(function (select) {
          var ownValue = select.value;
          Array.from(select.options).forEach(function (option) {
            if (!option.value) return;
            var taken = selected.indexOf(option.value) !== -1 && option.value !== ownValue;
            option.disabled = taken;
          });
        });

        updateGroupProgress();
      }

      function applyAllowOtherTypes(allowOtherTypes) {
        if (!modal) return;

        modal.querySelectorAll('.js-ci-group-slot-select').forEach(function (select) {
          fillSlotSelect(select, allowOtherTypes);
        });

        refreshGroupSlotOptions();
      }

      function renderGroupModal(row) {
        if (!modal || !row) return;

        var plan = JSON.parse(row.getAttribute('data-plan') || '{}');
        modalState.row = row;
        modalState.plan = plan;
        modalState.checkinUrl = row.getAttribute('data-checkin-url') || '';
        modalState.assignUrl = row.getAttribute('data-assign-url') || '';
        modalState.guest = row.getAttribute('data-guest') || '';
        modalState.bookingId = row.getAttribute('data-booking-id') || '';

        var guestEl = modal.querySelector('.js-ci-group-guest');
        var subtitle = modal.querySelector('.js-ci-group-subtitle');
        var summary = modal.querySelector('.js-ci-group-summary');
        var slotsWrap = modal.querySelector('.js-ci-group-slots-wrap');
        var lendBox = modal.querySelector('.js-ci-group-lend');
        var lendNote = modal.querySelector('.js-ci-group-lend-note');
        var allowOther = modal.querySelector('.js-ci-allow-other-types');

        if (guestEl) {
          guestEl.textContent = modalState.guest;
        }

        if (subtitle) {
          subtitle.textContent = 'Booking ' + modalState.bookingId;
        }

        if (summary) {
          summary.textContent = 'Select ' + plan.required + ' room numbers for this group. Rooms are grouped by booked type.';
        }

        if (allowOther) {
          allowOther.checked = false;
        }

        if (lendBox) {
          lendBox.style.display = plan.can_lend_other ? 'block' : 'none';
        }

        if (lendNote) {
          var available = Math.max(0, (plan.primary_total || 0));
          lendNote.textContent = plan.shortfall > 0
            ? 'Only ' + available + ' matching room(s) free. Turn this on to assign the remaining ' + plan.shortfall + ' from other types.'
            : 'Allow assigning rooms from other categories when needed.';
        }

        if (!slotsWrap) return;

        slotsWrap.innerHTML = '';
        var lineGroups = {};

        (plan.slots || []).forEach(function (slot) {
          var key = String(slot.line_label || 'Rooms');
          if (!lineGroups[key]) {
            lineGroups[key] = {
              label: slot.line_label || 'Rooms',
              slots: [],
            };
          }
          lineGroups[key].slots.push(slot);
        });

        Object.keys(lineGroups).forEach(function (key) {
          var group = lineGroups[key];
          var section = document.createElement('div');
          section.className = 'mb-3';

          var sectionTitle = document.createElement('div');
          sectionTitle.className = 'ci-group-section';
          sectionTitle.textContent = group.label + ' × ' + group.slots.length;
          section.appendChild(sectionTitle);

          var row = document.createElement('div');
          row.className = 'row';

          group.slots.forEach(function (slot) {
            var col = document.createElement('div');
            col.className = 'col-lg-2 col-md-3 col-sm-4 col-6';

            var formGroup = document.createElement('div');
            formGroup.className = 'form-group ci-group-slot mb-2';

            var label = document.createElement('label');
            label.className = 'control-label';
            label.textContent = 'Room ' + (slot.index + 1);
            formGroup.appendChild(label);

            var select = document.createElement('select');
            select.className = 'form-control js-ci-group-slot-select';
            select.setAttribute('data-slot-index', String(slot.index));
            if (slot.assigned_id) {
              select.setAttribute('data-assigned-id', String(slot.assigned_id));
            }
            select.addEventListener('change', function () {
              select.removeAttribute('data-assigned-id');
              refreshGroupSlotOptions();
            });

            formGroup.appendChild(select);
            col.appendChild(formGroup);
            row.appendChild(col);
          });

          section.appendChild(row);
          slotsWrap.appendChild(section);
        });

        applyAllowOtherTypes(false);

        if (window.jQuery && typeof jQuery(modal).modal === 'function') {
          jQuery(modal).modal('show');
        } else {
          modal.style.display = 'block';
        }
      }

      if (modal) {
        modal.addEventListener('change', function (event) {
          if (event.target && event.target.classList.contains('js-ci-allow-other-types')) {
            applyAllowOtherTypes(event.target.checked);
          }
        });
      }

      function postGroupAction(actionUrl, unitIds, allowOtherTypes) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;
        form.style.display = 'none';

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken();
        form.appendChild(csrfInput);

        var dateInput = document.createElement('input');
        dateInput.type = 'hidden';
        dateInput.name = 'date';
        dateInput.value = @json($date->format('Y-m-d'));
        form.appendChild(dateInput);

        unitIds.forEach(function (id) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'room_unit_ids[]';
          input.value = id;
          form.appendChild(input);
        });

        if (allowOtherTypes) {
          var lendInput = document.createElement('input');
          lendInput.type = 'hidden';
          lendInput.name = 'allow_other_types';
          lendInput.value = '1';
          form.appendChild(lendInput);
        }

        document.body.appendChild(form);
        form.submit();
      }

      function submitGroupForm(checkInAfter) {
        var plan = modalState.plan || {};
        var required = plan.required || 0;
        var unitIds = selectedUnitIdsFromModal();
        var allowOther = modal.querySelector('.js-ci-allow-other-types');
        var allowOtherTypes = allowOther ? allowOther.checked : false;

        if (unitIds.length === 0) {
          warn('Select rooms', 'Choose at least one room number.');
          return;
        }

        if (checkInAfter && unitIds.length < required) {
          warn('Select all rooms', 'Assign all ' + required + ' rooms before check-in.');
          return;
        }

        var uniqueIds = unitIds.filter(function (value, index, array) {
          return array.indexOf(value) === index;
        });

        if (uniqueIds.length !== unitIds.length) {
          warn('Duplicate rooms', 'Each room can only be selected once.');
          return;
        }

        postGroupAction(
          checkInAfter ? modalState.checkinUrl : modalState.assignUrl,
          unitIds,
          allowOtherTypes
        );
      }

      function planFromRow(row) {
        try {
          return JSON.parse(row.getAttribute('data-plan') || '{}');
        } catch (e) {
          return {};
        }
      }

      function assignedRoomLabelFromRow(row) {
        var badge = row.querySelector('.ci-room-badge');
        return badge ? badge.textContent.trim() : '';
      }

      document.querySelectorAll('.js-ci-open-group-modal').forEach(function (button) {
        button.addEventListener('click', function () {
          var row = button.closest('.js-ci-booking-row');
          if (!row) return;
          renderGroupModal(row);
        });
      });

      document.querySelectorAll('.js-ci-group-checkin-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          var row = button.closest('.js-ci-booking-row');
          if (!row) return;

          var plan = planFromRow(row);
          var required = plan.required || 0;
          var assignedCount = plan.assigned_count || 0;
          var guest = row.getAttribute('data-guest') || 'this guest';
          var checkinUrl = row.getAttribute('data-checkin-url') || '';

          if (assignedCount < required) {
            warn(
              'Assign rooms first',
              'Assign all ' + required + ' rooms before checking in. (' + assignedCount + '/' + required + ' assigned)'
            );
            renderGroupModal(row);
            return;
          }

          var rooms = assignedRoomLabelFromRow(row) || (required + ' rooms');
          confirmAction({
            title: 'Check in group?',
            text: 'Check in ' + guest + ' in rooms ' + rooms + '?',
            confirmText: 'Yes, check in',
            cancelText: 'Cancel',
          }, function () {
            postGroupAction(checkinUrl, [], false);
          });
        });
      });

      var assignGroupBtn = document.querySelector('.js-ci-group-assign-btn');
      if (assignGroupBtn) {
        assignGroupBtn.addEventListener('click', function () {
          submitGroupForm(false);
        });
      }

      var saveCheckinBtn = document.querySelector('.js-ci-group-save-checkin-btn');
      if (saveCheckinBtn) {
        saveCheckinBtn.addEventListener('click', function () {
          var plan = modalState.plan || {};
          var required = plan.required || 0;
          var unitIds = selectedUnitIdsFromModal();

          if (unitIds.length < required) {
            warn('Select all rooms', 'Choose all ' + required + ' rooms before check-in.');
            return;
          }

          var rooms = unitIds.join(', ');
          confirmAction({
            title: 'Check in group?',
            text: 'Check in ' + modalState.guest + ' in rooms ' + rooms + '?',
            confirmText: 'Yes, check in',
            cancelText: 'Cancel',
          }, function () {
            submitGroupForm(true);
          });
        });
      }

      document.querySelectorAll('.js-ci-checkin-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          var form = button.closest('form');
          var select = form ? form.querySelector('.js-ci-room-select') : null;

          if (select && !select.value) {
            warn('Select room', 'Please choose a room number before check-in.');
            return;
          }

          var guest = guestNameFromRow(button);
          var room = roomLabelFromRow(button);
          var text = 'Confirm check-in for ' + guest + (room ? ' in room ' + room + '.' : '.');

          confirmAction({
            title: 'Check in guest?',
            text: text,
            confirmText: 'Yes, check in',
            cancelText: 'Cancel',
          }, function () {
            form.submit();
          });
        });
      });

      document.querySelectorAll('.js-ci-assign-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          var checkinForm = button.closest('.js-ci-checkin-form');
          var row = button.closest('tr');
          var select = row ? row.querySelector('.js-ci-room-select') : null;
          var assignUrl = button.getAttribute('data-assign-url');

          if (!select || !select.value || !assignUrl || !checkinForm) {
            warn('Select room', 'Please choose a room number before assigning.');
            return;
          }

          var guest = guestNameFromRow(button);
          var room = select.options[select.selectedIndex].text.trim();

          confirmAction({
            title: 'Assign room?',
            text: 'Assign room ' + room + ' to ' + guest + '?',
            confirmText: 'Yes, assign',
            cancelText: 'Cancel',
          }, function () {
            var assignForm = document.createElement('form');
            assignForm.method = 'POST';
            assignForm.action = assignUrl;
            assignForm.style.display = 'none';

            var csrf = checkinForm.querySelector('input[name="_token"]');
            if (csrf) {
              assignForm.appendChild(csrf.cloneNode(true));
            }

            var dateInput = checkinForm.querySelector('input[name="date"]');
            if (dateInput) {
              assignForm.appendChild(dateInput.cloneNode(true));
            }

            var unitInput = document.createElement('input');
            unitInput.type = 'hidden';
            unitInput.name = 'room_unit_id';
            unitInput.value = select.value;
            assignForm.appendChild(unitInput);

            document.body.appendChild(assignForm);
            assignForm.submit();
          });
        });
      });
    });
  </script>
@endpush
