@extends('layouts.app')

@section('title', ($ui['parent_label'] ?? 'Analytics').' - '.($ui['title'] ?? 'Pick Up Report'))

@push('styles')
  <style>
    :root {
      --pu-brand: #940000;
    }

    .pu-page-title {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 10px;
      padding: 18px 20px 0;
    }

    .pu-page-title h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .pu-page-title span {
      font-size: 18px;
      color: #888;
      font-weight: 400;
    }

    .pu-breadcrumb {
      margin-left: auto;
      font-size: 13px;
      color: #888;
    }

    .pu-breadcrumb i {
      color: #f0ad4e;
      margin-right: 4px;
    }

    .pu-filter-box {
      margin: 16px 20px;
      padding: 16px;
      background: #fff;
      border: 1px solid #e5e7eb;
    }

    .pu-filter-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      align-items: flex-end;
    }

    .pu-modes {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 180px;
    }

    .pu-modes label {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      font-size: 13px;
      font-weight: 600;
      color: #333;
      cursor: pointer;
    }

    .pu-modes input {
      width: 16px;
      height: 16px;
      margin: 0;
    }

    .pu-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .pu-field .form-control {
      min-height: 36px;
      font-size: 13px;
      min-width: 150px;
    }

    .pu-summary {
      margin-left: auto;
      min-width: 220px;
    }

    .pu-summary-row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      font-size: 13px;
      padding: 4px 0;
      color: #333;
    }

    .pu-summary-row strong {
      font-weight: 700;
    }

    .pu-table-wrap {
      overflow-x: auto;
      padding: 0 20px 20px;
    }

    .pu-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 980px;
    }

    .pu-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 10px 12px;
      border: 1px solid #2d3238;
      white-space: nowrap;
      cursor: pointer;
      user-select: none;
    }

    .pu-table thead th .fa {
      margin-left: 4px;
      opacity: 0.7;
      font-size: 11px;
    }

    .pu-table tbody td {
      border: 1px solid #dee2e6;
      padding: 10px 12px;
      font-size: 13px;
      color: #333;
      background: #fff;
    }

    .pu-table tbody tr:nth-child(even) td {
      background: #f8f9fa;
    }

    .pu-hidden {
      display: none !important;
    }
  </style>
@endpush

@section('content')
  <div class="tile">
    <div class="pu-page-title">
      <h3>{{ $ui['parent_label'] ?? 'Analytics' }}</h3>
      <span>{{ $ui['title'] ?? 'Pick Up Report' }}</span>
      <div class="pu-breadcrumb">
        <i class="fa fa-database"></i>
        {{ $ui['parent_label'] ?? 'Analytics' }} &gt; {{ $ui['title'] ?? 'Pick Up Report' }}
      </div>
    </div>

    <form method="GET" action="{{ route('hotel.analytics.pickup-report') }}" id="puFilterForm">
      <div class="pu-filter-box">
        <div class="pu-filter-grid">
          <div class="pu-modes">
            @foreach($ui['modes'] ?? [] as $modeKey => $modeLabel)
              <label>
                <input type="radio" name="mode" value="{{ $modeKey }}" class="js-pu-mode" {{ ($filters['mode'] ?? 'by_date') === $modeKey ? 'checked' : '' }}>
                {{ $modeLabel }}
              </label>
            @endforeach
          </div>

          <div class="pu-field js-pu-date-single {{ ($filters['mode'] ?? 'by_date') === 'by_range' ? 'pu-hidden' : '' }}">
            <label for="pickup_date">Select Date:</label>
            <input type="date" class="form-control js-pu-filter" id="pickup_date" name="pickup_date" value="{{ $filters['pickup_date'] }}">
          </div>

          <div class="pu-field js-pu-date-from {{ ($filters['mode'] ?? 'by_date') === 'by_date' ? 'pu-hidden' : '' }}">
            <label for="pickup_from">From:</label>
            <input type="date" class="form-control js-pu-filter" id="pickup_from" name="pickup_from" value="{{ $filters['pickup_from'] }}">
          </div>

          <div class="pu-field js-pu-date-to {{ ($filters['mode'] ?? 'by_date') === 'by_date' ? 'pu-hidden' : '' }}">
            <label for="pickup_to">To:</label>
            <input type="date" class="form-control js-pu-filter" id="pickup_to" name="pickup_to" value="{{ $filters['pickup_to'] }}">
          </div>

          <div class="pu-field">
            <label for="report_type">PickUp Report Type :</label>
            <select class="form-control js-pu-filter" id="report_type" name="report_type">
              @foreach($ui['report_types'] ?? [] as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" {{ ($filters['report_type'] ?? 'date_wise') === $typeKey ? 'selected' : '' }}>{{ $typeLabel }}</option>
              @endforeach
            </select>
          </div>

          <div class="pu-summary">
            <div class="pu-summary-row">
              <span>Room Nights Pickup</span>
              <strong>{{ $report['summary']['room_nights_pickup'] ?? 0 }}</strong>
            </div>
            <div class="pu-summary-row">
              <span>Total Revenue</span>
              <strong>{{ $report['currency'] ?? 'USD' }} {{ $report['summary']['total_revenue'] ?? '0' }}</strong>
            </div>
            <div class="pu-summary-row">
              <span>ARR</span>
              <strong>{{ $report['currency'] ?? 'USD' }} {{ $report['summary']['arr'] ?? '0' }}</strong>
            </div>
          </div>
        </div>
      </div>
    </form>

    <div class="pu-table-wrap">
      <table class="pu-table" id="puReportTable">
        <thead>
          <tr>
            <th data-sort="text">Stay Date <i class="fa fa-sort"></i></th>
            <th data-sort="number">Total Rooms <i class="fa fa-sort"></i></th>
            <th data-sort="number">Rooms Occupied <i class="fa fa-sort"></i></th>
            <th data-sort="percent">Occupancy Forecast <i class="fa fa-sort"></i></th>
            <th data-sort="number">PickUp <i class="fa fa-sort"></i></th>
            <th data-sort="number">Revenue <i class="fa fa-sort"></i></th>
            <th data-sort="number">Avg. Revenue <i class="fa fa-sort"></i></th>
            <th data-sort="number">Current Base Rate <i class="fa fa-sort"></i></th>
          </tr>
        </thead>
        <tbody>
          @forelse($report['rows'] ?? [] as $row)
            <tr>
              <td>{{ $row['stay_date_label'] }}</td>
              <td>{{ $row['total_rooms'] }}</td>
              <td>{{ $row['rooms_occupied'] }}</td>
              <td>{{ $row['occupancy_forecast'] }}</td>
              <td>{{ $row['pickup'] }}</td>
              <td>{{ $row['revenue'] }}</td>
              <td>{{ $row['avg_revenue'] }}</td>
              <td>{{ $row['base_rate'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" style="text-align:center;padding:24px;color:#666;">No pickup data for the selected filters.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('puFilterForm');
      var singleBlock = document.querySelector('.js-pu-date-single');
      var fromBlock = document.querySelector('.js-pu-date-from');
      var toBlock = document.querySelector('.js-pu-date-to');

      function toggleMode(mode) {
        var isRange = mode === 'by_range';
        singleBlock.classList.toggle('pu-hidden', isRange);
        fromBlock.classList.toggle('pu-hidden', !isRange);
        toBlock.classList.toggle('pu-hidden', !isRange);
      }

      document.querySelectorAll('.js-pu-mode').forEach(function (radio) {
        radio.addEventListener('change', function () {
          toggleMode(radio.value);
          form.submit();
        });
      });

      document.querySelectorAll('.js-pu-filter').forEach(function (input) {
        input.addEventListener('change', function () {
          form.submit();
        });
      });

      var table = document.getElementById('puReportTable');
      if (!table) return;

      function cellValue(row, index, type) {
        var text = row.cells[index].textContent.trim();
        if (type === 'number') {
          return parseFloat(text.replace(/,/g, '')) || 0;
        }
        if (type === 'percent') {
          return parseFloat(text.replace('%', '')) || 0;
        }
        return text.toLowerCase();
      }

      table.querySelectorAll('thead th[data-sort]').forEach(function (th, index) {
        th.addEventListener('click', function () {
          var type = th.getAttribute('data-sort') || 'text';
          var tbody = table.querySelector('tbody');
          var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr')).filter(function (row) {
            return row.cells.length > 1;
          });
          var asc = th.getAttribute('data-order') !== 'asc';

          rows.sort(function (a, b) {
            var av = cellValue(a, index, type);
            var bv = cellValue(b, index, type);
            if (av < bv) return asc ? -1 : 1;
            if (av > bv) return asc ? 1 : -1;
            return 0;
          });

          table.querySelectorAll('thead th[data-sort]').forEach(function (header) {
            header.removeAttribute('data-order');
          });
          th.setAttribute('data-order', asc ? 'asc' : 'desc');

          rows.forEach(function (row) {
            tbody.appendChild(row);
          });
        });
      });
    })();
  </script>
@endpush
