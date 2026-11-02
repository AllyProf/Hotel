@extends('layouts.app')

{{--
  Update Rates grid — future interactivity & functionality (not implemented):

  Interactivity
  - Highlight dirty cells (rates/toggles changed since load) before Publish; warn on navigation if unsaved.
  - Excel-style keyboard: Tab/Enter to move between rate inputs; optional arrow keys across the grid.
  - Bulk tools: select date range + rooms/plans → apply % or fixed adjustment, copy from prior period, fill empty from base BAR.
  - Collapse/expand room groups; filter by room, meal plan, or occupancy type.
  - Sticky top row on vertical scroll when many rate plans; “jump to today” / weekend quick links.
  - Availability cell: tooltip with stopped channel names without opening modal (meta already in page).
  - Publish feedback: per-OTA sync status toast or badges on dates after save (push result from CM).

  New functionality
  - Separate local / international columns when plan pricing_mode is “both” (not single amount field).
  - Stay restrictions row: min/max length of stay, CTA/CTD, stop sell at plan level (not only inventory).
  - Dynamic rates: show rule preview or linked event when toggle is on (demand override from rateplan events).
  - Rate history / audit log for a date + plan; revert to previous published snapshot.
  - CSV import/export for the visible window; optional channel-specific export formats.
  - Occupancy % from real reservations (wire inventory occupancy row to analytics, not placeholder 0%).
  - Optional “compare” row: last year same date or competitor reference rate (read-only).
--}}

@section('title', 'Update Rates')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/hotel-update-rates.css') }}">
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

  <div class="row" id="updateRatesPage" data-rates-index-url="{{ $ratesIndexUrl }}">
    <div class="col-md-12">
      <div class="tile rates-page-tile">
        <form id="updateRatesForm" method="POST" action="{{ route('hotel.channel-manager.update-rates.store') }}">
          @csrf
          <input type="hidden" name="start_date" value="{{ $startDate }}">

          <div class="rates-page-toolbar">
            <a href="{{ $calendarViewUrl }}" class="btn btn-rates-secondary btn-sm">
              Calendar View
            </a>
            <button type="button" class="btn btn-rates-secondary btn-sm" id="resetRatesBtn">Reset</button>
            <button type="submit" class="btn btn-rates-publish btn-sm" id="publishRatesBtn">
              Publish Rates
              <span class="rates-dirty-badge d-none" id="ratesDirtyBadge" aria-live="polite"></span>
            </button>
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
                        <a href="{{ route('hotel.channel-manager.update-rates', ['start_date' => $prevStartDate]) }}"
                           class="nav-btn" title="Previous {{ $windowDays }} days">&laquo;</a>
                        <label class="date-label mb-0" for="startDatePicker">{{ $startDateLabel }}</label>
                        <input class="date-picker-hidden" type="date" id="startDatePicker" value="{{ $startDate }}">
                        <a href="{{ route('hotel.channel-manager.update-rates', ['start_date' => $nextStartDate]) }}"
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

                  <tr class="rates-row-white">
                    <td class="sticky-col">Available</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      @php $state = $grid['inventory']['isOpen'][$dateKey] ?? 'open'; @endphp
                      <td class="rates-available-cell js-availability-open" data-date="{{ $dateKey }}" title="Change availability">
                        <span class="rates-available-square {{ $state === 'closed' ? 'is-closed' : ($state === 'partial' ? 'is-partial' : '') }}"></span>
                      </td>
                    @endforeach
                  </tr>

                  <tr class="rates-row-white">
                    <td class="sticky-col">Available Rooms (Occupancy %)</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      <td title="Occupancy from active reservations vs total sellable rooms">
                        <span class="rates-occ-value">{{ $grid['inventory']['totals'][$dateKey] ?? 0 }}</span>
                        <span class="rates-occ-pct">({{ number_format($grid['inventory']['occupancy'][$dateKey] ?? 0, 0) }} %)</span>
                      </td>
                    @endforeach
                  </tr>

                  <tr class="rates-row-white rates-row-compare">
                    <td class="sticky-col">Compare · same date last year (avg.)</td>
                    @foreach($grid['dateKeys'] as $dateKey)
                      @php
                        $compare = $grid['compareLastYear'][$dateKey] ?? null;
                        $compareAmount = is_array($compare) ? ($compare['amount'] ?? null) : null;
                        $compareLabel = is_array($compare) ? ($compare['label'] ?? '') : '';
                      @endphp
                      <td class="rates-compare-cell" @if($compareLabel !== '') title="Average rate on {{ $compareLabel }}" @endif>
                        @if($compareAmount !== null)
                          <span class="rates-compare-value">{{ number_format($compareAmount, 0) }}</span>
                        @else
                          <span class="rates-compare-empty">—</span>
                        @endif
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
    $month = $calendarMonth;
  @endphp
  @include('hotel.channel-manager.partials.rates-modals')
  @endif
@endsection

@push('scripts')
  <script src="{{ asset('assets/js/hotel-update-rates-grid.js') }}" defer></script>
@endpush
