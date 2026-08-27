@extends('layouts.app')

@section('title', 'Update Rates')

@push('styles')
  <style>
    .rates-page-tile {
      padding: 0;
      overflow: hidden;
      background: #fff;
    }

    .rates-page-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 16px;
      border-bottom: 1px solid #dde1e6;
      background: #fff;
    }

    .btn-rates-secondary {
      background: #fff;
      border: 1px solid #c5ccd3;
      color: #333;
      font-weight: 600;
      font-size: 13px;
      padding: 6px 14px;
      border-radius: 3px;
    }

    .btn-rates-secondary:hover {
      background: #f5f6f7;
      color: #222;
    }

    .btn-rates-publish {
      background: #1877f2 !important;
      border: 1px solid #1877f2 !important;
      color: #fff !important;
      font-weight: 700;
      font-size: 13px;
      padding: 6px 16px;
      border-radius: 3px;
    }

    .btn-rates-publish:hover {
      background: #166fe0 !important;
      border-color: #166fe0 !important;
      color: #fff !important;
    }

    /* Scroll area — dates move left, label column stays fixed */
    .rates-grid-scroll {
      overflow-x: auto;
      overflow-y: hidden;
      width: 100%;
      -webkit-overflow-scrolling: touch;
    }

    .rates-grid-scroll::-webkit-scrollbar {
      height: 10px;
    }

    .rates-grid-scroll::-webkit-scrollbar-thumb {
      background: #8a9199;
      border-radius: 5px;
    }

    .rates-grid-scroll::-webkit-scrollbar-track {
      background: #eef0f2;
    }

    .rates-grid-table {
      width: max-content;
      min-width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin: 0;
      table-layout: fixed;
    }

    .rates-grid-table th,
    .rates-grid-table td {
      border: 1px solid #c8cdd3;
      text-align: center;
      vertical-align: middle;
      padding: 10px 8px;
      font-size: 13px;
      line-height: 1.25;
      min-width: 92px;
      max-width: 92px;
      box-sizing: border-box;
    }

    /* Sticky left column */
    .rates-grid-table .sticky-col {
      position: sticky;
      left: 0;
      z-index: 5;
      min-width: 240px;
      max-width: 240px;
      width: 240px;
      text-align: left;
      padding-left: 14px;
      padding-right: 12px;
      box-shadow: 2px 0 4px rgba(0, 0, 0, 0.06);
    }

    /* Header row — date columns dark */
    .rates-grid-table thead th.date-col {
      background: #4f5862;
      color: #fff;
      font-weight: 700;
      border-color: #434b54;
      z-index: 1;
    }

    .rates-grid-table thead .sticky-col {
      background: #fff;
      z-index: 6;
      vertical-align: middle;
      border-right: 1px solid #c8cdd3;
    }

    .rates-date-nav {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .rates-date-nav .nav-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border: 1px solid #c5ccd3;
      background: #fff;
      color: #444;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      line-height: 1;
      border-radius: 2px;
    }

    .rates-date-nav .nav-btn:hover {
      background: #f3f4f6;
      color: #111;
      text-decoration: none;
    }

    .rates-date-nav .date-label {
      font-weight: 700;
      font-size: 13px;
      color: #222;
      min-width: 72px;
      text-align: center;
      cursor: pointer;
    }

    .rates-date-nav .date-picker-hidden {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
      pointer-events: none;
    }

    .rates-date-head-icon {
      font-size: 11px;
      opacity: 0.85;
      margin-right: 3px;
    }

    .rates-date-head-line {
      display: block;
      font-size: 12px;
      font-weight: 700;
    }

    /* Row types */
    .rates-row-dark td {
      background: #4f5862;
      color: #fff;
      border-color: #434b54;
      font-weight: 700;
    }

    .rates-row-dark td.sticky-col {
      background: #4f5862;
      color: #fff;
      z-index: 4;
    }

    .rates-row-white td {
      background: #fff;
      color: #222;
    }

    .rates-row-white td.sticky-col {
      background: #fff;
      font-weight: 700;
      z-index: 3;
    }

    .rates-row-room td.sticky-col i {
      margin-right: 8px;
      font-size: 14px;
    }

    .rates-row-plan td.sticky-col {
      font-weight: 600;
      padding-left: 22px;
    }

    /* Available green square */
    .rates-available-square {
      display: inline-block;
      width: 14px;
      height: 14px;
      background: #2ecc71;
      border-radius: 2px;
    }

    .rates-available-square.is-closed { background: #e74c3c; }
    .rates-available-square.is-partial { background: #f1c40f; }

    /* Occupancy cell */
    .rates-occ-value {
      display: block;
      font-weight: 700;
      font-size: 14px;
      line-height: 1.2;
    }

    .rates-occ-pct {
      display: block;
      font-size: 12px;
      font-style: italic;
      color: #555;
      line-height: 1.2;
      margin-top: 2px;
    }

    /* Toggle switch */
    .rates-toggle {
      position: relative;
      display: inline-block;
      width: 38px;
      height: 20px;
      margin: 0;
      vertical-align: middle;
    }

    .rates-toggle input {
      opacity: 0;
      width: 0;
      height: 0;
      position: absolute;
    }

    .rates-toggle-track {
      position: absolute;
      inset: 0;
      background: #9aa3ab;
      border-radius: 20px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .rates-toggle-track:before {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      left: 2px;
      top: 2px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.2s;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .rates-toggle input:checked + .rates-toggle-track {
      background: #940000;
    }

    .rates-toggle input:checked + .rates-toggle-track:before {
      transform: translateX(18px);
    }

    /* Rate input */
    .rates-plan-input {
      width: 58px;
      max-width: 100%;
      margin: 0 auto;
      text-align: center;
      font-weight: 600;
      font-size: 13px;
      border: 1px solid #b8bec5;
      border-radius: 2px;
      padding: 5px 2px;
      background: #fff;
      color: #222;
      -moz-appearance: textfield;
    }

    .rates-plan-input::-webkit-outer-spin-button,
    .rates-plan-input::-webkit-inner-spin-button {
      opacity: 1;
    }

    .rates-plan-input:focus {
      border-color: #1877f2;
      outline: none;
      box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.15);
    }

    .rates-empty {
      padding: 48px 20px;
      text-align: center;
      color: #666;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-dollar"></i> Channel Manager <small class="text-muted">Update Rates</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">Channel Manager</a></li>
      <li class="breadcrumb-item">Update Rates</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile rates-page-tile">
        <form id="updateRatesForm" method="POST" action="{{ route('hotel.channel-manager.update-rates.store') }}">
          @csrf
          <input type="hidden" name="start_date" value="{{ $startDate }}">

          <div class="rates-page-toolbar">
            <a href="{{ route('hotel.channel-manager.update-rates', ['view' => 'calendar', 'month' => \Carbon\Carbon::parse($startDate)->format('Y-m')]) }}" class="btn btn-rates-secondary btn-sm">
              Calendar View
            </a>
            <button type="button" class="btn btn-rates-secondary btn-sm" id="resetRatesBtn">Reset</button>
            <button type="submit" class="btn btn-rates-publish btn-sm">Publish Rates</button>
          </div>

          @if(empty($grid['roomGroups']))
            <div class="rates-empty">
              <p class="mb-2">No rate plans yet.</p>
              <a href="{{ route('hotel.rooms.create') }}" class="btn btn-primary btn-sm mr-2">Add a room</a>
              <a href="{{ route('hotel.settings.index', ['tab' => 'rateplan']) }}" class="btn btn-outline-primary btn-sm">Set base prices</a>
            </div>
          @else
            <div class="rates-grid-scroll" id="ratesGridScroll">
              <table class="rates-grid-table" id="updateRatesTable">
                <thead>
                  <tr>
                    <th class="sticky-col">
                      <div class="rates-date-nav">
                        <a href="{{ route('hotel.channel-manager.update-rates', ['start_date' => \Carbon\Carbon::parse($startDate)->subDays($windowDays)->format('Y-m-d')]) }}"
                           class="nav-btn" title="Previous {{ $windowDays }} days">&laquo;</a>
                        <label class="date-label mb-0" for="startDatePicker">{{ $startDateLabel }}</label>
                        <input class="date-picker-hidden" type="date" id="startDatePicker" value="{{ $startDate }}">
                        <a href="{{ route('hotel.channel-manager.update-rates', ['start_date' => \Carbon\Carbon::parse($startDate)->addDays($windowDays)->format('Y-m-d')]) }}"
                           class="nav-btn" title="Next {{ $windowDays }} days">&raquo;</a>
                      </div>
                    </th>
                    @foreach($grid['dates'] as $date)
                      <th class="date-col rates-date-col-clickable js-event-open"
                          data-date="{{ $date->format('Y-m-d') }}"
                          data-label="{{ $date->format('d M') }}"
                          title="Create event">
                        <i class="fa fa-calendar rates-date-head-icon"></i>
                        <span class="rates-date-head-line">{{ $date->format('D') }}</span>
                        <span class="rates-date-head-line">{{ $date->format('d M') }}</span>
                      </th>
                    @endforeach
                  </tr>
                </thead>
                <tbody>
                  {{-- Dynamic Rates — dark row + toggles --}}
                  <tr class="rates-row-dark">
                    <td class="sticky-col">Dynamic Rates</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      <td>
                        <label class="rates-toggle">
                          <input type="checkbox" name="dynamic_rates[{{ $dateKey }}]" value="1"
                            class="js-dynamic-toggle"
                            {{ ($grid['dynamicRates'][$dateKey] ?? false) ? 'checked' : '' }}
                            data-original="{{ ($grid['dynamicRates'][$dateKey] ?? false) ? '1' : '0' }}">
                          <span class="rates-toggle-track"></span>
                        </label>
                      </td>
                    @endforeach
                  </tr>

                  {{-- Available — green squares --}}
                  <tr class="rates-row-white">
                    <td class="sticky-col">Available</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      @php $state = $grid['inventory']['isOpen'][$dateKey] ?? 'open'; @endphp
                      <td class="rates-available-cell js-availability-open" data-date="{{ $dateKey }}" title="Change availability">
                        <span class="rates-available-square {{ $state === 'closed' ? 'is-closed' : ($state === 'partial' ? 'is-partial' : '') }}"></span>
                      </td>
                    @endforeach
                  </tr>

                  {{-- Available Rooms (Occupancy %) --}}
                  <tr class="rates-row-white">
                    <td class="sticky-col">Available Rooms (Occupancy %)</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      <td>
                        <span class="rates-occ-value">{{ $grid['inventory']['totals'][$dateKey] ?? 0 }}</span>
                        <span class="rates-occ-pct">({{ number_format($grid['inventory']['occupancy'][$dateKey] ?? 0, 0) }} %)</span>
                      </td>
                    @endforeach
                  </tr>

                  @foreach($grid['roomGroups'] as $roomGroup)
                    <tr class="rates-row-dark rates-row-room">
                      <td class="sticky-col"><i class="fa fa-bed"></i>{{ $roomGroup['name'] }}</td>
                      @foreach($grid['dateKeys'] as $dateKey)
                        <td>{{ $roomGroup['counts'][$dateKey] ?? 0 }}</td>
                      @endforeach
                    </tr>

                    @foreach($roomGroup['rate_plans'] as $plan)
                      <tr class="rates-row-white rates-row-plan">
                        <td class="sticky-col">{{ $plan['plan_label'] }}</td>
                        @foreach($grid['dateKeys'] as $dateKey)
                          @php $amount = $plan['display_rates'][$dateKey] ?? null; @endphp
                          <td>
                            <input type="number" step="1" min="0"
                              class="rates-plan-input js-rate-input"
                              name="rates[{{ $plan['id'] }}][{{ $dateKey }}][amount]"
                              value="{{ $amount !== null ? (int) $amount : '' }}"
                              data-original="{{ $amount !== null ? (int) $amount : '' }}"
                              placeholder="0">
                          </td>
                        @endforeach
                      </tr>
                    @endforeach
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </form>
      </div>
    </div>
  </div>

  @if(!empty($grid['roomGroups']))
  @php
    $availabilityMeta = $grid['availabilityMeta'] ?? [];
    $returnView = 'grid';
    $month = \Carbon\Carbon::parse($startDate)->format('Y-m');
  @endphp
  @include('hotel.channel-manager.partials.rates-modals')
  @endif
@endsection

@push('scripts')
  <script>
    (function () {
      var datePicker = document.getElementById('startDatePicker');
      var dateLabel = document.querySelector('.rates-date-nav .date-label');
      var resetBtn = document.getElementById('resetRatesBtn');
      var table = document.getElementById('updateRatesTable');
      var scrollEl = document.getElementById('ratesGridScroll');

      if (dateLabel && datePicker) {
        dateLabel.addEventListener('click', function () {
          if (datePicker.showPicker) {
            datePicker.showPicker();
          } else {
            datePicker.click();
          }
        });
        datePicker.addEventListener('change', function () {
          if (!this.value) return;
          window.location.href = '{{ route('hotel.channel-manager.update-rates') }}?start_date=' + encodeURIComponent(this.value);
        });
      }

      if (resetBtn && table) {
        resetBtn.addEventListener('click', function () {
          table.querySelectorAll('.js-rate-input').forEach(function (input) {
            input.value = input.getAttribute('data-original') || '';
          });
          table.querySelectorAll('.js-dynamic-toggle').forEach(function (cb) {
            cb.checked = cb.getAttribute('data-original') === '1';
          });
        });
      }

      // Keep scroll position natural — grid scrolls dates to the left under fixed labels
      if (scrollEl) {
        scrollEl.setAttribute('tabindex', '0');
      }
    })();
  </script>
@endpush
