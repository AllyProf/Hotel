@extends('layouts.app')

@section('title', $ui['title'] ?? 'Invoice Report')

@push('styles')
  @include('hotel.pms.payment-gateway.partials._report-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-link"></i> {{ $ui['title'] ?? 'Invoice Report' }}</h1>
      <p>Payment gateway invoices by period</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.payment-gateway.index') }}">Payment Status</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Invoice Report' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile pg-page">
        <div class="pg-header">
          <h3>{{ $ui['title'] ?? 'Invoice Report' }}</h3>
          <a href="{{ route('hotel.payment-gateway.index') }}" class="btn btn-sm btn-pg">Back</a>
        </div>

        <form method="GET" action="{{ route('hotel.payment-gateway.invoices') }}" id="pgReportForm">
          <input type="hidden" name="submitted" value="1">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}" id="pgPerPageHidden">

          <div class="pg-toolbar pg-toolbar--report">
            <div class="pg-field pg-field--date">
              <label for="from_date">Start Date:</label>
              <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
            </div>

            <div class="pg-field pg-field--date">
              <label for="to_date">End Date:</label>
              <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
            </div>

            <div class="pg-field">
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-pg-submit">Submit</button>
            </div>
          </div>
        </form>

        <div class="pg-table-wrap pg-table-wrap--compact">
          <table class="pg-table pg-table--compact">
            <thead>
              <tr>
                @foreach($ui['columns'] ?? [] as $column)
                  <th>{{ $column['label'] }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $row)
                <tr>
                  <td>{{ $row['invoice_date'] }}</td>
                  <td>{{ $row['start_date'] }}</td>
                  <td>{{ $row['end_date'] }}</td>
                  <td>{{ $row['invoice_num'] }}</td>
                  <td>{{ $row['type'] }}</td>
                  <td>
                    <a href="#" class="pg-link" onclick="return false;">View</a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="{{ count($ui['columns'] ?? []) }}" class="pg-empty-cell">&nbsp;</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @include('hotel.pms.payment-gateway.partials._pagination', [
          'paginator' => $rows,
          'routeName' => 'hotel.payment-gateway.invoices',
          'filters' => $filters,
          'perPageOptions' => config('hotel_pms.payment_gateway.per_page_options', [20, 50, 100]),
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
      var reportForm = document.getElementById('pgReportForm');

      if (perPageSelect && perPageHidden && reportForm) {
        perPageSelect.addEventListener('change', function () {
          perPageHidden.value = perPageSelect.value;
          reportForm.submit();
        });
      }
    })();
  </script>
@endpush
