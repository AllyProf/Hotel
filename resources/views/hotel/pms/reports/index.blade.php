@extends('layouts.app')

@section('title', $ui['title'] ?? 'Reports')

@push('styles')
  <link rel="stylesheet" href="{{ asset('panel-assets/js/plugins/select2.min.css') }}">
  <style>
    :root {
      --rp-brand: #940000;
      --rp-brand-dark: #7a0000;
    }

    .select2-container { width: 100% !important; }

    .rp-page { background: #fff; }

    .rp-header {
      padding: 18px 20px 0;
    }

    .rp-header h3 {
      margin: 0 0 16px;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .rp-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 14px;
      padding: 0 20px 16px;
      border-bottom: 3px solid var(--rp-brand);
    }

    .rp-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .rp-field .form-control {
      min-height: 36px;
      font-size: 13px;
    }

    .rp-field--report {
      min-width: 240px;
    }

    .rp-field--filter-by {
      min-width: 140px;
    }

    .rp-field--filter-value {
      min-width: 180px;
    }

    .rp-field--filter-value.is-hidden {
      display: none;
    }

    .rp-field--date input {
      min-width: 150px;
    }

    .rp-field--guest input {
      min-width: 180px;
    }

    .btn-rp {
      background: var(--rp-brand) !important;
      border-color: var(--rp-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 18px;
      min-height: 36px;
    }

    .btn-rp:hover {
      background: var(--rp-brand-dark) !important;
      border-color: var(--rp-brand-dark) !important;
      color: #fff !important;
    }

    .btn-rp-outline {
      border-color: var(--rp-brand) !important;
      color: var(--rp-brand) !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 16px;
      min-height: 36px;
      text-decoration: none;
    }

    .btn-rp-outline:hover {
      background: #fef2f2 !important;
      color: var(--rp-brand-dark) !important;
      text-decoration: none;
    }

    .rp-actions {
      display: flex;
      justify-content: flex-end;
      padding: 12px 20px 0;
    }

    .rp-content {
      padding: 24px 20px 28px;
      min-height: 220px;
    }

    .rp-empty {
      text-align: center;
      color: #666;
      font-size: 18px;
      font-weight: 600;
      padding: 48px 16px;
    }

    .rp-table-wrap { overflow-x: auto; }

    .rp-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 960px;
    }

    .rp-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 10px 12px;
      border: 1px solid #2d3238;
      white-space: nowrap;
    }

    .rp-table tbody td {
      border: 1px solid #dee2e6;
      padding: 10px 12px;
      font-size: 13px;
      background: #fff;
    }

    .rp-table tbody tr:hover td { background: #fafafa; }

    .rp-report-title {
      font-size: 16px;
      font-weight: 700;
      color: #333;
      margin-bottom: 14px;
    }

    .select2-container--default .select2-results__group {
      font-weight: 700;
      color: #555;
      background: #f3f4f6;
    }

    .rp-field--month.is-hidden,
    .rp-field--date-range.is-hidden,
    .rp-toolbar-extra.is-hidden {
      display: none;
    }

    .rp-pl-table thead th:first-child {
      min-width: 220px;
    }

    .rp-pl-table tbody td:first-child {
      font-weight: 500;
    }

    .rp-pl-table tbody td.rp-pl-num {
      text-align: right;
      white-space: nowrap;
      font-variant-numeric: tabular-nums;
    }

    .rp-pl-table tr.rp-pl-section td {
      background: #e9ecef !important;
      font-weight: 700;
    }

    .rp-pl-table tr.rp-pl-subtotal td {
      background: #d6d8db !important;
      font-weight: 700;
    }

    .rp-pl-table tr.rp-pl-profit td {
      background: #e9ecef !important;
      font-weight: 700;
    }

    .rp-pl-table tr.rp-pl-section:hover td,
    .rp-pl-table tr.rp-pl-subtotal:hover td,
    .rp-pl-table tr.rp-pl-profit:hover td {
      background: inherit !important;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-file-text-o"></i> {{ $ui['title'] ?? 'Reports' }}</h1>
      <p>PMS reports with date range and booking filters</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Reports' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile rp-page">
        <div class="rp-header">
          <h3>{{ $ui['title'] ?? 'Reports' }}</h3>
        </div>

        <form method="GET" action="{{ route('hotel.reports.index') }}" id="rpFilterForm">
          <input type="hidden" name="submitted" value="1">

          <div class="rp-toolbar">
            <div class="rp-field rp-field--report">
              <label for="report">Select Report</label>
              <select class="form-control js-rp-report-select" id="report" name="report">
                @foreach($ui['categories'] ?? [] as $category)
                  <optgroup label="{{ $category['label'] }}">
                    @foreach($category['reports'] ?? [] as $report)
                      <option value="{{ $report['key'] }}" {{ $filters['report'] === $report['key'] ? 'selected' : '' }}>
                        {{ $report['label'] }}
                      </option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
            </div>

            <div class="rp-field rp-field--month {{ ($filters['report'] ?? '') === 'monthly_pl' ? '' : 'is-hidden' }}" id="rpMonthRangeWrap">
              <label for="from_month">From Month:</label>
              <input type="month" class="form-control" id="from_month" name="from_month"
                value="{{ $filters['from_month'] ?? now()->subMonths(5)->format('Y-m') }}">
            </div>

            <div class="rp-field rp-field--month {{ ($filters['report'] ?? '') === 'monthly_pl' ? '' : 'is-hidden' }}" id="rpMonthRangeWrapTo">
              <label for="to_month">To Month:</label>
              <input type="month" class="form-control" id="to_month" name="to_month"
                value="{{ $filters['to_month'] ?? now()->format('Y-m') }}">
            </div>

            <div class="rp-field rp-field--date rp-field--date-range {{ ($filters['report'] ?? '') === 'monthly_pl' ? 'is-hidden' : '' }}" id="rpDateRangeWrap">
              <label for="from_date">From Date:</label>
              <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
            </div>

            <div class="rp-field rp-field--date rp-field--date-range {{ ($filters['report'] ?? '') === 'monthly_pl' ? 'is-hidden' : '' }}" id="rpDateRangeWrapTo">
              <label for="to_date">To Date:</label>
              <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
            </div>

            <div class="rp-field">
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-rp">Submit</button>
            </div>

            <div class="rp-field rp-field--filter-by rp-toolbar-extra {{ ($filters['report'] ?? '') === 'monthly_pl' ? 'is-hidden' : '' }}" id="rpExtraFilters">
              <label for="filter_by">Filter By:</label>
              <select class="form-control js-rp-filter-by" id="filter_by" name="filter_by">
                <option value="" {{ $filters['filter_by'] === '' ? 'selected' : '' }}>All</option>
                <option value="source" {{ $filters['filter_by'] === 'source' ? 'selected' : '' }}>Source</option>
                <option value="segment" {{ $filters['filter_by'] === 'segment' ? 'selected' : '' }}>Segment</option>
                <option value="room_type" {{ $filters['filter_by'] === 'room_type' ? 'selected' : '' }}>Room Type</option>
                <option value="status" {{ $filters['filter_by'] === 'status' ? 'selected' : '' }}>Status</option>
                <option value="rate_plan" {{ $filters['filter_by'] === 'rate_plan' ? 'selected' : '' }}>Rate Plan</option>
              </select>
            </div>

            <div class="rp-field rp-field--filter-value rp-toolbar-extra {{ ($filters['report'] ?? '') === 'monthly_pl' ? 'is-hidden' : ($filters['filter_by'] === '' ? 'is-hidden' : '') }}" id="rpFilterValueWrap">
              <label for="filter_value" class="js-rp-filter-value-label">Value</label>
              <select class="form-control js-rp-filter-value" id="filter_value" name="filter_value">
                <option value="">All</option>
              </select>
            </div>

            <div class="rp-field rp-field--guest rp-toolbar-extra {{ ($filters['report'] ?? '') === 'monthly_pl' ? 'is-hidden' : '' }}">
              <label for="guest_search">Search Guest:</label>
              <input type="search" class="form-control" id="guest_search" name="guest_search"
                value="{{ $filters['guest_search'] }}" placeholder="Enter Guest Name">
            </div>
          </div>
        </form>

        @if($submitted && ($result['available'] ?? false))
          <div class="rp-actions">
            <a class="btn btn-rp-outline" href="{{ route('hotel.reports.export', request()->query()) }}">
              <i class="fa fa-download"></i> Download Excel
            </a>
          </div>
        @endif

        <div class="rp-content">
          @if(! $submitted)
            <div class="rp-empty">Select a report and click Submit to view data.</div>
          @elseif(! ($result['available'] ?? false))
            <div class="rp-empty">This report is not available yet. It requires additional modules or data that are not configured for your property.</div>
          @else
            <div class="rp-report-title">
              {{ $result['title'] }}
              @if(empty($result['summary']['monthly_pl']))
                <span class="text-muted" style="font-size:13px;font-weight:500;">· {{ count($result['rows']) }} {{ Str::plural('record', count($result['rows'])) }}</span>
              @endif
            </div>

            @if(! empty($result['summary']['monthly_pl']))
              @php
                $pl = $result['summary']['monthly_pl'];
                $plColumns = $pl['columns'] ?? [];
                $plMonthKeys = $pl['month_keys'] ?? [];
                $plMonthLabels = array_slice($plColumns, 1, max(0, count($plColumns) - 2));
              @endphp
              <div class="rp-table-wrap">
                <table class="rp-table rp-pl-table">
                  <thead>
                    <tr>
                      @foreach($plColumns as $column)
                        <th>{{ $column === '' ? ' ' : $column }}</th>
                      @endforeach
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($pl['sections'] ?? [] as $section)
                      @php
                        $kind = $section['kind'] ?? 'data';
                        $rowClass = match ($kind) {
                          'section_title' => 'rp-pl-section',
                          'subtotal' => 'rp-pl-subtotal',
                          'profit' => 'rp-pl-profit',
                          default => '',
                        };
                        $values = $section['values'] ?? [];
                        $rowTotal = $values !== [] ? array_sum($values) : 0;
                      @endphp
                      <tr class="{{ $rowClass }}">
                        <td>{{ $section['label'] ?? '' }}</td>
                        @if($kind === 'section_title')
                          @foreach($plMonthLabels as $monthLabel)
                            <td class="rp-pl-num"></td>
                          @endforeach
                          <td class="rp-pl-num"></td>
                        @else
                          @foreach($plMonthKeys as $monthKey)
                            <td class="rp-pl-num">{{ number_format($values[$monthKey] ?? 0, 0, '.', ',') }}</td>
                          @endforeach
                          <td class="rp-pl-num">{{ number_format($rowTotal, 0, '.', ',') }}</td>
                        @endif
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @elseif(! empty($result['summary']['revenue_totals']))
              <div class="row mb-4">
                @foreach($result['summary']['revenue_totals'] as $block)
                  @php
                    $colClass = count($result['summary']['revenue_totals']) > 1 ? 'col-lg-6' : 'col-12';
                  @endphp
                  <div class="{{ $colClass }} mb-3 mb-lg-0">
                    <div class="table-responsive">
                      <table class="table table-hover table-bordered mb-0">
                        <thead class="thead-light">
                          <tr>
                            <th colspan="2">{{ $block['currency'] }} revenue totals</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($block['items'] ?? [] as $item)
                            <tr>
                              <th scope="row" class="text-muted font-weight-normal" style="width:42%">{{ $item['label'] ?? '' }}</th>
                              <td class="font-weight-bold">{{ $item['value'] ?? '—' }}</td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </div>
                @endforeach
              </div>
            @endif

            @if(empty($result['summary']['monthly_pl']))
            <div class="rp-table-wrap">
              <table class="rp-table">
                <thead>
                  <tr>
                    @foreach($result['columns'] as $column)
                      <th>{{ $column }}</th>
                    @endforeach
                  </tr>
                </thead>
                <tbody>
                  @forelse($result['rows'] as $row)
                    <tr>
                      @foreach($result['columns'] as $column)
                        <td>{{ $row[$column] ?? '—' }}</td>
                      @endforeach
                    </tr>
                  @empty
                    <tr>
                      <td colspan="{{ count($result['columns']) }}" class="text-center text-muted py-4">
                        No records found for the selected filters.
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script type="application/json" id="rp-filter-options">@json($filterOptions)</script>
  <script>
    (function () {
      var filterOptions = {};
      try {
        filterOptions = JSON.parse(document.getElementById('rp-filter-options').textContent || '{}');
      } catch (e) {
        filterOptions = {};
      }

      var filterLabels = {
        source: 'Source',
        segment: 'Segment',
        room_type: 'Room Type',
        status: 'Status',
        rate_plan: 'Rate Plan'
      };

      var optionKeys = {
        source: 'sources',
        segment: 'segments',
        room_type: 'room_types',
        status: 'statuses',
        rate_plan: 'rate_plans'
      };

      var currentFilterBy = @json($filters['filter_by'] ?? '');
      var currentFilterValue = @json($filters['filter_value'] ?? '');

      jQuery(function ($) {
        $('.js-rp-report-select').select2({
          width: '100%',
          placeholder: 'Select Report',
          allowClear: false,
        });

        var $filterBy = $('#filter_by');
        var $filterValue = $('#filter_value');
        var $filterWrap = $('#rpFilterValueWrap');
        var $filterLabel = $('.js-rp-filter-value-label');

        function populateFilterValue(type, selected) {
          $filterValue.empty().append('<option value="">All</option>');

          if (!type || !optionKeys[type]) {
            return;
          }

          (filterOptions[optionKeys[type]] || []).forEach(function (item) {
            var option = $('<option></option>').attr('value', item.value).text(item.label);
            if (String(selected) === String(item.value)) {
              option.prop('selected', true);
            }
            $filterValue.append(option);
          });
        }

        function syncFilterValueField() {
          var type = $filterBy.val();

          if (!type) {
            $filterWrap.addClass('is-hidden');
            $filterValue.val('');
            return;
          }

          $filterWrap.removeClass('is-hidden');
          $filterLabel.text(filterLabels[type] || 'Value');
          populateFilterValue(type, $filterValue.val() || currentFilterValue);
        }

        $filterBy.on('change', function () {
          currentFilterValue = '';
          populateFilterValue($filterBy.val(), '');
          syncFilterValueField();
        });

        populateFilterValue(currentFilterBy, currentFilterValue);
        syncFilterValueField();

        var $report = $('#report');

        function syncMonthlyPlToolbar() {
          var isPl = $report.val() === 'monthly_pl';
          $('#rpMonthRangeWrap, #rpMonthRangeWrapTo').toggleClass('is-hidden', !isPl);
          $('#rpDateRangeWrap, #rpDateRangeWrapTo').toggleClass('is-hidden', isPl);
          $('.rp-toolbar-extra').toggleClass('is-hidden', isPl);
        }

        $report.on('change select2:select', syncMonthlyPlToolbar);
        syncMonthlyPlToolbar();
      });
    })();
  </script>
@endpush
