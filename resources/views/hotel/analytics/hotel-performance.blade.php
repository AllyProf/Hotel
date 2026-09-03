@extends('layouts.app')

@section('title', ($ui['parent_label'] ?? 'Analytics').' - '.($ui['title'] ?? 'Hotel Performance'))

@push('styles')
  <style>
    :root {
      --an-brand: #940000;
      --an-brand-dark: #7a0000;
    }

    .an-page-title {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 10px;
      padding: 18px 20px 0;
    }

    .an-page-title h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .an-page-title span {
      font-size: 18px;
      color: #888;
      font-weight: 400;
    }

    .an-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      padding: 14px 20px 16px;
      border-bottom: 3px solid var(--an-brand);
    }

    .an-view-toggle .btn {
      font-size: 13px;
      font-weight: 600;
      padding: 7px 14px;
    }

    .an-view-toggle .btn.is-active {
      background: var(--an-brand) !important;
      border-color: var(--an-brand) !important;
      color: #fff !important;
    }

    .an-view-toggle .btn:not(.is-active) {
      background: #5b9bd5 !important;
      border-color: #5b9bd5 !important;
      color: #fff !important;
    }

    .an-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .an-field .form-control {
      min-height: 36px;
      font-size: 13px;
    }

    .an-actions {
      margin-left: auto;
      display: flex;
      gap: 10px;
    }

    .btn-an {
      background: var(--an-brand) !important;
      border-color: var(--an-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 16px;
      min-height: 36px;
    }

    .btn-an:hover {
      background: var(--an-brand-dark) !important;
      border-color: var(--an-brand-dark) !important;
      color: #fff !important;
    }

    .an-section-title {
      font-size: 18px;
      font-weight: 400;
      color: #666;
      margin: 18px 0 12px;
      padding: 0 4px;
    }

    .an-chart-tile {
      margin-bottom: 20px;
    }

    .an-chart-tile .tile-title {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 12px;
      color: #333;
    }

    .an-chart-canvas {
      position: relative;
      height: 280px;
    }

    .an-chart-canvas canvas {
      width: 100% !important;
      height: 100% !important;
    }

    .an-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      margin-bottom: 10px;
      font-size: 12px;
      font-weight: 600;
    }

    .an-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .an-legend-swatch {
      width: 28px;
      height: 14px;
      border-radius: 2px;
      display: inline-block;
    }

    .an-table-wrap {
      overflow-x: auto;
      margin-top: 12px;
    }

    .an-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 720px;
    }

    .an-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 10px 12px;
      border: 1px solid #2d3238;
      white-space: nowrap;
    }

    .an-table tbody td {
      border: 1px solid #dee2e6;
      padding: 10px 12px;
      font-size: 13px;
      background: #fff;
    }

    .an-table tbody tr:last-child td {
      font-weight: 700;
      background: #f8f9fa;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-line-chart"></i> {{ $ui['parent_label'] ?? 'Analytics' }}</h1>
      <p>{{ $ui['title'] ?? 'Hotel Performance' }} charts and segment breakdown</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item">{{ $ui['parent_label'] ?? 'Analytics' }}</li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Hotel Performance' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="an-page-title">
          <h3>{{ $ui['parent_label'] ?? 'Analytics' }}</h3>
          <span>{{ $ui['title'] ?? 'Hotel Performance' }}</span>
        </div>

        <form method="GET" action="{{ route('hotel.analytics.hotel-performance') }}" id="anFilterForm">
          <input type="hidden" name="view" id="anViewInput" value="{{ $filters['view'] }}">

          <div class="an-toolbar">
            <div class="an-view-toggle btn-group" role="group">
              @foreach($ui['views'] ?? [] as $viewKey => $viewLabel)
                <button type="button"
                  class="btn btn-sm js-an-view {{ $filters['view'] === $viewKey ? 'is-active' : '' }}"
                  data-view="{{ $viewKey }}">{{ $viewLabel }}</button>
              @endforeach
            </div>

            <div class="an-field">
              <label for="from_date">From:</label>
              <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
            </div>

            <div class="an-field">
              <label for="to_date">To:</label>
              <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
            </div>

            <div class="an-field">
              <label for="filter_by">Filter:</label>
              <select class="form-control" id="filter_by" name="filter_by">
                @foreach($ui['filter_options'] ?? [] as $key => $label)
                  <option value="{{ $key }}" {{ $filters['filter_by'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="an-actions">
              <button type="submit" class="btn btn-an js-an-report" data-view="weekly">Weekly Report</button>
              <button type="submit" class="btn btn-an js-an-report" data-view="monthly">Monthly Report</button>
            </div>
          </div>
        </form>

        <div class="tile-body">
          <div class="row">
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="an-legend">
                  <span class="an-legend-item">
                    <span class="an-legend-swatch" style="background:#940000;"></span>
                    closing occupancy(%age)
                  </span>
                </div>
                <div class="an-chart-canvas">
                  <canvas id="anOccupancyChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="an-legend">
                  <span class="an-legend-item">
                    <span class="an-legend-swatch" style="background:rgba(151,187,205,1);"></span>
                    ARR
                  </span>
                  <span class="an-legend-item">
                    <span class="an-legend-swatch" style="background:#FDB45C;"></span>
                    RevPAR
                  </span>
                </div>
                <div class="an-chart-canvas">
                  <canvas id="anArrRevparChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <h4 class="an-section-title">All Channels</h4>
          <div class="row">
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="an-legend">
                  <span class="an-legend-item">
                    <span class="an-legend-swatch" style="background:#949FB1;"></span>
                    Total Sales
                  </span>
                </div>
                <div class="an-chart-canvas">
                  <canvas id="anAllSalesChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="an-legend">
                  <span class="an-legend-item">
                    <span class="an-legend-swatch" style="background:#FDB45C;"></span>
                    Total room nights
                  </span>
                </div>
                <div class="an-chart-canvas">
                  <canvas id="anAllNightsChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <h4 class="an-section-title">Per Channel</h4>
          <div class="row">
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="tile-title">Total Sales</div>
                <div class="an-chart-canvas">
                  <canvas id="anChannelSalesChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="tile-title">Room Nights</div>
                <div class="an-chart-canvas">
                  <canvas id="anChannelNightsChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <h4 class="an-section-title">Per Segment</h4>
          <div class="row">
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="tile-title">Total Sales (By Segment)</div>
                <div class="an-chart-canvas">
                  <canvas id="anSegmentSalesChart"></canvas>
                </div>
                <div class="an-table-wrap">
                  <table class="an-table">
                    <thead>
                      <tr>
                        <th>Sales (in Thousands)</th>
                        @foreach($report['periods'] ?? [] as $period)
                          <th>{{ $period['label'] }}</th>
                        @endforeach
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($report['segment_sales_table'] ?? [] as $row)
                        <tr>
                          <td>{{ $row['label'] }}</td>
                          @foreach($report['periods'] ?? [] as $period)
                            <td>{{ $row[$period['key']] ?? '0.00' }}</td>
                          @endforeach
                          <td>{{ $row['total'] ?? '0.00' }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="tile an-chart-tile">
                <div class="tile-title">Total Room Nights (By Segment)</div>
                <div class="an-chart-canvas">
                  <canvas id="anSegmentNightsChart"></canvas>
                </div>
                <div class="an-table-wrap">
                  <table class="an-table">
                    <thead>
                      <tr>
                        <th>Room Nights</th>
                        @foreach($report['periods'] ?? [] as $period)
                          <th>{{ $period['label'] }}</th>
                        @endforeach
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($report['segment_nights_table'] ?? [] as $row)
                        <tr>
                          <td>{{ $row['label'] }}</td>
                          @foreach($report['periods'] ?? [] as $period)
                            <td>{{ $row[$period['key']] ?? '0' }}</td>
                          @endforeach
                          <td>{{ $row['total'] ?? '0' }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
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
  <script type="application/json" id="an-report-data">@json($report)</script>
  <script>
    (function () {
      var report = {};
      try {
        report = JSON.parse(document.getElementById('an-report-data').textContent || '{}');
      } catch (e) {
        report = {};
      }

      var labels = report.chart_labels || [];
      var palette = [
        { fill: 'rgba(148,0,0,0.15)', stroke: '#940000', point: '#940000' },
        { fill: 'rgba(151,187,205,0.2)', stroke: 'rgba(151,187,205,1)', point: 'rgba(151,187,205,1)' },
        { fill: 'rgba(253,180,92,0.2)', stroke: '#FDB45C', point: '#FDB45C' },
        { fill: 'rgba(148,163,184,0.2)', stroke: '#949FB1', point: '#949FB1' },
        { fill: 'rgba(70,191,189,0.2)', stroke: '#46BFBD', point: '#46BFBD' },
        { fill: 'rgba(247,70,74,0.15)', stroke: '#F7464A', point: '#F7464A' }
      ];

      function lineDataset(label, data, colorIndex) {
        var c = palette[colorIndex % palette.length];
        return {
          label: label,
          fillColor: c.fill,
          strokeColor: c.stroke,
          pointColor: c.stroke,
          pointStrokeColor: '#fff',
          pointHighlightFill: '#fff',
          pointHighlightStroke: c.stroke,
          data: data
        };
      }

      function barDataset(label, data, colorIndex) {
        var c = palette[colorIndex % palette.length];
        return {
          label: label,
          fillColor: c.stroke,
          strokeColor: c.stroke,
          highlightFill: c.stroke,
          highlightStroke: c.stroke,
          data: data
        };
      }

      function maxValue(list, floor) {
        var max = Math.max.apply(null, list.concat([floor || 0]));
        return max <= 0 ? (floor || 1) : max;
      }

      function renderLine(canvasId, datasets, yMax) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas.getContext('2d')).Line({
          labels: labels,
          datasets: datasets
        }, {
          scaleOverride: !!yMax,
          scaleSteps: yMax ? 10 : undefined,
          scaleStepWidth: yMax ? Math.ceil(yMax / 10) : undefined,
          scaleStartValue: 0
        });
      }

      function renderBar(canvasId, datasets, yMax) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas.getContext('2d')).Bar({
          labels: labels,
          datasets: datasets
        }, {
          scaleOverride: !!yMax,
          scaleSteps: yMax ? 10 : undefined,
          scaleStepWidth: yMax ? Math.ceil(yMax / 10) : undefined,
          scaleStartValue: 0
        });
      }

      function bucketDatasets(buckets, field, type) {
        return (buckets || []).slice(0, 5).map(function (bucket, index) {
          return type === 'bar'
            ? barDataset(bucket.name, bucket[field] || [], index + 1)
            : lineDataset(bucket.name, bucket[field] || [], index + 1);
        });
      }

      renderLine('anOccupancyChart', [
        lineDataset('closing occupancy(%age)', report.closing_occupancy || [], 0)
      ], 100);

      renderLine('anArrRevparChart', [
        lineDataset('ARR', report.arr || [], 1),
        lineDataset('RevPAR', report.revpar || [], 2)
      ], maxValue((report.arr || []).concat(report.revpar || []), 1));

      renderLine('anAllSalesChart', [
        lineDataset('Total Sales', report.total_sales || [], 3)
      ], maxValue(report.total_sales || [], 1));

      renderLine('anAllNightsChart', [
        lineDataset('Total room nights', report.total_room_nights || [], 2)
      ], maxValue(report.total_room_nights || [], 1));

      var channelSets = bucketDatasets(report.channels || [], 'sales', 'line');
      renderLine('anChannelSalesChart', channelSets.length ? channelSets : [
        lineDataset('Total Sales', report.total_sales || [], 3)
      ], maxValue(report.total_sales || [], 1));

      var channelNightSets = bucketDatasets(report.channels || [], 'roomNights', 'line');
      renderLine('anChannelNightsChart', channelNightSets.length ? channelNightSets : [
        lineDataset('Room Nights', report.total_room_nights || [], 2)
      ], maxValue(report.total_room_nights || [], 1));

      var segmentSalesSets = bucketDatasets(report.segments || [], 'sales', 'bar');
      renderBar('anSegmentSalesChart', segmentSalesSets.length ? segmentSalesSets : [
        barDataset('Total Sales', report.total_sales || [], 3)
      ], maxValue(report.total_sales || [], 1));

      var segmentNightSets = bucketDatasets(report.segments || [], 'roomNights', 'bar');
      renderBar('anSegmentNightsChart', segmentNightSets.length ? segmentNightSets : [
        barDataset('Room Nights', report.total_room_nights || [], 2)
      ], maxValue(report.total_room_nights || [], 1));

      var form = document.getElementById('anFilterForm');
      var viewInput = document.getElementById('anViewInput');

      document.querySelectorAll('.js-an-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!viewInput || !form) return;
          viewInput.value = btn.getAttribute('data-view') || 'monthly';
          form.submit();
        });
      });

      document.querySelectorAll('.js-an-report').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
          if (!viewInput) return;
          viewInput.value = btn.getAttribute('data-view') || 'monthly';
        });
      });
    })();
  </script>
@endpush
