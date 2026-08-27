@extends('layouts.app')

@section('title', 'OTA Commission')

@push('styles')
  <style>
    .ota-commission-filter {
      border-top: 3px solid #940000;
      padding: 20px;
    }
    .ota-commission-filter .control-label {
      font-weight: 700;
      margin-bottom: 6px;
    }
    .ota-date-input-wrap {
      position: relative;
    }
    .ota-date-input-wrap .fa-calendar {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
      pointer-events: none;
    }
    .ota-date-input-wrap .form-control {
      padding-left: 32px;
    }
    .ota-commission-empty {
      padding: 18px 4px 6px;
      font-weight: 700;
      font-size: 15px;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-percent"></i> OTAs <small class="text-muted">OTA Commission</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">OTAs</a></li>
      <li class="breadcrumb-item"><a href="#">OTA Commission</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="ota-commission-filter">
          <form method="GET" action="{{ route('hotel.channel-manager.ota-commission') }}">
            <input type="hidden" name="submitted" value="1">
            <div class="row align-items-end">
              <div class="col-md-3 col-sm-6">
                <div class="form-group mb-md-0">
                  <label class="control-label">Start Date:</label>
                  <div class="ota-date-input-wrap">
                    <i class="fa fa-calendar"></i>
                    <input class="form-control" type="date" name="start_date" value="{{ $filters['start_date'] }}" required>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-sm-6">
                <div class="form-group mb-md-0">
                  <label class="control-label">End Date:</label>
                  <div class="ota-date-input-wrap">
                    <i class="fa fa-calendar"></i>
                    <input class="form-control" type="date" name="end_date" value="{{ $filters['end_date'] }}" required>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-sm-6">
                <div class="form-group mb-md-0">
                  <label class="control-label">Filter:</label>
                  <select class="form-control" name="filter_type" required>
                    @foreach($filterTypes as $value => $label)
                      <option value="{{ $value }}" {{ $filters['filter_type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-3 col-sm-6">
                <div class="form-group mb-md-0">
                  <button type="submit" class="btn btn-primary btn-block">Submit</button>
                </div>
              </div>
            </div>
          </form>

          @if($submitted)
            @if($commissions && $commissions->isEmpty())
              <div class="ota-commission-empty">Data Not Found !</div>
            @elseif($commissions && $commissions->isNotEmpty())
              <div class="table-responsive mt-4">
                <table class="table table-hover table-bordered mb-0">
                  <thead>
                    <tr>
                      <th>OTA</th>
                      <th>Booking ID</th>
                      <th>Guest</th>
                      <th>Check-in</th>
                      <th>Check-out</th>
                      <th>Amount</th>
                      <th>Commission</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($commissions as $row)
                      <tr>
                        <td>{{ $row['ota'] }}</td>
                        <td>{{ $row['booking_id'] }}</td>
                        <td>{{ $row['guest'] }}</td>
                        <td>{{ $row['checkin'] }}</td>
                        <td>{{ $row['checkout'] }}</td>
                        <td>{{ $row['amount'] }}</td>
                        <td>{{ $row['commission'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.querySelector('.ota-commission-filter form');
      if (!form) return;
      form.addEventListener('submit', function (e) {
        var start = form.querySelector('[name=start_date]').value;
        var end = form.querySelector('[name=end_date]').value;
        if (start && end && start > end) {
          e.preventDefault();
          if (typeof swal === 'function') {
            swal('Invalid dates', 'Start date cannot be after end date.', 'warning');
          }
        }
      });
    })();
  </script>
@endpush
