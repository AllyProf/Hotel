@extends('layouts.app')

@section('title', $ui['title'] ?? 'Logs')

@push('styles')
  <style>
    :root {
      --lg-brand: #940000;
      --lg-brand-dark: #7a0000;
    }

    .lg-page { background: #fff; }

    .lg-header {
      padding: 18px 20px 0;
    }

    .lg-header h3 {
      margin: 0 0 16px;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .lg-filter-box {
      margin: 0 20px 16px;
      padding: 16px;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
    }

    .lg-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
    }

    .lg-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .lg-field .form-control {
      min-height: 36px;
      font-size: 13px;
    }

    .lg-field--date input { min-width: 145px; }
    .lg-field--select select { min-width: 120px; }
    .lg-field--text input { min-width: 120px; }

    .lg-checks {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 130px;
      padding-bottom: 2px;
    }

    .lg-checks label {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      font-size: 13px;
      font-weight: 600;
      color: #333;
      cursor: pointer;
    }

    .lg-checks input {
      width: 16px;
      height: 16px;
      margin: 0;
    }

    .lg-submit-wrap {
      margin-left: auto;
    }

    .btn-lg-submit {
      background: var(--lg-brand) !important;
      border-color: var(--lg-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 18px;
      min-height: 36px;
    }

    .btn-lg-submit:hover {
      background: var(--lg-brand-dark) !important;
      border-color: var(--lg-brand-dark) !important;
      color: #fff !important;
    }

    .lg-table-wrap {
      overflow-x: auto;
      padding: 0 20px 20px;
    }

    .lg-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1100px;
    }

    .lg-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 10px 12px;
      border: 1px solid #2d3238;
      white-space: nowrap;
    }

    .lg-table tbody td {
      border: 1px solid #dee2e6;
      padding: 10px 12px;
      font-size: 13px;
      color: #333;
      vertical-align: top;
    }

    .lg-table tbody tr:nth-child(even) td {
      background: #f8f9fa;
    }

    .lg-table tbody tr:nth-child(odd) td {
      background: #fff;
    }

    .lg-table tbody tr:hover td {
      background: #fef2f2;
    }

    .lg-empty {
      padding: 40px 20px;
      text-align: center;
      color: #666;
      font-size: 15px;
    }

    .lg-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      padding: 0 20px 18px;
    }

    .lg-per-page {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #555;
    }

    .lg-per-page select {
      width: 70px;
      min-height: 34px;
    }

    .lg-count {
      font-size: 13px;
      color: #555;
    }

    .lg-nav {
      display: flex;
      gap: 4px;
    }

    .lg-nav-btn {
      width: 34px;
      height: 34px;
      border: 1px solid #dee2e6;
      background: #fff;
      color: #495057;
      border-radius: 3px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      font-size: 16px;
    }

    .lg-nav-btn:hover {
      border-color: var(--lg-brand);
      color: var(--lg-brand);
      text-decoration: none;
    }

    .lg-nav-btn.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-file-text-o"></i> {{ $ui['title'] ?? 'Logs' }}</h1>
      <p>Audit trail for inventory sync, payments, and room actions</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Logs' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile lg-page">
        <div class="lg-header">
          <h3>{{ $ui['title'] ?? 'Logs' }}</h3>
        </div>

        <form method="GET" action="{{ route('hotel.logs.index') }}" id="lgFilterForm">
          <input type="hidden" name="submitted" value="1">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}" id="lgPerPageHidden">

          <div class="lg-filter-box">
            <div class="lg-toolbar">
              <div class="lg-field lg-field--date">
                <label for="from_date">From Date:</label>
                <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
              </div>

              <div class="lg-field lg-field--date">
                <label for="to_date">To Date:</label>
                <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
              </div>

              <div class="lg-field lg-field--select">
                <label for="room_type">Room Type:</label>
                <select class="form-control" id="room_type" name="room_type">
                  <option value="" {{ $filters['room_type'] === '' ? 'selected' : '' }}>All</option>
                  @foreach($options['room_types'] ?? [] as $roomType)
                    <option value="{{ $roomType['value'] }}" {{ $filters['room_type'] === $roomType['value'] ? 'selected' : '' }}>
                      {{ $roomType['label'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="lg-field lg-field--select">
                <label for="room_no">Room No:</label>
                <select class="form-control" id="room_no" name="room_no">
                  <option value="" {{ $filters['room_no'] === '' ? 'selected' : '' }}>All</option>
                  @foreach($options['room_numbers'] ?? [] as $roomNumber)
                    <option value="{{ $roomNumber['value'] }}" {{ $filters['room_no'] === $roomNumber['value'] ? 'selected' : '' }}>
                      {{ $roomNumber['label'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="lg-field lg-field--text">
                <label for="invoice_no">Invoice No:</label>
                <input type="text" class="form-control" id="invoice_no" name="invoice_no" value="{{ $filters['invoice_no'] }}">
              </div>

              <div class="lg-field lg-field--text">
                <label for="booking_id">Booking Id:</label>
                <input type="text" class="form-control" id="booking_id" name="booking_id" value="{{ $filters['booking_id'] }}">
              </div>

              <div class="lg-checks">
                <label><input type="checkbox" name="payments" value="1" {{ $filters['payments'] ? 'checked' : '' }}> Payments</label>
                <label><input type="checkbox" name="out_of_order" value="1" {{ $filters['out_of_order'] ? 'checked' : '' }}> Out Of Order</label>
                <label><input type="checkbox" name="complimentary" value="1" {{ $filters['complimentary'] ? 'checked' : '' }}> Complimentary</label>
              </div>

              <div class="lg-field lg-submit-wrap">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-lg-submit">Submit</button>
              </div>
            </div>
          </div>
        </form>

        @if($logs)
          <div class="lg-table-wrap">
            <table class="lg-table">
              <thead>
                <tr>
                  @foreach($ui['columns'] ?? [] as $column)
                    <th>{{ $column['label'] }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @forelse($logs as $log)
                  <tr>
                    <td>{{ $log->dateLabel() }}</td>
                    <td>{{ $log->action_type }}</td>
                    <td>{{ $log->booking_id ?: '' }}</td>
                    <td>{{ $log->guest_name ?: '' }}</td>
                    <td>{{ $log->folio_no ?: '' }}</td>
                    <td>{{ $log->room_no ?: '' }}</td>
                    <td>{{ $log->details ?: '' }}</td>
                    <td>{{ $log->changed_by ?: '' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="{{ count($ui['columns'] ?? []) }}" class="lg-empty">
                      No logs found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if($logs->total() > 0)
            @php
              $queryBase = request()->except('page');
              $prevQuery = array_merge($queryBase, ['page' => max(1, $logs->currentPage() - 1)]);
              $nextQuery = array_merge($queryBase, ['page' => min($logs->lastPage(), $logs->currentPage() + 1)]);
            @endphp
            <div class="lg-footer">
              <div class="lg-per-page">
                <span>Items per page:</span>
                <select class="form-control js-lg-per-page">
                  @foreach($ui['per_page_options'] ?? [20, 50, 100] as $option)
                    <option value="{{ $option }}" {{ (int) $filters['per_page'] === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
                  @endforeach
                </select>
              </div>
              <div class="lg-count">{{ $logs->firstItem() }} – {{ $logs->lastItem() }} of {{ $logs->total() }}</div>
              <div class="lg-nav">
                <a href="{{ $logs->onFirstPage() ? '#' : route('hotel.logs.index', $prevQuery) }}"
                  class="lg-nav-btn {{ $logs->onFirstPage() ? 'is-disabled' : '' }}">&lsaquo;</a>
                <a href="{{ $logs->onLastPage() ? '#' : route('hotel.logs.index', $nextQuery) }}"
                  class="lg-nav-btn {{ $logs->onLastPage() ? 'is-disabled' : '' }}">&rsaquo;</a>
              </div>
            </div>
          @endif
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var perPageSelect = document.querySelector('.js-lg-per-page');
      var perPageHidden = document.getElementById('lgPerPageHidden');
      var filterForm = document.getElementById('lgFilterForm');

      if (perPageSelect && perPageHidden && filterForm) {
        perPageSelect.addEventListener('change', function () {
          perPageHidden.value = perPageSelect.value;
          filterForm.submit();
        });
      }
    })();
  </script>
@endpush
