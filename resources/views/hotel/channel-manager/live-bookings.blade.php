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
      max-height: 220px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-word;
      margin-bottom: 0;
    }
    .lb-detail-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
    }
    .lb-detail-table th,
    .lb-detail-table td {
      border: 1px solid #e5e7eb;
      padding: 8px 10px;
      font-size: 13px;
      vertical-align: top;
    }
    .lb-detail-table th {
      width: 34%;
      background: #f9fafb;
      color: #374151;
      font-weight: 700;
    }
    .lb-detail-raw summary {
      cursor: pointer;
      font-size: 13px;
      font-weight: 700;
      color: #940000;
      margin-bottom: 8px;
    }
    .lb-channel {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      max-width: 100%;
    }
    .lb-channel__live {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6);
      animation: lb-live-pulse 1.8s infinite;
      flex-shrink: 0;
    }
    @keyframes lb-live-pulse {
      0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
      70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
      100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .lb-channel__chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 4px 10px 4px 4px;
      border-radius: 999px;
      border: 1px solid color-mix(in srgb, var(--lb-brand) 28%, #fff);
      background: color-mix(in srgb, var(--lb-brand) 10%, #fff);
      min-height: 32px;
      max-width: 100%;
    }
    .lb-channel__logo {
      width: 24px;
      height: 24px;
      object-fit: contain;
      border-radius: 50%;
      background: #fff;
      padding: 2px;
      flex-shrink: 0;
    }
    .lb-channel__fallback {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--lb-brand);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .lb-channel__name {
      font-size: 12px;
      font-weight: 700;
      color: var(--lb-brand);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 120px;
    }
    .lb-table tbody tr.is-live td {
      background: #fcfffc;
    }
    .lb-table tbody tr.is-live td:first-child {
      box-shadow: inset 3px 0 0 var(--lb-brand, #940000);
    }
    .lb-booking-id {
      font-size: 12px;
      font-weight: 600;
      color: #333;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 2px 6px;
    }
    .lb-pay {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 999px;
      white-space: nowrap;
    }
    .lb-pay-prepaid { background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; }
    .lb-pay-pah { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .lb-pay-neutral { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
    .lb-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }
    .lb-status.is-confirmed { background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; }
    .lb-status.is-modified { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .lb-status.is-cancelled { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
    .lb-price {
      font-weight: 700;
      color: #111827;
    }
    .lb-meal {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 4px;
      background: #eef2ff;
      color: #3730a3;
      border: 1px solid #c7d2fe;
    }
    .btn-lb-view {
      color: #940000 !important;
      border-color: #940000 !important;
      font-size: 12px;
      font-weight: 600;
    }
    .btn-lb-view:hover {
      background: #940000 !important;
      color: #fff !important;
    }
  </style>
@endpush

@section('content')
  @inject('otaLogos', 'App\Services\OtaLogoService')

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
                  <input type="checkbox" class="form-check-input" name="cancelled_only" value="1"
                    {{ $filters['cancelled_only'] ? 'checked' : '' }}>
                  Cancelled only
                </label>
              </div>
              <div class="mt-4">
                <button type="submit" class="btn btn-sm btn-light border">Apply</button>
                @if($filters['search'] || $filters['cancelled_only'] || $filters['from_date'] !== now()->subDays(7)->format('Y-m-d') || $filters['to_date'] !== now()->format('Y-m-d'))
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
                @php
                  $channelBrand = $otaLogos->presentationForChannel($booking->channel);
                  $isLive = $booking->isRecentlyReceived();
                @endphp
                <tr class="{{ $booking->isCancelled() ? 'is-cancelled' : '' }}{{ $isLive && ! $booking->isCancelled() ? ' is-live' : '' }}"
                  @if($isLive && ! $booking->isCancelled()) style="--lb-brand: {{ $channelBrand['brand_color'] }};" @endif>
                  <td>
                    @include('hotel.channel-manager.partials._lb-channel-badge', [
                      'brand' => $channelBrand,
                      'channel' => $booking->channel,
                      'isLive' => $isLive && ! $booking->isCancelled(),
                    ])
                  </td>
                  <td><span class="lb-booking-id">{{ $booking->booking_id }}</span></td>
                  <td><strong>{{ $booking->guestName() }}</strong></td>
                  <td><span class="lb-pay {{ $booking->paymentBadgeClass() }}">{{ $booking->paymentLabel() }}</span></td>
                  <td>{{ $booking->bookedOnLabel() }}</td>
                  <td>{{ $booking->checkinLabel() }}</td>
                  <td>{{ $booking->checkoutLabel() }}</td>
                  <td>{{ $booking->roomLabel() }}</td>
                  <td>{{ $booking->roomNightCount() ?? '—' }}</td>
                  <td>{{ $booking->roomCount() ?: '—' }}</td>
                  <td>
                    @if($booking->mealPlanLabel() !== '—')
                      <span class="lb-meal">{{ $booking->mealPlanLabel() }}</span>
                    @else
                      —
                    @endif
                  </td>
                  <td><span class="lb-price">{{ $booking->priceLabel() }}</span></td>
                  <td>
                    <span class="lb-status {{ $booking->status === 'cancelled' ? 'is-cancelled' : ($booking->status === 'modified' ? 'is-modified' : 'is-confirmed') }}">
                      {{ $booking->statusLabel() }}
                    </span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-lb-view js-lb-view"
                      data-booking-id="{{ $booking->booking_id }}"
                      data-record-id="{{ $booking->id }}">
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
          <div id="lbModalSummary"></div>
          <details class="lb-detail-raw mt-3">
            <summary>Raw webhook payload</summary>
            <pre class="lb-modal-pre" id="lbModalPayload"></pre>
          </details>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script type="application/json" id="lb-bookings-data">@json($bookingDetails)</script>
  <script>
    (function () {
      var details = {};
      try {
        details = JSON.parse(document.getElementById('lb-bookings-data').textContent || '{}');
      } catch (e) {
        details = {};
      }

      function renderSummary(summary) {
        var html = '<table class="lb-detail-table"><tbody>';
        Object.keys(summary || {}).forEach(function (key) {
          html += '<tr><th>' + key + '</th><td>' + String(summary[key]) + '</td></tr>';
        });
        html += '</tbody></table>';
        return html;
      }

      document.querySelectorAll('.js-lb-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var recordId = btn.getAttribute('data-record-id');
          var detail = details[recordId] || { summary: {}, raw: {} };

          document.getElementById('lbModalBookingId').textContent = btn.getAttribute('data-booking-id') || '';
          document.getElementById('lbModalSummary').innerHTML = renderSummary(detail.summary || {});
          document.getElementById('lbModalPayload').textContent = JSON.stringify(detail.raw || {}, null, 2);
          $('#lbDetailModal').modal('show');
        });
      });
    })();
  </script>
@endpush
