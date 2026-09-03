@extends('layouts.app')

@section('title', ($ui['parent_label'] ?? 'Analytics').' - '.($ui['title'] ?? 'Daily Report'))

@push('styles')
  <style>
    :root {
      --dr-brand: #940000;
    }

    .dr-page-title {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 10px;
      padding: 18px 20px 0;
    }

    .dr-page-title h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .dr-page-title span {
      font-size: 18px;
      color: #888;
      font-weight: 400;
    }

    .dr-breadcrumb {
      margin-left: auto;
      font-size: 13px;
      color: #888;
    }

    .dr-breadcrumb i {
      color: #f0ad4e;
      margin-right: 4px;
    }

    .dr-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      padding: 14px 20px 16px;
      border-bottom: 3px solid var(--dr-brand);
    }

    .dr-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .dr-field .form-control {
      min-height: 36px;
      font-size: 13px;
      min-width: 180px;
    }

    .dr-table-wrap {
      padding: 20px;
    }

    .dr-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      background: #fff;
      border: 1px solid #ddd;
    }

    .dr-table th,
    .dr-table td {
      border: 1px solid #ddd;
      padding: 8px 12px;
      text-align: center;
      vertical-align: middle;
    }

    .dr-table thead th {
      background: #555;
      color: #fff;
      font-weight: 600;
      font-size: 13px;
    }

    .dr-table thead th.dr-th-label {
      background: #555;
    }

    .dr-table thead th.dr-th-sub {
      background: #666;
      font-size: 12px;
      font-weight: 500;
    }

    .dr-section td {
      background: #f5f5f5;
      font-weight: 700;
      text-align: left;
      color: #333;
    }

    .dr-table tbody td:first-child {
      text-align: left;
      font-weight: 500;
    }

    .dr-table tbody tr:nth-child(even):not(.dr-section) td {
      background: #fafafa;
    }

    .dr-table .dr-total td {
      font-weight: 700;
      background: #f9f9f9;
    }

    .dr-footnote {
      margin-top: 10px;
      font-size: 12px;
      color: #888;
      font-style: italic;
    }
  </style>
@endpush

@section('content')
  <div class="tile">
    <div class="dr-page-title">
      <h3>{{ $ui['parent_label'] ?? 'Analytics' }}</h3>
      <span>{{ $ui['title'] ?? 'Daily Report' }}</span>
      <div class="dr-breadcrumb">
        <i class="fa fa-star"></i>
        {{ $ui['parent_label'] ?? 'Analytics' }} &gt; {{ $ui['title'] ?? 'Daily Report' }}
      </div>
    </div>

    <form method="GET" action="{{ route('hotel.analytics.daily-report') }}" id="drFilterForm">
      <div class="dr-toolbar">
        <div class="dr-field">
          <label for="report_date">Select Date:</label>
          <input
            type="date"
            name="report_date"
            id="report_date"
            class="form-control"
            value="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}"
          >
        </div>
      </div>
    </form>

    <div class="dr-table-wrap">
      <table class="dr-table">
        <thead>
          <tr>
            <th class="dr-th-label" style="width: 28%;">&nbsp;</th>
            <th colspan="2">{{ $report['day_label'] ?? '' }}</th>
            <th colspan="2">{{ $report['period_label'] ?? '' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr class="dr-section">
            <td colspan="5">Last Night's Performance</td>
          </tr>
          @foreach ($report['last_night'] ?? [] as $row)
            <tr>
              <td>{{ $row['label'] }}</td>
              <td colspan="2">{{ $row['day'] }}</td>
              <td colspan="2">{{ $row['period'] }}</td>
            </tr>
          @endforeach

          <tr class="dr-section">
            <td colspan="5">Source of Business*</td>
          </tr>
          <tr>
            <td>&nbsp;</td>
            <th class="dr-th-sub">Rooms</th>
            <th class="dr-th-sub">{{ $report['currency'] ?? 'USD' }}</th>
            <th class="dr-th-sub">Rooms</th>
            <th class="dr-th-sub">{{ $report['currency'] ?? 'USD' }}</th>
          </tr>
          @foreach ($report['sources'] ?? [] as $row)
            <tr @class(['dr-total' => ($row['label'] ?? '') === 'Total'])>
              <td>{{ $row['label'] }}</td>
              <td>{{ $row['day_rooms'] }}</td>
              <td>{{ $row['day_usd'] }}</td>
              <td>{{ $row['period_rooms'] }}</td>
              <td>{{ $row['period_usd'] }}</td>
            </tr>
          @endforeach

          <tr class="dr-section">
            <td colspan="5">Business Pick Up*</td>
          </tr>
          <tr>
            <td>&nbsp;</td>
            <th class="dr-th-sub">RNs</th>
            <th class="dr-th-sub">{{ $report['currency'] ?? 'USD' }}</th>
            <th class="dr-th-sub">RNs</th>
            <th class="dr-th-sub">{{ $report['currency'] ?? 'USD' }}</th>
          </tr>
          @foreach ($report['pickup'] ?? [] as $row)
            <tr @class(['dr-total' => ($row['label'] ?? '') === 'Total'])>
              <td>{{ $row['label'] }}</td>
              <td>{{ $row['day_rns'] }}</td>
              <td>{{ $row['day_usd'] }}</td>
              <td>{{ $row['period_rns'] }}</td>
              <td>{{ $row['period_usd'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <p class="dr-footnote">* Sources that generate business for the day will feature in the report only</p>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.getElementById('report_date').addEventListener('change', function () {
      document.getElementById('drFilterForm').submit();
    });
  </script>
@endpush
