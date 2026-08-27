@extends('layouts.app')

@section('title', 'Live Bookings')

@push('styles')
  <style>
    .lb-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      margin-bottom: 14px;
    }
    .lb-toolbar label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }
    .lb-toolbar .form-control {
      font-size: 13px;
      min-height: 34px;
    }
    .lb-toolbar-actions {
      margin-left: auto;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }
    .lb-search-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
    }
    .lb-search-row .form-control {
      max-width: 260px;
      font-size: 13px;
    }
    .lb-table-wrap { overflow-x: auto; }
    .lb-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1400px;
    }
    .lb-table thead th {
      background: #5a5a5a;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 10px;
      border: 1px solid #4a4a4a;
      white-space: nowrap;
      vertical-align: middle;
    }
    .lb-table tbody td {
      border: 1px solid #ddd;
      padding: 8px 10px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
      white-space: nowrap;
    }
    .lb-table tbody tr.is-cancelled td {
      color: #888;
      background: #fafafa;
    }
    .btn-lb-primary {
      background: #940000 !important;
      border-color: #940000 !important;
      color: #fff !important;
      font-weight: 600;
      font-size: 13px;
    }
    .btn-lb-primary:hover {
      background: #7a0000 !important;
      border-color: #7a0000 !important;
      color: #fff !important;
    }
    .lb-download {
      font-size: 13px;
      font-weight: 600;
      color: #333;
      text-decoration: none;
    }
    .lb-download:hover { color: #940000; text-decoration: none; }
    .lb-modal-pre {
      background: #f8f9fa;
      border: 1px solid #e5e5e5;
      border-radius: 4px;
      padding: 12px;
      font-size: 12px;
      max-height: 360px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-word;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-columns"></i> Live Bookings</h1>
      <p>OTA reservations received from Channel Manager</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item">Live Bookings</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
          <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        <form method="GET" action="{{ route('hotel.channel-manager.live-bookings') }}" id="lbFilterForm">
          <div class="lb-toolbar">
            <div>
              <label for="filter_by">Filter</label>
              <select class="form-control form-control-sm" id="filter_by" name="filter_by">
                <option value="booking_date" {{ $filters['filter_by'] === 'booking_date' ? 'selected' : '' }}>Booking Date</option>
                <option value="checkin" {{ $filters['filter_by'] === 'checkin' ? 'selected' : '' }}>Check-in Date</option>
                <option value="checkout" {{ $filters['filter_by'] === 'checkout' ? 'selected' : '' }}>Check-out Date</option>
              </select>
            </div>
            <div>
              <label for="from_date">From Date</label>
              <input type="date" class="form-control form-control-sm" id="from_date" name="from_date"
                value="{{ $filters['from_date'] }}">
            </div>
            <div>
              <label for="to_date">To Date</label>
              <input type="date" class="form-control form-control-sm" id="to_date" name="to_date"
                value="{{ $filters['to_date'] }}">
            </div>
            <div>
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-sm btn-primary btn-lb-primary d-block">Submit</button>
            </div>

            <div class="lb-toolbar-actions">
              <span class="text-muted small">Hotel code: <code>{{ $hotelCode }}</code></span>
            </div>
          </div>

          <div class="lb-search-row">
            <div class="d-flex align-items-center flex-wrap" style="gap:12px;">
              <div>
                <label class="mb-1 d-block" style="font-size:12px;font-weight:700;">Search</label>
                <input type="text" class="form-control form-control-sm" name="search"
                  value="{{ $filters['search'] }}" placeholder="Booking ID, guest, channel">
              </div>
              <div class="form-check mt-4">
                <label class="form-check-label">
                  <input type="checkbox" class="form-check-input" name="show_cancelled" value="1"
                    {{ $filters['show_cancelled'] ? 'checked' : '' }}>
                  Cancelled Bookings
                </label>
              </div>
              <div class="mt-4">
                <button type="submit" class="btn btn-sm btn-light border">Apply</button>
                @if($filters['search'] || $filters['show_cancelled'] || $filters['from_date'] !== now()->subDays(7)->format('Y-m-d') || $filters['to_date'] !== now()->format('Y-m-d'))
                  <a class="btn btn-sm btn-link" href="{{ route('hotel.channel-manager.live-bookings') }}">Reset</a>
                @endif
              </div>
            </div>
          </div>
        </form>

        <div class="lb-search-row">
          <div></div>
          <div class="d-flex align-items-center" style="gap:16px;">
            <form method="POST" action="{{ route('hotel.channel-manager.live-bookings.sync') }}" class="d-inline mb-0">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-refresh"></i> Sync from CM
              </button>
            </form>
            <a class="lb-download" href="{{ route('hotel.channel-manager.live-bookings.export', request()->query()) }}">
              <i class="fa fa-download"></i> Download
            </a>
          </div>
        </div>

        <div class="lb-table-wrap">
          <table class="lb-table">
            <thead>
              <tr>
                <th>Channel</th>
                <th>Booking ID</th>
                <th>Customer Name</th>
                <th>Payment</th>
                <th>Booked On</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Room</th>
                <th>Total Room Night</th>
                <th># of Rooms</th>
                <th>Meal Plan</th>
                <th>Price</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($bookings as $booking)
                <tr class="{{ $booking->isCancelled() ? 'is-cancelled' : '' }}">
                  <td>{{ $booking->channel }}</td>
                  <td><code>{{ $booking->booking_id }}</code></td>
                  <td>{{ $booking->guestName() }}</td>
                  <td>{{ $booking->paymentLabel() }}</td>
                  <td>{{ $booking->bookedOnLabel() }}</td>
                  <td>{{ $booking->checkinLabel() }}</td>
                  <td>{{ $booking->checkoutLabel() }}</td>
                  <td>{{ $booking->roomLabel() }}</td>
                  <td>{{ $booking->roomNightCount() ?? '—' }}</td>
                  <td>{{ $booking->roomCount() ?: '—' }}</td>
                  <td>{{ $booking->mealPlanLabel() }}</td>
                  <td>{{ $booking->priceLabel() }}</td>
                  <td><span class="badge {{ $booking->statusBadgeClass() }}">{{ $booking->statusLabel() }}</span></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-lb-view"
                      data-booking-id="{{ $booking->booking_id }}"
                      data-payload="{{ e(json_encode($booking->payload ?? [], JSON_UNESCAPED_UNICODE)) }}">
                      View
                    </button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="14" class="text-center text-muted py-4">
                    No bookings in this date range.
                    Run <strong>Sync from CM</strong> or <code>php artisan cm:test-apis</code> to add sample data.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
          <small class="text-muted">
            Showing {{ $bookings->firstItem() ?? 0 }} to {{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }} entries
          </small>
          {{ $bookings->links() }}
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="lbDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background:#940000;color:#fff;">
          <h5 class="modal-title">Booking <span id="lbModalBookingId"></span></h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <pre class="lb-modal-pre" id="lbModalPayload"></pre>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.querySelectorAll('.js-lb-view').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var payload = {};
        try {
          payload = JSON.parse(btn.getAttribute('data-payload') || '{}');
        } catch (e) {
          payload = {};
        }

        document.getElementById('lbModalBookingId').textContent = btn.getAttribute('data-booking-id') || '';
        document.getElementById('lbModalPayload').textContent = JSON.stringify(payload, null, 2);
        $('#lbDetailModal').modal('show');
      });
    });
  </script>
@endpush
