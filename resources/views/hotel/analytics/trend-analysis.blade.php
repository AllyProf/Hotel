@extends('layouts.app')

@section('title', ($ui['parent_label'] ?? 'Analytics').' - '.($ui['title'] ?? 'Trend Analysis'))

@push('styles')
  <style>
    :root {
      --ta-brand: #940000;
      --ta-brand-dark: #7a0000;
    }

    .ta-page-title {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 10px;
      padding: 18px 20px 0;
    }

    .ta-page-title h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .ta-page-title span {
      font-size: 18px;
      color: #888;
      font-weight: 400;
    }

    .ta-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      padding: 14px 20px 16px;
      border-bottom: 3px solid var(--ta-brand);
    }

    .ta-view-toggle .btn {
      font-size: 13px;
      font-weight: 600;
      padding: 7px 14px;
    }

    .ta-view-toggle .btn.is-active {
      background: var(--ta-brand) !important;
      border-color: var(--ta-brand) !important;
      color: #fff !important;
    }

    .ta-view-toggle .btn:not(.is-active) {
      background: #5b9bd5 !important;
      border-color: #5b9bd5 !important;
      color: #fff !important;
    }

    .ta-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .ta-field .form-control {
      min-height: 36px;
      font-size: 13px;
    }

    .ta-actions {
      margin-left: auto;
      display: flex;
      gap: 10px;
    }

    .btn-ta {
      background: var(--ta-brand) !important;
      border-color: var(--ta-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 16px;
      min-height: 36px;
    }

    .btn-ta:hover {
      background: var(--ta-brand-dark) !important;
      border-color: var(--ta-brand-dark) !important;
      color: #fff !important;
    }

    .ta-chart-tile {
      margin-bottom: 20px;
      min-height: 340px;
    }

    .ta-chart-title {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 10px;
      color: #333;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .ta-badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 3px;
      background: #28a745;
      color: #fff;
    }

    .ta-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 8px;
      font-size: 12px;
      font-weight: 600;
    }

    .ta-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .ta-legend-swatch {
      width: 24px;
      height: 12px;
      border-radius: 2px;
      display: inline-block;
    }

    .ta-chart-canvas {
      position: relative;
      height: 250px;
    }

    .ta-chart-canvas canvas {
      width: 100% !important;
      height: 100% !important;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-bar-chart"></i> {{ $ui['parent_label'] ?? 'Analytics' }}</h1>
      <p>{{ $ui['title'] ?? 'Trend Analysis' }} booking patterns and trends</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item">{{ $ui['parent_label'] ?? 'Analytics' }}</li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Trend Analysis' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="ta-page-title">
          <h3>{{ $ui['parent_label'] ?? 'Analytics' }}</h3>
          <span>{{ $ui['title'] ?? 'Trend Analysis' }}</span>
        </div>

        <form method="GET" action="{{ route('hotel.analytics.trend-analysis') }}" id="taFilterForm">
          <input type="hidden" name="view" id="taViewInput" value="{{ $filters['view'] }}">

          <div class="ta-toolbar">
            <div class="ta-view-toggle btn-group" role="group">
              @foreach($ui['views'] ?? [] as $viewKey => $viewLabel)
                <button type="button"
                  class="btn btn-sm js-ta-view {{ $filters['view'] === $viewKey ? 'is-active' : '' }}"
                  data-view="{{ $viewKey }}">{{ $viewLabel }}</button>
              @endforeach
            </div>

            <div class="ta-field">
              <label for="from_date">From:</label>
              <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
            </div>

            <div class="ta-field">
              <label for="to_date">To:</label>
              <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
            </div>

            <div class="ta-field">
              <label for="filter_by">Filter:</label>
              <select class="form-control" id="filter_by" name="filter_by">
                @foreach($ui['filter_options'] ?? [] as $key => $label)
                  <option value="{{ $key }}" {{ $filters['filter_by'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="ta-actions">
              <button type="submit" class="btn btn-ta js-ta-report" data-view="weekly">Weekly Report</button>
              <button type="submit" class="btn btn-ta js-ta-report" data-view="monthly">Monthly Report</button>
            </div>
          </div>
        </form>

        <div class="tile-body">
          <div class="row">
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Lead time <span class="ta-badge">Short</span></div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taLeadTimeChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Length of Stay</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taLengthOfStayChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Occupancy by day of week</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:#97BBCD;"></span>
                    Occupancy
                  </span>
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,0.55);"></span>
                    Bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taDowChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Last minute bookings</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taLastMinuteChart"></canvas></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Room nights by Occupancy</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taOccupancyGuestsChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Room nights by Room Type</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taRoomTypeChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Cancellation Percentages</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of cancelled bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taCancellationChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Rates</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    %age of bookings
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taRatesChart"></canvas></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Historical Trends</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:#940000;"></span>
                    closing occupancy
                  </span>
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:#28a745;"></span>
                    inventory sold
                  </span>
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:#FDB45C;"></span>
                    total rooms
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taHistoricalChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Future Trends</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:#9b59b6;"></span>
                    future occupancy
                  </span>
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:#FDB45C;"></span>
                    total rooms
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taFutureChart"></canvas></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Meal Plan (overall)</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    % of Meal Plan
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taMealPlanChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Meal Plan (OTA-wise)</div>
                <div class="ta-chart-canvas"><canvas id="taMealPlanOtaChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Payment Mode (overall)</div>
                <div class="ta-legend">
                  <span class="ta-legend-item">
                    <span class="ta-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    % of Payment
                  </span>
                </div>
                <div class="ta-chart-canvas"><canvas id="taPaymentChart"></canvas></div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <div class="tile ta-chart-tile">
                <div class="ta-chart-title">Payment Mode (OTA-wise)</div>
                <div class="ta-chart-canvas"><canvas id="taPaymentOtaChart"></canvas></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/chart.js') }}"></script>
  <script type="application/json" id="ta-report-data">@json($report)</script>
  <script>
    (function () {
      var report = {};
      try {
        report = JSON.parse(document.getElementById('ta-report-data').textContent || '{}');
      } catch (e) {
        report = {};
      }

      var palette = [
        'rgba(151,187,205,1)',
        '#97BBCD',
        '#940000',
        '#28a745',
        '#FDB45C',
        '#9b59b6',
        '#46BFBD',
        '#949FB1'
      ];

      function barDataset(label, data, color, index) {
        var fill = color || palette[index % palette.length];
        return {
          label: label,
          fillColor: fill,
          strokeColor: fill,
          highlightFill: fill,
          highlightStroke: fill,
          data: data || []
        };
      }

      function lineDataset(label, data, color, index) {
        var stroke = color || palette[index % palette.length];
        return {
          label: label,
          fillColor: 'rgba(0,0,0,0)',
          strokeColor: stroke,
          pointColor: stroke,
          pointStrokeColor: '#fff',
          pointHighlightFill: '#fff',
          pointHighlightStroke: stroke,
          data: data || []
        };
      }

      function maxValue(list, floor) {
        var max = Math.max.apply(null, (list || []).concat([floor || 0]));
        return max <= 0 ? (floor || 1) : max;
      }

      function renderBar(canvasId, labels, datasets, yMax) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas.getContext('2d')).Bar({
          labels: labels || [],
          datasets: datasets || []
        }, {
          scaleOverride: true,
          scaleSteps: 10,
          scaleStepWidth: yMax / 10,
          scaleStartValue: 0
        });
      }

      function renderLine(canvasId, labels, datasets, yMax) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas.getContext('2d')).Line({
          labels: labels || [],
          datasets: datasets || []
        }, {
          scaleOverride: true,
          scaleSteps: 10,
          scaleStepWidth: yMax / 10,
          scaleStartValue: 0
        });
      }

      function singlePercentBar(canvasId, block) {
        renderBar(canvasId, block.labels || [], [
          barDataset('%age of bookings', block.values || [], 'rgba(151,187,205,1)', 0)
        ], 1);
      }

      singlePercentBar('taLeadTimeChart', report.lead_time || {});
      singlePercentBar('taLengthOfStayChart', report.length_of_stay || {});
      singlePercentBar('taLastMinuteChart', report.last_minute || {});
      singlePercentBar('taOccupancyGuestsChart', report.occupancy_guests || {});
      singlePercentBar('taRoomTypeChart', report.room_types || {});
      singlePercentBar('taRatesChart', report.rates || {});
      singlePercentBar('taMealPlanChart', report.meal_plan || {});
      singlePercentBar('taPaymentChart', report.payment_mode || {});

      var dow = report.dow_occupancy || {};
      var dowMax = maxValue((dow.occupancy || []).concat(dow.bookings || []), 1);
      renderBar('taDowChart', dow.labels || [], [
        barDataset('Occupancy', dow.occupancy || [], '#97BBCD', 0),
        barDataset('Bookings', dow.bookings || [], 'rgba(151,187,205,0.55)', 1)
      ], dowMax);

      renderBar('taCancellationChart', (report.cancellations || {}).labels || [], [
        barDataset('%age of cancelled bookings', (report.cancellations || {}).values || [], 'rgba(151,187,205,1)', 0)
      ], 50);

      function renderOtaBar(canvasId, block) {
        var datasets = (block.datasets || []).map(function (row, index) {
          return barDataset(row.name, row.data, palette[index + 1], index + 1);
        });

        if (!datasets.length) {
          datasets = [barDataset('—', (block.labels || []).map(function () { return 0; }), 'rgba(151,187,205,1)', 0)];
        }

        renderBar(canvasId, block.labels || ['—'], datasets, 1);
      }

      renderOtaBar('taMealPlanOtaChart', report.meal_plan_ota || {});
      renderOtaBar('taPaymentOtaChart', report.payment_mode_ota || {});

      var historical = report.historical || {};
      var historicalMax = maxValue(
        (historical.closing_occupancy || []).concat(historical.inventory_sold || []).concat(historical.total_rooms || []),
        5
      );
      renderLine('taHistoricalChart', historical.labels || [], [
        lineDataset('closing occupancy', historical.closing_occupancy || [], '#940000', 0),
        lineDataset('inventory sold', historical.inventory_sold || [], '#28a745', 1),
        lineDataset('total rooms', historical.total_rooms || [], '#FDB45C', 2)
      ], historicalMax);

      var future = report.future || {};
      var futureMax = maxValue(
        (future.future_occupancy || []).concat(future.total_rooms || []),
        5
      );
      renderLine('taFutureChart', future.labels || [], [
        lineDataset('future occupancy', future.future_occupancy || [], '#9b59b6', 0),
        lineDataset('total rooms', future.total_rooms || [], '#FDB45C', 1)
      ], futureMax);

      var form = document.getElementById('taFilterForm');
      var viewInput = document.getElementById('taViewInput');

      document.querySelectorAll('.js-ta-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!viewInput || !form) return;
          viewInput.value = btn.getAttribute('data-view') || 'monthly';
          form.submit();
        });
      });

      document.querySelectorAll('.js-ta-report').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!viewInput) return;
          viewInput.value = btn.getAttribute('data-view') || 'monthly';
        });
      });
    })();
  </script>
@endpush
