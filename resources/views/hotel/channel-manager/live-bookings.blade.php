@extends('layouts.app')

@section('title', 'Live Bookings')

@push('styles')
  <style>
    :root {
      --lb-brand: #940000;
      --lb-brand-dark: #7a0000;
    }

    .lb-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
      padding-bottom: 14px;
      border-bottom: 1px solid #eee;
    }

    .lb-filters {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      flex: 1;
      min-width: 0;
    }

    .lb-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
      white-space: nowrap;
    }

    .lb-field .form-control {
      font-size: 13px;
      min-height: 34px;
    }

    .lb-field--filter select {
      min-width: 130px;
    }

    .lb-field--search .form-control {
      min-width: 220px;
      width: 220px;
    }

    .lb-field--checkbox label.lb-check-label {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      min-height: 34px;
      margin: 0;
      font-size: 13px;
      font-weight: 500;
      color: #444;
      cursor: pointer;
      white-space: nowrap;
    }

    .lb-field--checkbox .form-check-input {
      margin: 0;
      position: static;
    }

    .lb-field--actions {
      display: flex;
      align-items: flex-end;
      gap: 8px;
    }

    .lb-toolbar-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }

    .lb-hotel-code {
      font-size: 12px;
      color: #888;
      white-space: nowrap;
    }

    .lb-hotel-code code {
      color: #940000;
      background: #fef2f2;
      padding: 2px 6px;
      border-radius: 4px;
    }

    .lb-toolbar-btn {
      width: 34px;
      height: 34px;
      border: 1px solid #d1d5db;
      background: #fff;
      color: #555;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      cursor: pointer;
      padding: 0;
      transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .lb-toolbar-btn:hover {
      background: rgba(148, 0, 0, 0.08);
      border-color: #940000;
      color: #940000;
      text-decoration: none;
    }

    .lb-toolbar-divider {
      width: 1px;
      height: 34px;
      background: #e5e7eb;
      align-self: flex-end;
      margin: 0 2px;
    }

    .lb-table-wrap { overflow-x: auto; }

    .lb-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1200px;
    }

    .lb-table thead th {
      background: var(--lb-brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 10px;
      border: 1px solid var(--lb-brand-dark);
      white-space: nowrap;
      vertical-align: middle;
    }

    .lb-table tbody td {
      border: 1px solid #ddd;
      padding: 10px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
    }

    .lb-table tbody tr:hover td { background: #fafafa; }

    .lb-table tbody tr.is-cancelled td {
      color: #888;
      background: #fafafa;
    }

    .lb-table tbody tr.is-live td {
      background: #fff;
    }

    .lb-table tbody tr.is-live td:first-child {
      box-shadow: inset 3px 0 0 #22c55e;
    }

    .lb-channel-cell {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .lb-live-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #22c55e;
      flex-shrink: 0;
      animation: lb-live-pulse 1.8s infinite;
    }

    @keyframes lb-live-pulse {
      0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
      70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
      100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }

    .lb-source {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 4px;
      white-space: nowrap;
    }

    .lb-source-direct { background: #fef2f2; color: #940000; border: 1px solid #fecaca; }
    .lb-source-booking { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .lb-source-expedia { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .lb-source-agoda { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .lb-source-airbnb { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }
    .lb-source-goibibo { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .lb-source-mmt { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .lb-source-trip { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .lb-source-cleartrip { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
    .lb-source-hostelworld { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .lb-source-hotelbeds { background: #ecfdf5; color: #047857; border: 1px solid #bbf7d0; }
    .lb-source-traveloka { background: #eff6ff; color: #0284c7; border: 1px solid #bae6fd; }
    .lb-source-yatra { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .lb-source-ota { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .lb-source-neutral { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

    .lb-guest-name {
      font-weight: 700;
      color: #222;
    }

    .lb-booking-id {
      font-size: 12px;
      font-weight: 600;
      color: #333;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 2px 6px;
      white-space: nowrap;
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
      white-space: nowrap;
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

    .lb-icon-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      padding: 0;
      color: var(--lb-brand) !important;
      border-color: var(--lb-brand) !important;
      font-size: 12px;
    }

    .lb-icon-btn:hover {
      background: var(--lb-brand) !important;
      color: #fff !important;
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
      color: var(--lb-brand);
      margin-bottom: 8px;
    }

    .lb-live-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      font-weight: 700;
      color: #047857;
      background: #ecfdf3;
      border: 1px solid #bbf7d0;
      border-radius: 999px;
      padding: 4px 12px;
    }
    .lb-live-badge__dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      animation: lb-live-pulse 1.8s infinite;
    }
    .lb-live-badge.is-checking { color: #6b7280; background: #f3f4f6; border-color: #e5e7eb; }
    .lb-live-badge.is-checking .lb-live-badge__dot { background: #9ca3af; animation: none; }

    .lb-toast-wrap {
      position: fixed;
      top: 72px;
      right: 20px;
      z-index: 1090;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
      max-width: min(360px, calc(100vw - 40px));
    }

    .lb-toast {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 8px;
      background: #fff;
      border: 1px solid #bbf7d0;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
      color: #065f46;
      font-size: 13px;
      line-height: 1.45;
      opacity: 0;
      transform: translateY(-8px);
      transition: opacity 0.2s ease, transform 0.2s ease;
      pointer-events: auto;
    }

    .lb-toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    .lb-toast__icon {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      background: #ecfdf3;
      color: #047857;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 13px;
    }

    .lb-toast__title {
      font-weight: 700;
      color: #064e3b;
      margin-bottom: 2px;
    }

    .lb-toast__meta {
      font-size: 12px;
      color: #047857;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-columns"></i> Live Bookings</h1>
      <p>OTA reservations from Channel Manager webhooks · checks for updates every {{ $pollIntervalSeconds }} seconds</p>
    </div>
    <div class="d-flex align-items-center" style="gap:12px;">
      <span class="lb-live-badge" id="lbLiveBadge">
        <span class="lb-live-badge__dot"></span>
        <span id="lbLiveBadgeText">Live</span>
      </span>
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
            <div class="lb-filters">
              <div class="lb-field lb-field--filter">
                <label for="filter_by">Filter</label>
                <select class="form-control form-control-sm" id="filter_by" name="filter_by">
                  <option value="booking_date" {{ $filters['filter_by'] === 'booking_date' ? 'selected' : '' }}>Booking Date</option>
                  <option value="checkin" {{ $filters['filter_by'] === 'checkin' ? 'selected' : '' }}>Check-in Date</option>
                  <option value="checkout" {{ $filters['filter_by'] === 'checkout' ? 'selected' : '' }}>Check-out Date</option>
                </select>
              </div>

              <div class="lb-field">
                <label for="from_date">From Date</label>
                <input type="date" class="form-control form-control-sm" id="from_date" name="from_date"
                  value="{{ $filters['from_date'] }}">
              </div>

              <div class="lb-field">
                <label for="to_date">To Date</label>
                <input type="date" class="form-control form-control-sm" id="to_date" name="to_date"
                  value="{{ $filters['to_date'] }}">
              </div>

              <div class="lb-field">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-sm btn-primary btn-lb-primary d-block">Submit</button>
              </div>

              <span class="lb-toolbar-divider" aria-hidden="true"></span>

              <div class="lb-field lb-field--search">
                <label for="lb_search">Search</label>
                <input type="text" class="form-control form-control-sm" id="lb_search" name="search"
                  value="{{ $filters['search'] }}" placeholder="Booking ID, guest, channel">
              </div>

              <div class="lb-field lb-field--checkbox">
                <label>&nbsp;</label>
                <label class="lb-check-label">
                  <input type="checkbox" class="form-check-input" name="cancelled_only" value="1"
                    {{ $filters['cancelled_only'] ? 'checked' : '' }}>
                  Cancelled only
                </label>
              </div>

              <div class="lb-field lb-field--actions">
                <label>&nbsp;</label>
                <div class="d-flex align-items-center" style="gap:8px;">
                  <button type="submit" class="btn btn-sm btn-light border">Apply</button>
                  @if($filters['search'] || $filters['cancelled_only'] || $filters['from_date'] !== now()->subDays(7)->format('Y-m-d') || $filters['to_date'] !== now()->format('Y-m-d'))
                    <a class="btn btn-sm btn-link px-1" href="{{ route('hotel.channel-manager.live-bookings') }}">Reset</a>
                  @endif
                </div>
              </div>
            </div>

            <div class="lb-toolbar-actions">
              <span class="lb-hotel-code">Hotel code: <code>{{ $hotelCode }}</code></span>

              <button type="submit" form="lbSyncForm" class="lb-toolbar-btn" title="Sync from CM">
                <i class="fa fa-refresh"></i>
              </button>

              <a class="lb-toolbar-btn" href="{{ route('hotel.channel-manager.live-bookings.export', request()->query()) }}" title="Download">
                <i class="fa fa-download"></i>
              </a>
            </div>
          </div>
        </form>

        <form method="POST" action="{{ route('hotel.channel-manager.live-bookings.sync') }}" class="d-none" id="lbSyncForm">
          @csrf
        </form>

        <div class="lb-table-wrap">
          <table class="lb-table table table-hover table-bordered mb-0">
            <thead>
              <tr>
                <th>Channel</th>
                <th>Booking ID</th>
                <th>Guest</th>
                <th>Payment</th>
                <th>Booked On</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Room</th>
                <th>Nights</th>
                <th>Rooms</th>
                <th>Meal</th>
                <th>Price</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($bookings as $booking)
                @php
                  $isLive = $booking->isRecentlyReceived();
                  $sourceClass = str_replace('rd-source', 'lb-source', $booking->sourceBadgeClass());
                @endphp
                <tr class="{{ $booking->isCancelled() ? 'is-cancelled' : '' }}{{ $isLive && ! $booking->isCancelled() ? ' is-live' : '' }}">
                  <td>
                    @include('hotel.channel-manager.partials._lb-channel-badge', [
                      'label' => $booking->sourceDisplayLabel(),
                      'badgeClass' => $sourceClass,
                      'isLive' => $isLive && ! $booking->isCancelled(),
                    ])
                  </td>
                  <td><span class="lb-booking-id">{{ $booking->booking_id }}</span></td>
                  <td><span class="lb-guest-name">{{ $booking->guestName() }}</span></td>
                  <td><span class="lb-pay {{ str_replace('rd-pay', 'lb-pay', $booking->paymentBadgeClass()) }}">{{ $booking->paymentLabel() }}</span></td>
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
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td><span class="lb-price">{{ $booking->priceLabel() }}</span></td>
                  <td>
                    <span class="lb-status {{ $booking->status === 'cancelled' ? 'is-cancelled' : ($booking->status === 'modified' ? 'is-modified' : 'is-confirmed') }}">
                      {{ $booking->statusLabel() }}
                    </span>
                  </td>
                  <td>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary lb-icon-btn js-lb-view"
                            title="View booking"
                            aria-label="View booking"
                            data-booking-id="{{ $booking->booking_id }}"
                            data-record-id="{{ $booking->id }}">
                      <i class="fa fa-eye"></i>
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

  <div class="lb-toast-wrap" id="lbToastWrap" aria-live="polite" aria-atomic="true"></div>
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

      var pollUrl = @json(route('hotel.channel-manager.live-bookings.poll'));
      var lastSignature = @json($pollSignature);
      var lastTotal = @json($pollTotal);
      var pollMs = @json($pollIntervalSeconds * 1000);
      var reloadDelayMs = @json($pollReloadDelayMs);
      var liveBadge = document.getElementById('lbLiveBadge');
      var liveBadgeText = document.getElementById('lbLiveBadgeText');
      var toastWrap = document.getElementById('lbToastWrap');
      var isReloading = false;

      function buildChangeMessage(data) {
        var bookingRef = data.latest_booking_id ? ': ' + data.latest_booking_id : '';

        if (typeof data.total === 'number' && data.total > lastTotal) {
          return {
            title: 'New booking received',
            meta: (data.latest_booking_id || 'Refreshing list…') + ' · via webhook or sync'
          };
        }

        if (data.latest_status === 'cancelled') {
          return {
            title: 'Booking cancelled',
            meta: bookingRef.replace(/^: /, '') || 'Refreshing list…'
          };
        }

        if (data.latest_status === 'modified') {
          return {
            title: 'Booking modified',
            meta: bookingRef.replace(/^: /, '') || 'Refreshing list…'
          };
        }

        return {
          title: 'Bookings updated',
          meta: bookingRef.replace(/^: /, '') || 'Refreshing list…'
        };
      }

      function showToast(title, meta) {
        if (!toastWrap) {
          return;
        }

        var toast = document.createElement('div');
        toast.className = 'lb-toast';
        toast.innerHTML =
          '<span class="lb-toast__icon"><i class="fa fa-bell"></i></span>' +
          '<span><div class="lb-toast__title"></div><div class="lb-toast__meta"></div></span>';

        toast.querySelector('.lb-toast__title').textContent = title;
        toast.querySelector('.lb-toast__meta').textContent = meta;
        toastWrap.appendChild(toast);

        requestAnimationFrame(function () {
          toast.classList.add('is-visible');
        });
      }

      function scheduleReload(data) {
        if (isReloading) {
          return;
        }

        isReloading = true;
        var message = buildChangeMessage(data);
        showToast(message.title, message.meta);

        window.setTimeout(function () {
          window.location.reload();
        }, reloadDelayMs);
      }

      function pollBookings() {
        if (isReloading || $('#lbDetailModal').hasClass('show')) {
          return;
        }

        liveBadge.classList.add('is-checking');
        liveBadgeText.textContent = 'Checking…';

        fetch(pollUrl + window.location.search, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (data.signature && data.signature !== lastSignature) {
              scheduleReload(data);
              return;
            }

            liveBadge.classList.remove('is-checking');
            liveBadgeText.textContent = 'Live';
          })
          .catch(function () {
            liveBadge.classList.remove('is-checking');
            liveBadgeText.textContent = 'Live';
          });
      }

      window.setInterval(pollBookings, pollMs);
      window.setTimeout(pollBookings, 3000);
    })();
  </script>
@endpush
