@extends('layouts.app')

@section('title', ($ui['parent_label'] ?? 'Analytics').' - '.($ui['title'] ?? 'Dynamic Pricing'))

@push('styles')
  <style>
    :root {
      --dp-brand: #940000;
    }

    .dp-page-title {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 10px;
      padding: 18px 20px 0;
    }

    .dp-page-title h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .dp-page-title span {
      font-size: 18px;
      color: #888;
      font-weight: 400;
    }

    .dp-panel {
      border: 1px solid #ddd;
      background: #fff;
      margin-bottom: 20px;
    }

    .dp-panel-head {
      padding: 14px 16px 10px;
      border-bottom: 1px solid #eee;
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }

    .dp-panel-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      padding: 12px 16px;
      border-bottom: 1px solid #f0f0f0;
    }

    .dp-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .dp-field .form-control {
      min-height: 34px;
      font-size: 13px;
      min-width: 160px;
    }

    .dp-note {
      padding: 0 16px 8px;
      font-size: 12px;
      color: #dc3545;
      font-style: italic;
    }

    .dp-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      padding: 8px 16px 0;
      font-size: 12px;
      font-weight: 600;
    }

    .dp-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .dp-legend-box {
      width: 28px;
      height: 14px;
      border: 2px solid #28a745;
      border-radius: 2px;
      display: inline-block;
    }

    .dp-legend-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }

    .dp-chart-wrap {
      position: relative;
      height: 320px;
      padding: 10px 16px 16px;
    }

    .dp-chart-wrap canvas {
      width: 100% !important;
      height: 100% !important;
    }

    .dp-override-panel {
      margin-top: 4px;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-area-chart"></i> {{ $ui['parent_label'] ?? 'Analytics' }}</h1>
      <p>{{ $ui['title'] ?? 'Dynamic Pricing' }} rate trends and overrides</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item">{{ $ui['parent_label'] ?? 'Analytics' }}</li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Dynamic Pricing' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="dp-page-title">
          <h3>{{ $ui['parent_label'] ?? 'Analytics' }}</h3>
          <span>{{ $ui['title'] ?? 'Dynamic Pricing' }}</span>
        </div>

        <div class="tile-body">
          <form method="GET" action="{{ route('hotel.analytics.dynamic-pricing') }}" id="dpFilterForm">
            <div class="row">
              <div class="col-lg-6">
                <div class="dp-panel">
                  <div class="dp-panel-head">Dynamic Pricing Graph (Past)</div>
                  <div class="dp-panel-toolbar">
                    <div class="dp-field">
                      <label for="past_date">Select Dates:</label>
                      <input type="date" class="form-control js-dp-filter" id="past_date" name="past_date" value="{{ $filters['past_date'] }}">
                    </div>
                  </div>
                  <p class="dp-note">* Red dots are indicating manual overriding of rate</p>
                  <div class="dp-legend">
                    <span class="dp-legend-item">
                      <span class="dp-legend-box"></span>
                      {{ $report['bar_label'] ?? 'BAR' }}
                    </span>
                  </div>
                  <div class="dp-chart-wrap">
                    <canvas id="dpPastChart"></canvas>
                  </div>
                </div>
              </div>

              <div class="col-lg-6">
                <div class="dp-panel">
                  <div class="dp-panel-head">Dynamic Pricing Graph (Future)</div>
                  <div class="dp-panel-toolbar">
                    <div class="dp-field">
                      <label for="future_from">From:</label>
                      <input type="date" class="form-control js-dp-filter" id="future_from" name="future_from" value="{{ $filters['future_from'] }}">
                    </div>
                    <div class="dp-field">
                      <label for="future_to">To:</label>
                      <input type="date" class="form-control js-dp-filter" id="future_to" name="future_to" value="{{ $filters['future_to'] }}">
                    </div>
                  </div>
                  <div class="dp-legend">
                    <span class="dp-legend-item">
                      <span class="dp-legend-box"></span>
                      Rate
                    </span>
                    <span class="dp-legend-item">
                      <span class="dp-legend-dot" style="background:#dc3545;"></span>
                      Manual override
                    </span>
                    <span class="dp-legend-item">
                      <span class="dp-legend-dot" style="background:#28a745;"></span>
                      System rate
                    </span>
                  </div>
                  <div class="dp-chart-wrap">
                    <canvas id="dpFutureChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </form>

          <div class="dp-panel dp-override-panel">
            <div class="dp-panel-head">Rate Override Graph</div>
            <div class="dp-legend">
              <span class="dp-legend-item">
                <span class="dp-legend-dot" style="background:#b39ddb;"></span>
                Override %
              </span>
            </div>
            <div class="dp-chart-wrap">
              <canvas id="dpOverrideChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/chart.js') }}"></script>
  <script type="application/json" id="dp-report-data">@json($report)</script>
  <script>
    (function () {
      var report = {};
      try {
        report = JSON.parse(document.getElementById('dp-report-data').textContent || '{}');
      } catch (e) {
        report = {};
      }

      function overrideMarkerData(values, overrides) {
        return (values || []).map(function (value, index) {
          return (overrides || [])[index] ? value : null;
        });
      }

      function renderRateLine(canvasId, block, yMin, yMax) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined' || !block) return;

        var values = block.values || [];
        var overrides = block.overrides || [];
        var step = (yMax - yMin) / 10;

        new Chart(canvas.getContext('2d')).Line({
          labels: block.labels || [],
          datasets: [
            {
              label: report.bar_label || 'BAR',
              fillColor: 'rgba(40,167,69,0.05)',
              strokeColor: '#28a745',
              pointColor: '#28a745',
              pointStrokeColor: '#fff',
              pointHighlightFill: '#fff',
              pointHighlightStroke: '#28a745',
              pointDotRadius: 4,
              data: values
            },
            {
              label: 'Manual override',
              fillColor: 'rgba(0,0,0,0)',
              strokeColor: 'rgba(0,0,0,0)',
              pointColor: '#dc3545',
              pointStrokeColor: '#fff',
              pointHighlightFill: '#fff',
              pointHighlightStroke: '#dc3545',
              pointDotRadius: 6,
              data: overrideMarkerData(values, overrides)
            }
          ]
        }, {
          scaleOverride: true,
          scaleSteps: 10,
          scaleStepWidth: step > 0 ? step : 1,
          scaleStartValue: yMin
        });
      }

      function renderOverrideBar(block) {
        var canvas = document.getElementById('dpOverrideChart');
        if (!canvas || typeof Chart === 'undefined' || !block) return;

        new Chart(canvas.getContext('2d')).Bar({
          labels: block.labels || [],
          datasets: [{
            label: 'Override %',
            fillColor: '#b39ddb',
            strokeColor: '#b39ddb',
            highlightFill: '#9575cd',
            highlightStroke: '#9575cd',
            data: block.values || []
          }]
        }, {
          scaleOverride: true,
          scaleSteps: 10,
          scaleStepWidth: 10,
          scaleStartValue: 0
        });
      }

      var past = report.past || {};
      var future = report.future || {};
      var yMin = past.y_min !== undefined ? past.y_min : 0;
      var yMax = past.y_max !== undefined ? past.y_max : 100;

      if (yMax <= yMin) {
        yMax = yMin + 2;
      }

      renderRateLine('dpPastChart', past, yMin, yMax);
      renderRateLine('dpFutureChart', future, 0, 100);
      renderOverrideBar(report.override || {});

      document.querySelectorAll('.js-dp-filter').forEach(function (input) {
        input.addEventListener('change', function () {
          document.getElementById('dpFilterForm').submit();
        });
      });
    })();
  </script>
@endpush
