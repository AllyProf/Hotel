@extends('layouts.app')

@section('title', ($ui['title'] ?? 'Daily Report'))

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

    .dr-meta {
      margin-left: auto;
      font-size: 13px;
      color: #666;
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

    .dr-revenue-native {
      font-size: 11px;
      color: #777;
      margin-top: 3px;
      font-weight: 400;
    }
  </style>
@endpush

@section('content')
  <div class="tile">
    <div class="dr-page-title">
      <h3>{{ $ui['title'] ?? 'Daily Report' }}</h3>
    </div>

    <form method="GET" action="{{ route('hotel.analytics.daily-report') }}" id="drFilterForm">
      <div class="dr-toolbar">
        <div class="dr-field">
          <label for="report_date">Report Date</label>
          <input
            type="date"
            name="report_date"
            id="report_date"
            class="form-control"
            value="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}"
          >
        </div>
        <div class="dr-meta">
          {{ $report['total_rooms'] ?? 0 }} sellable rooms &middot; Operating currency: {{ $report['currency'] ?? 'TZS' }}
        </div>
      </div>
    </form>

    <div class="dr-table-wrap">
      <table class="dr-table">
        <thead>
          <tr>
            <th style="width: 28%;">&nbsp;</th>
            <th colspan="2">{{ $report['day_label'] ?? '' }}</th>
            <th colspan="2">{{ $report['period_label'] ?? '' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr class="dr-section">
            <td colspan="5">Daily Performance</td>
          </tr>
          @foreach ($report['performance'] ?? [] as $row)
            <tr>
              <td>{{ $row['label'] }}</td>
              <td colspan="2">
                {{ $row['day'] }}
                @if(! empty($row['day_native']))
                  <div class="dr-revenue-native">Original: {{ $row['day_native'] }}</div>
                @endif
              </td>
              <td colspan="2">
                {{ $row['period'] }}
                @if(! empty($row['period_native']))
                  <div class="dr-revenue-native">Original: {{ $row['period_native'] }}</div>
                @endif
              </td>
            </tr>
          @endforeach

          <tr class="dr-section">
            <td colspan="5">Source of Business</td>
          </tr>
          <tr>
            <td>&nbsp;</td>
            <th class="dr-th-sub">Rooms</th>
            <th class="dr-th-sub">Revenue</th>
            <th class="dr-th-sub">Rooms</th>
            <th class="dr-th-sub">Revenue</th>
          </tr>
          @forelse ($report['sources'] ?? [] as $row)
            <tr @class(['dr-total' => ! empty($row['is_total']) || ($row['label'] ?? '') === 'Total'])>
              <td>{{ $row['label'] }}</td>
              <td>{{ $row['day_rooms'] }}</td>
              <td>
                {{ $row['day_revenue'] }}
                @if(! empty($row['day_revenue_native']))
                  <div class="dr-revenue-native">Original: {{ $row['day_revenue_native'] }}</div>
                @endif
              </td>
              <td>{{ $row['period_rooms'] }}</td>
              <td>
                {{ $row['period_revenue'] }}
                @if(! empty($row['period_revenue_native']))
                  <div class="dr-revenue-native">Original: {{ $row['period_revenue_native'] }}</div>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-muted">No occupied rooms for this date.</td>
            </tr>
          @endforelse

          @if(! empty($report['services'] ?? []))
            <tr class="dr-section">
              <td colspan="5">PMS Services</td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <th class="dr-th-sub">Transactions</th>
              <th class="dr-th-sub">Revenue</th>
              <th class="dr-th-sub">Transactions</th>
              <th class="dr-th-sub">Revenue</th>
            </tr>
            @foreach ($report['services'] as $row)
              <tr @class(['dr-total' => ! empty($row['is_total'])])>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['day_count'] }}</td>
                <td>
                  {{ $row['day_revenue'] }}
                  @if(! empty($row['day_revenue_native']))
                    <div class="dr-revenue-native">Original: {{ $row['day_revenue_native'] }}</div>
                  @endif
                </td>
                <td>{{ $row['period_count'] }}</td>
                <td>
                  {{ $row['period_revenue'] }}
                  @if(! empty($row['period_revenue_native']))
                    <div class="dr-revenue-native">Original: {{ $row['period_revenue_native'] }}</div>
                  @endif
                </td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
      @if(! empty($report['exchange_rate_note']))
        <p class="text-muted small px-3 pb-3 mb-0">{{ $report['exchange_rate_note'] }}</p>
      @endif
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
