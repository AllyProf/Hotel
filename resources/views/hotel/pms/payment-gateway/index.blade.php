@extends('layouts.app')

@section('title', $ui['title'] ?? 'Payment Status')

@push('styles')
  <style>
    :root {
      --pg-brand: #940000;
      --pg-brand-dark: #7a0000;
    }

    .pg-page { background: #fff; }

    .pg-header {
      padding: 18px 20px 0;
    }

    .pg-header h3 {
      margin: 0 0 14px;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .pg-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 0 20px 14px;
    }

    .btn-pg {
      background: var(--pg-brand) !important;
      border-color: var(--pg-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 14px;
      border-radius: 3px;
      white-space: nowrap;
    }

    .btn-pg:hover {
      background: var(--pg-brand-dark) !important;
      border-color: var(--pg-brand-dark) !important;
      color: #fff !important;
    }

    .btn-pg.is-muted {
      opacity: 0.65;
      cursor: not-allowed;
    }

    .pg-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      padding: 0 20px 16px;
      border-bottom: 3px solid var(--pg-brand);
    }

    .pg-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .pg-field .form-control {
      min-height: 36px;
      font-size: 13px;
    }

    .pg-field--date input { min-width: 145px; }
    .pg-field--text input { min-width: 130px; }
    .pg-field--select select { min-width: 120px; }

    .btn-pg-submit {
      background: var(--pg-brand) !important;
      border-color: var(--pg-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 18px;
      min-height: 36px;
    }

    .pg-table-wrap {
      overflow-x: auto;
      padding: 0 20px;
    }

    .pg-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1400px;
    }

    .pg-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 10px 12px;
      border: 1px solid #2d3238;
      white-space: nowrap;
    }

    .pg-table tbody td {
      border: 1px solid #dee2e6;
      padding: 10px 12px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
    }

    .pg-table tbody tr:hover td { background: #fafafa; }

    .pg-link {
      color: var(--pg-brand);
      text-decoration: none;
      font-weight: 600;
    }

    .pg-link:hover {
      color: var(--pg-brand-dark);
      text-decoration: underline;
    }

    .pg-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      padding: 14px 20px 18px;
    }

    .pg-per-page {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #555;
    }

    .pg-per-page select {
      width: 70px;
      min-height: 34px;
    }

    .pg-count {
      font-size: 13px;
      color: #555;
      min-width: 70px;
      text-align: center;
    }

    .pg-nav {
      display: flex;
      gap: 4px;
    }

    .pg-nav-btn {
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

    .pg-nav-btn:hover {
      border-color: var(--pg-brand);
      color: var(--pg-brand);
      text-decoration: none;
    }

    .pg-nav-btn.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }

    .pg-empty {
      padding: 32px 20px;
      text-align: center;
      color: #666;
      font-size: 15px;
    }

    .pg-modal__header {
      background: var(--pg-brand);
      color: #fff;
      border-bottom: none;
    }

    .pg-modal__header .close {
      color: #fff;
      opacity: 1;
    }

    .pg-modal__header-plain {
      border-bottom: none;
      padding-bottom: 0;
    }

    .pg-modal__header-plain .modal-title {
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .pg-modal__subtitle {
      font-size: 13px;
      color: #666;
    }

    .pg-modal__divider {
      height: 3px;
      background: var(--pg-brand);
      margin: 0 0 20px;
    }

    .pg-modal__body {
      font-size: 14px;
      line-height: 1.6;
      color: #333;
    }

    .btn-pg-modal-primary {
      background: var(--pg-brand) !important;
      border-color: var(--pg-brand) !important;
      color: #fff !important;
    }
  </style>
@endpush

@section('content')
  @php
    $queryBase = request()->except('page');
    $firstQuery = array_merge($queryBase, ['page' => 1]);
    $prevQuery = array_merge($queryBase, ['page' => max(1, $payments->currentPage() - 1)]);
    $nextQuery = array_merge($queryBase, ['page' => min($payments->lastPage(), $payments->currentPage() + 1)]);
    $lastQuery = array_merge($queryBase, ['page' => max(1, $payments->lastPage())]);
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-link"></i> {{ $ui['title'] ?? 'Payment Status' }}</h1>
      <p>Payment links, transactions, and settlement tracking</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Payment Status' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile pg-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif

        <div class="pg-header">
          <h3>{{ $ui['title'] ?? 'Payment Status' }}</h3>
        </div>

        <div class="pg-actions">
          @foreach($ui['header_actions'] ?? [] as $action)
            @if(! empty($action['route']))
              <a href="{{ route($action['route']) }}" class="btn btn-sm btn-pg">{{ $action['label'] }}</a>
            @elseif(! empty($action['modal']))
              <button type="button" class="btn btn-sm btn-pg" data-toggle="modal" data-target="#{{ $action['modal'] }}">
                {{ $action['label'] }}
              </button>
            @else
              <button type="button" class="btn btn-sm btn-pg is-muted" disabled title="Coming soon">{{ $action['label'] }}</button>
            @endif
          @endforeach
        </div>

        <form method="GET" action="{{ route('hotel.payment-gateway.index') }}" id="pgFilterForm">
          <input type="hidden" name="submitted" value="1">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}" id="pgPerPageHidden">

          <div class="pg-toolbar">
            <div class="pg-field pg-field--date">
              <label for="from_date">From Date:</label>
              <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
            </div>

            <div class="pg-field pg-field--date">
              <label for="to_date">To Date:</label>
              <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
            </div>

            <div class="pg-field pg-field--text">
              <label for="transaction_id">Transaction ID:</label>
              <input type="text" class="form-control" id="transaction_id" name="transaction_id" value="{{ $filters['transaction_id'] }}">
            </div>

            <div class="pg-field pg-field--text">
              <label for="booking_id">Booking ID:</label>
              <input type="text" class="form-control" id="booking_id" name="booking_id" value="{{ $filters['booking_id'] }}">
            </div>

            <div class="pg-field pg-field--text">
              <label for="guest_name">Guest Name:</label>
              <input type="text" class="form-control" id="guest_name" name="guest_name" value="{{ $filters['guest_name'] }}">
            </div>

            <div class="pg-field pg-field--select">
              <label for="order_status">Order Status:</label>
              <select class="form-control" id="order_status" name="order_status">
                @foreach($orderStatusOptions as $value => $label)
                  <option value="{{ $value }}" {{ $filters['order_status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="pg-field pg-field--text">
              <label for="payment_link">Payment Link:</label>
              <input type="text" class="form-control" id="payment_link" name="payment_link" value="{{ $filters['payment_link'] }}">
            </div>

            <div class="pg-field">
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-pg-submit">Submit</button>
            </div>
          </div>
        </form>

        @if($filters['submitted'])
          <div class="pg-table-wrap">
            <table class="pg-table">
              <thead>
                <tr>
                  @foreach($ui['columns'] ?? [] as $column)
                    <th>{{ $column['label'] }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @forelse($payments as $row)
                  <tr>
                    <td>{{ $row['booking_id'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td>{{ $row['transaction_id'] }}</td>
                    <td>{{ $row['transaction_date'] }}</td>
                    <td>{{ $row['checkin'] }}</td>
                    <td>{{ $row['checkout'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['amount'] }}</td>
                    <td>{{ $row['pg_charges'] }}</td>
                    <td>{{ $row['tax'] }}</td>
                    <td>{{ $row['net_amount'] }}</td>
                    <td>{{ $row['confirmation_id'] }}</td>
                    <td>
                      @if(! empty($row['payment_link_url']))
                        <a href="{{ $row['payment_link_url'] }}" class="pg-link" target="_blank" rel="noopener">Link</a>
                      @else
                        —
                      @endif
                    </td>
                    <td>{{ $row['settlement_date'] }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="{{ count($ui['columns'] ?? []) }}" class="pg-empty">
                      No payment records found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="pg-footer">
            <div class="pg-per-page">
              <span>Items per page:</span>
              <select class="form-control js-pg-per-page">
                @foreach($ui['per_page_options'] ?? [20, 50, 100] as $option)
                  <option value="{{ $option }}" {{ (int) $filters['per_page'] === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
              </select>
            </div>

            <div class="pg-count">
              {{ $payments->firstItem() ?? 0 }} – {{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }}
            </div>

            <div class="pg-nav">
              <a href="{{ $payments->onFirstPage() ? '#' : route('hotel.payment-gateway.index', $firstQuery) }}"
                class="pg-nav-btn {{ $payments->onFirstPage() ? 'is-disabled' : '' }}" title="First page">&laquo;</a>
              <a href="{{ $payments->onFirstPage() ? '#' : route('hotel.payment-gateway.index', $prevQuery) }}"
                class="pg-nav-btn {{ $payments->onFirstPage() ? 'is-disabled' : '' }}" title="Previous page">&lsaquo;</a>
              <a href="{{ $payments->onLastPage() ? '#' : route('hotel.payment-gateway.index', $nextQuery) }}"
                class="pg-nav-btn {{ $payments->onLastPage() ? 'is-disabled' : '' }}" title="Next page">&rsaquo;</a>
              <a href="{{ $payments->onLastPage() ? '#' : route('hotel.payment-gateway.index', $lastQuery) }}"
                class="pg-nav-btn {{ $payments->onLastPage() ? 'is-disabled' : '' }}" title="Last page">&raquo;</a>
            </div>
          </div>
        @else
          <div class="pg-empty">Select a date range and click Submit to view payment status.</div>
        @endif
      </div>
    </div>
  </div>

  @include('hotel.pms.payment-gateway.partials._modals')
@endsection

@push('scripts')
  <script>
    (function () {
      var perPageSelect = document.querySelector('.js-pg-per-page');
      var perPageHidden = document.getElementById('pgPerPageHidden');
      var filterForm = document.getElementById('pgFilterForm');

      if (perPageSelect && perPageHidden && filterForm) {
        perPageSelect.addEventListener('change', function () {
          perPageHidden.value = perPageSelect.value;
          filterForm.submit();
        });
      }
    })();
  </script>
@endpush
