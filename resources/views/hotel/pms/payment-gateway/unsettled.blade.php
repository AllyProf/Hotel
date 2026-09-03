@extends('layouts.app')

@section('title', $ui['unsettled']['title'] ?? 'Unsettled Payments')

@push('styles')
  @include('hotel.pms.payment-gateway.partials._report-styles')
@endpush

@section('content')
  @php
    $queryBase = request()->except('page');
    $firstQuery = array_merge($queryBase, ['page' => 1]);
    $prevQuery = array_merge($queryBase, ['page' => max(1, $payments->currentPage() - 1)]);
    $nextQuery = array_merge($queryBase, ['page' => min($payments->lastPage(), $payments->currentPage() + 1)]);
    $lastQuery = array_merge($queryBase, ['page' => max(1, $payments->lastPage())]);
    $columns = $ui['columns'] ?? [];
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-link"></i> {{ $ui['unsettled']['title'] ?? 'Unsettled Payments' }}</h1>
      <p>Paid and pending payments awaiting settlement</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.payment-gateway.index') }}">Payment Status</a></li>
      <li class="breadcrumb-item">{{ $ui['unsettled']['title'] ?? 'Unsettled Payments' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile pg-page">
        <div class="pg-header">
          <h3>{{ $ui['unsettled']['title'] ?? 'Unsettled Payments' }}</h3>
          <a href="{{ route('hotel.payment-gateway.index') }}" class="btn btn-sm btn-pg">Back</a>
        </div>

        <form method="GET" action="{{ route('hotel.payment-gateway.unsettled') }}" id="pgUnsettledForm">
          <input type="hidden" name="submitted" value="1">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}" id="pgPerPageHidden">

          <div class="pg-toolbar pg-toolbar--report">
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

            <div class="pg-field">
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-pg-submit">Submit</button>
            </div>
          </div>
        </form>

        <div class="pg-table-wrap">
          <table class="pg-table">
            <thead>
              <tr>
                @foreach($columns as $column)
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
                  <td colspan="{{ count($columns) }}" class="pg-empty">
                    No unsettled payments found for the selected filters.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @include('hotel.pms.payment-gateway.partials._pagination', [
          'paginator' => $payments,
          'routeName' => 'hotel.payment-gateway.unsettled',
          'filters' => $filters,
          'perPageOptions' => $ui['per_page_options'] ?? [20, 50, 100],
        ])
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var perPageSelect = document.querySelector('.js-pg-report-per-page');
      var perPageHidden = document.getElementById('pgPerPageHidden');
      var filterForm = document.getElementById('pgUnsettledForm');

      if (perPageSelect && perPageHidden && filterForm) {
        perPageSelect.addEventListener('change', function () {
          perPageHidden.value = perPageSelect.value;
          filterForm.submit();
        });
      }
    })();
  </script>
@endpush
