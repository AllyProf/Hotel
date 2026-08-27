@extends('layouts.app')

@section('title', 'Bulk Update')

@push('styles')
  <style>
    .bulk-tile { padding: 0; overflow: hidden; background: #fff; }
    .bulk-form { padding: 20px 24px 24px; }
    .bulk-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; margin-bottom: 18px; }
    .bulk-field label { display: block; font-weight: 700; font-size: 13px; color: #333; margin-bottom: 6px; }
    .bulk-field .form-control { max-width: 180px; }
    .bulk-type-label { font-weight: 700; font-size: 13px; color: #333; margin-right: 8px; }
    .bulk-type-group { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; }
    .bulk-type-group label { font-size: 13px; font-weight: 600; color: #444; margin: 0; cursor: pointer; }
    .bulk-weekdays { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
    .bulk-weekdays label { font-size: 13px; font-weight: 600; margin: 0; cursor: pointer; }
    .bulk-panel { display: none; }
    .bulk-panel.is-active { display: block; }
    .bulk-table-wrap { overflow-x: auto; margin-top: 8px; }
    .bulk-table { width: 100%; border-collapse: collapse; min-width: 640px; }
    .bulk-table thead th {
      background: #5a5a5a; color: #fff; font-size: 12px; font-weight: 700;
      padding: 8px 10px; border: 1px solid #4a4a4a; white-space: nowrap; vertical-align: middle;
    }
    .bulk-table tbody td { border: 1px solid #ddd; padding: 8px 10px; font-size: 13px; background: #fff; vertical-align: middle; }
    .bulk-table .col-check { width: 42px; text-align: center; }
    .bulk-table input[type="number"], .bulk-table input[type="text"] {
      width: 100%; min-width: 70px; max-width: 120px; border: 1px solid #ccc; border-radius: 2px; padding: 4px 6px; font-size: 13px;
    }
    .bulk-restr-table { min-width: 1200px; }
    .bulk-restr-table input[type="number"] { max-width: 72px; min-width: 56px; }
    .bulk-stop-toggle { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #666; }
    .bulk-stop-switch { position: relative; width: 38px; height: 20px; display: inline-block; }
    .bulk-stop-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .bulk-stop-track {
      position: absolute; inset: 0; background: #ccc; border-radius: 20px; cursor: pointer;
    }
    .bulk-stop-track:before {
      content: ''; position: absolute; width: 14px; height: 14px; left: 3px; top: 3px;
      background: #fff; border-radius: 50%; transition: transform .2s;
    }
    .bulk-stop-switch input:checked + .bulk-stop-track { background: #940000; }
    .bulk-stop-switch input:checked + .bulk-stop-track:before { transform: translateX(18px); }
    .bulk-top-actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-bulk-primary {
      background: #940000 !important; border-color: #940000 !important; color: #fff !important;
      font-weight: 600; font-size: 13px; padding: 6px 18px; border-radius: 3px;
    }
    .btn-bulk-primary:hover { background: #7a0000 !important; border-color: #7a0000 !important; color: #fff !important; }
    .btn-bulk-outline {
      background: #fff; border: 1px solid #c5ccd3; color: #333; font-weight: 600; font-size: 13px;
      padding: 6px 14px; border-radius: 3px;
    }
    .bulk-copy-icon { opacity: .7; font-size: 11px; margin-left: 4px; cursor: pointer; }
    .bulk-channel-select { min-width: 200px; max-width: 280px; }
    .bulk-status {
      padding: 10px 14px;
      border-radius: 4px;
      font-size: 13px;
      margin-bottom: 16px;
    }
    .bulk-status.is-ok { background: #eef8ee; border: 1px solid #c8e6c9; color: #2e7d32; }
    .bulk-status.is-warn { background: #fff8e6; border: 1px solid #ffe082; color: #8a6d00; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-dot-circle-o"></i> Bulk Update</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">Channel Manager</a></li>
      <li class="breadcrumb-item">Bulk Update</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile bulk-tile">
        <form method="POST" action="{{ route('hotel.channel-manager.bulk-update.store') }}" id="bulkUpdateForm">
          @csrf
          <input type="hidden" name="update_type" id="bulkUpdateType" value="inventory">

          <div class="bulk-form">
            @if($cmConnected)
              <div class="bulk-status is-ok">
                <i class="fa fa-check-circle"></i>
                Connected to Channel Manager API
                @if($cmSandbox) (Aiosell sandbox) @endif
                — changes sync to <strong>/update</strong> (inventory) and <strong>/update-rates</strong> (rates).
                @if($configuredOtaCount > 0)
                  {{ $configuredOtaCount }} OTA{{ $configuredOtaCount === 1 ? '' : 's' }} mapped.
                @else
                  <a href="{{ route('hotel.channel-manager.ota-mapping') }}">Set up OTA Mapping</a> first.
                @endif
              </div>
            @else
              <div class="bulk-status is-warn">
                <i class="fa fa-exclamation-triangle"></i>
                Channel Manager API is not configured. Changes save locally only until admin connects Aiosell in Platform Settings.
              </div>
            @endif

            <div class="bulk-row">
              <div class="bulk-field">
                <label for="fromDate">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" id="fromDate" value="{{ old('from_date', $today) }}" required>
              </div>
              <div class="bulk-field">
                <label for="toDate">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" id="toDate" value="{{ old('to_date', $today) }}" required>
              </div>
              <div class="bulk-panel is-active" data-panel="restrictions_rates restrictions_inventory">
                <div class="bulk-field">
                  <label for="bulkChannels">Select Channel</label>
                  <select name="channels[]" id="bulkChannels" class="form-control form-control-sm bulk-channel-select">
                    <option value="all" selected>All Channels</option>
                    @foreach($otas as $ota)
                      <option value="{{ $ota['slug'] }}">{{ $ota['name'] }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="bulk-top-actions">
                <div class="bulk-panel" data-panel="rate ratio increment">
                  <button type="submit" name="sync_otas" value="1" class="btn btn-bulk-outline btn-sm">Sync to OTAs</button>
                </div>
                <div class="bulk-panel" data-panel="restrictions_rates restrictions_inventory">
                  <button type="button" class="btn btn-bulk-outline btn-sm js-bulk-info" data-text="Restriction calendar view coming soon.">Restriction Calendar</button>
                  <button type="button" class="btn btn-bulk-outline btn-sm js-bulk-info" data-text="Restriction logs coming soon.">Restriction Logs</button>
                </div>
              </div>
            </div>

            <div class="bulk-row align-items-center">
              <span class="bulk-type-label">Type:</span>
              <div class="bulk-type-group">
                @foreach([
                  'inventory' => 'Inventory',
                  'rate' => 'Rate',
                  'ratio' => 'Ratio',
                  'increment' => 'Increment',
                  'restrictions_rates' => 'Restrictions (Rates)',
                  'restrictions_inventory' => 'Restrictions (Inventory)',
                ] as $value => $label)
                  <label>
                    <input type="radio" name="type_radio" value="{{ $value }}" class="js-bulk-type" {{ $loop->first ? 'checked' : '' }}>
                    {{ $label }}
                  </label>
                @endforeach
              </div>
            </div>

            <div class="bulk-row align-items-center bulk-panel" data-panel="rate ratio increment restrictions_rates restrictions_inventory">
              <span class="bulk-type-label">Weekdays:</span>
              <div class="bulk-weekdays">
                @foreach(['all' => 'All', 'sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat'] as $val => $lbl)
                  <label><input type="checkbox" name="weekdays[]" value="{{ $val }}" class="js-bulk-weekday" checked> {{ $lbl }}</label>
                @endforeach
              </div>
            </div>

            {{-- Inventory --}}
            <div class="bulk-panel is-active" data-panel="inventory">
              <div class="bulk-table-wrap">
                <table class="bulk-table">
                  <thead>
                    <tr>
                      <th class="col-check"><input type="checkbox" class="js-bulk-check-all" data-target="room"></th>
                      <th>Room</th>
                      <th>Inventory</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($rooms as $room)
                      <tr>
                        <td class="col-check"><input type="checkbox" name="selected_rooms[]" value="{{ $room->id }}" class="js-bulk-row-check" data-group="room"></td>
                        <td>{{ $room->name }}</td>
                        <td><input type="number" name="rooms[{{ $room->id }}]" min="0" max="999" placeholder=""></td>
                      </tr>
                    @empty
                      <tr><td colspan="3">No rooms configured.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            {{-- Rate / Ratio / Increment --}}
            <div class="bulk-panel" data-panel="rate ratio increment">
              <div class="bulk-table-wrap">
                <table class="bulk-table">
                  <thead>
                    <tr>
                      <th class="col-check"><input type="checkbox" class="js-bulk-check-all" data-target="plan"></th>
                      <th>Rateplans</th>
                      <th class="js-bulk-value-col">Rates</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($ratePlans as $plan)
                      <tr>
                        <td class="col-check"><input type="checkbox" name="selected_plans[]" value="{{ $plan['id'] }}" class="js-bulk-row-check" data-group="plan"></td>
                        <td>{{ $plan['label'] }}</td>
                        <td>
                          <input type="number" step="0.01" min="0" name="plans[{{ $plan['id'] }}]" class="js-bulk-plan-value" placeholder="">
                        </td>
                      </tr>
                    @empty
                      <tr><td colspan="3">No rate plans configured.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              <div class="bulk-row mt-3 js-bulk-ratio-field" style="display:none;">
                <div class="bulk-field">
                  <label for="bulkRatio">Ratio (applies to selected rate plans)</label>
                  <input type="number" step="0.1" min="0.1" name="ratio" id="bulkRatio" class="form-control form-control-sm" value="1">
                </div>
              </div>
              <div class="bulk-row mt-3 js-bulk-increment-field" style="display:none;">
                <div class="bulk-field">
                  <label for="bulkIncrement">Increment (applies to selected rate plans)</label>
                  <input type="number" step="1" name="increment" id="bulkIncrement" class="form-control form-control-sm" value="0">
                </div>
              </div>
            </div>

            {{-- Restrictions (Rates) --}}
            <div class="bulk-panel" data-panel="restrictions_rates">
              @include('hotel.channel-manager.partials._bulk-restrictions-table', ['mode' => 'plan', 'items' => $ratePlans])
            </div>

            {{-- Restrictions (Inventory) --}}
            <div class="bulk-panel" data-panel="restrictions_inventory">
              @include('hotel.channel-manager.partials._bulk-restrictions-table', [
                'mode' => 'room',
                'items' => $rooms->map(fn ($r) => ['id' => $r->id, 'label' => $r->name])->values(),
              ])
            </div>

            <div class="mt-4">
              <button type="submit" class="btn btn-bulk-primary">Update</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var typeInput = document.getElementById('bulkUpdateType');
      var valueCol = document.querySelector('.js-bulk-value-col');
      var ratioField = document.querySelector('.js-bulk-ratio-field');
      var incrementField = document.querySelector('.js-bulk-increment-field');
      var planInputs = document.querySelectorAll('.js-bulk-plan-value');

      function setType(type) {
        typeInput.value = type;
        document.querySelectorAll('.bulk-panel').forEach(function (panel) {
          var panels = (panel.getAttribute('data-panel') || '').split(' ');
          panel.classList.toggle('is-active', panels.indexOf(type) !== -1);
        });

        if (valueCol) {
          valueCol.textContent = type === 'ratio' ? 'Ratio' : (type === 'increment' ? 'Increment' : 'Rates');
        }
        if (ratioField) ratioField.style.display = type === 'ratio' ? 'block' : 'none';
        if (incrementField) incrementField.style.display = type === 'increment' ? 'block' : 'none';
        planInputs.forEach(function (input) {
          input.style.display = (type === 'rate') ? '' : 'none';
          input.disabled = type !== 'rate';
        });
      }

      document.querySelectorAll('.js-bulk-type').forEach(function (radio) {
        radio.addEventListener('change', function () {
          if (this.checked) setType(this.value);
        });
      });

      document.querySelectorAll('.js-bulk-check-all').forEach(function (master) {
        master.addEventListener('change', function () {
          var group = master.getAttribute('data-target');
          document.querySelectorAll('.js-bulk-row-check[data-group="' + group + '"]').forEach(function (cb) {
            cb.checked = master.checked;
          });
        });
      });

      document.querySelectorAll('.js-bulk-weekday').forEach(function (cb) {
        cb.addEventListener('change', function () {
          if (cb.value !== 'all') return;
          document.querySelectorAll('.js-bulk-weekday').forEach(function (other) {
            if (other !== cb) other.checked = cb.checked;
          });
        });
      });

      document.querySelectorAll('.js-bulk-info').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (typeof swal === 'function') swal('Bulk Update', btn.getAttribute('data-text') || '', 'info');
        });
      });

      setType('inventory');
    })();
  </script>
@endpush
