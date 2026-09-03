@extends('layouts.app')

@section('title', $ui['title'] ?? 'Reservation Data')

@push('styles')
  <style>
    :root {
      --rd-brand: #940000;
      --rd-brand-dark: #7a0000;
      --rd-brand-soft: rgba(148, 0, 0, 0.08);
      --rd-ease: cubic-bezier(0.16, 1, 0.3, 1);
    }

    .rd-page-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding-bottom: 14px;
      margin-bottom: 14px;
      border-bottom: 1px solid #eee;
    }

    .rd-page-header h3 {
      margin: 0;
      font-size: 18px;
      font-weight: 400;
      color: #333;
    }

    .rd-header-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }

    .btn-rd-primary {
      background: var(--rd-brand) !important;
      border-color: var(--rd-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 6px 14px;
    }

    .btn-rd-primary:hover {
      background: var(--rd-brand-dark) !important;
      border-color: var(--rd-brand-dark) !important;
      color: #fff !important;
    }

    .rd-filters {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 12px;
      flex: 1;
      min-width: 0;
    }

    .rd-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
      padding-bottom: 14px;
      border-bottom: 1px solid #eee;
    }

    .rd-toolbar-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-left: auto;
    }

    .rd-icon-btn {
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
      transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
      padding: 0;
    }

    .rd-icon-btn:hover,
    .rd-icon-btn.is-active {
      background: var(--rd-brand-soft);
      border-color: var(--rd-brand);
      color: var(--rd-brand);
      text-decoration: none;
    }

    .rd-icon-btn.is-spinning i {
      animation: rd-spin 0.8s linear infinite;
    }

    @keyframes rd-spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .rd-search-wrap {
      width: 0;
      max-width: 0;
      opacity: 0;
      overflow: hidden;
      transition: max-width 0.45s var(--rd-ease), opacity 0.35s var(--rd-ease), margin 0.35s var(--rd-ease);
      margin-left: 0;
    }

    .rd-search-wrap.is-open {
      width: auto;
      max-width: 260px;
      opacity: 1;
      margin-left: 4px;
    }

    .rd-search-input {
      width: 100%;
      min-width: 200px;
      height: 34px;
      font-size: 13px;
      border: 1px solid #d1d5db;
      border-radius: 4px;
      padding: 6px 12px;
      background: #fff;
    }

    .rd-search-input:focus {
      border-color: var(--rd-brand);
      outline: none;
      box-shadow: 0 0 0 2px rgba(148, 0, 0, 0.12);
    }

    .rd-filters label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .rd-filters .form-control {
      font-size: 13px;
      min-height: 34px;
    }

    .rd-date-field {
      position: relative;
      display: inline-flex;
      align-items: stretch;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
      height: 34px;
      min-width: 178px;
      cursor: pointer;
    }

    .rd-date-field__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      min-width: 38px;
      background: #f6f6f6;
      border-right: 1px solid #e5e5e5;
      color: var(--rd-brand);
      font-size: 14px;
      pointer-events: none;
      flex-shrink: 0;
    }

    .rd-date-field input[type="date"] {
      flex: 1;
      width: 140px;
      min-width: 140px;
      height: 100%;
      padding: 0 10px;
      font-size: 13px;
      font-weight: 500;
      color: #333;
      border: none;
      background: transparent;
      cursor: pointer;
      -webkit-appearance: none;
      appearance: none;
    }

    .rd-date-field input[type="date"]:focus {
      outline: none;
    }

    .rd-date-field:focus-within {
      border-color: var(--rd-brand);
      box-shadow: 0 0 0 1px rgba(148, 0, 0, 0.12);
    }

    .rd-date-field input[type="date"]::-webkit-calendar-picker-indicator {
      display: none;
      -webkit-appearance: none;
      appearance: none;
    }

    .rd-date-field input[type="date"]::-webkit-inner-spin-button {
      display: none;
    }

    .rd-filter-row {
      display: none;
    }

    .rd-table-wrap { overflow-x: auto; }

    .rd-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1600px;
    }

    .rd-table thead th {
      background: var(--rd-brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 10px;
      border: 1px solid var(--rd-brand-dark);
      white-space: nowrap;
      vertical-align: middle;
    }

    .rd-table tbody td {
      border: 1px solid #ddd;
      padding: 8px 10px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
      white-space: nowrap;
    }

    .rd-table tbody tr.is-cancelled td {
      color: #991b1b;
      background: #fef2f2;
    }

    .rd-table tbody tr.is-cancelled td:first-child {
      box-shadow: inset 3px 0 0 var(--rd-brand);
    }

    .rd-table tbody tr.is-cancelled .rd-booking-id {
      color: #991b1b;
      background: #fee2e2;
      border-color: #fecaca;
    }

    .rd-table tbody tr.is-cancelled strong {
      color: #991b1b;
    }

    .rd-booking-id {
      font-size: 12px;
      font-weight: 600;
      color: #333;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 2px 6px;
    }

    .rd-status {
      display: inline-flex;
      align-items: center;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .rd-status.is-confirmed { background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; }
    .rd-status.is-modified { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .rd-status.is-cancelled { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    .rd-pay {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .rd-pay-prepaid { background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; }
    .rd-pay-pah { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .rd-pay-neutral { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

    .rd-meal {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 4px;
      background: #eef2ff;
      color: #3730a3;
      border: 1px solid #c7d2fe;
      white-space: nowrap;
    }

    .rd-source {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 4px;
      white-space: nowrap;
      letter-spacing: 0.01em;
    }

    .rd-source-direct { background: #fef2f2; color: #940000; border: 1px solid #fecaca; }
    .rd-source-booking { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .rd-source-expedia { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .rd-source-agoda { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .rd-source-airbnb { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }
    .rd-source-goibibo { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .rd-source-mmt { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .rd-source-trip { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .rd-source-cleartrip { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
    .rd-source-hostelworld { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .rd-source-hotelbeds { background: #ecfdf5; color: #047857; border: 1px solid #bbf7d0; }
    .rd-source-traveloka { background: #eff6ff; color: #0284c7; border: 1px solid #bae6fd; }
    .rd-source-yatra { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .rd-source-ota { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .rd-source-neutral { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

    .rd-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 14px;
      padding-top: 10px;
      border-top: 1px solid #eee;
      font-size: 13px;
      color: #555;
    }

    .rd-footer select {
      width: auto;
      min-width: 70px;
      font-size: 13px;
      height: 32px;
    }

    .rd-footer-nav {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .rd-footer-nav .btn-nav {
      width: 30px;
      height: 30px;
      padding: 0;
      border: 1px solid #ccc;
      background: #fff;
      color: #555;
      border-radius: 2px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .rd-footer-nav .btn-nav:hover { background: var(--rd-brand-soft); color: var(--rd-brand); border-color: var(--rd-brand); text-decoration: none; }
    .rd-footer-nav .btn-nav.is-disabled { opacity: 0.45; pointer-events: none; }

    .btn-rd-view {
      color: var(--rd-brand) !important;
      border-color: var(--rd-brand) !important;
      font-size: 12px;
      font-weight: 600;
    }

    .btn-rd-view:hover {
      background: var(--rd-brand) !important;
      color: #fff !important;
    }

    .rd-modal-pre {
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

    .rd-detail-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
    }

    .rd-detail-table th,
    .rd-detail-table td {
      border: 1px solid #e5e7eb;
      padding: 8px 10px;
      font-size: 13px;
      vertical-align: top;
    }

    .rd-detail-table th {
      width: 34%;
      background: #f9fafb;
      color: #374151;
      font-weight: 700;
    }

    .rd-payment-link {
      color: var(--rd-brand);
      font-weight: 600;
      text-decoration: none;
    }

    .rd-payment-link:hover { text-decoration: underline; color: var(--rd-brand-dark); }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-list-alt"></i> {{ $ui['title'] ?? 'Reservation Data' }}</h1>
      <p>PMS reservation list with filters, export, and sync</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Reservation Data' }}</li>
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

        <div class="rd-page-header">
          <h3>{{ $ui['title'] ?? 'Reservation Data' }}</h3>
          <div class="rd-header-actions">
            @foreach($ui['header_actions'] ?? [] as $action)
              @if(!empty($action['route']))
                <a href="{{ route($action['route']) }}" class="btn btn-sm btn-rd-primary">{{ $action['label'] }}</a>
              @else
                <button type="button" class="btn btn-sm btn-rd-primary" disabled title="Coming soon">
                  {{ $action['label'] }}
                </button>
              @endif
            @endforeach
          </div>
        </div>

        <form method="GET" action="{{ route('hotel.reservations.index') }}" id="rdFilterForm">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">

          <div class="rd-toolbar">
            <div class="rd-filters">
              <div>
                <label for="from_date">From Date</label>
                <div class="rd-date-field">
                  <span class="rd-date-field__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                  <input type="date" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
                </div>
              </div>
              <div>
                <label for="to_date">To Date</label>
                <div class="rd-date-field">
                  <span class="rd-date-field__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                  <input type="date" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
                </div>
              </div>
              <div>
                <label for="filter_by">Filter</label>
                <select class="form-control form-control-sm" id="filter_by" name="filter_by" style="min-width:120px;">
                  @foreach($ui['filter_options'] ?? [] as $value => $label)
                    <option value="{{ $value }}" {{ $filters['filter_by'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-sm btn-rd-primary d-block">Submit</button>
              </div>
            </div>

            <div class="rd-toolbar-actions">
              <button type="button" class="rd-icon-btn js-rd-search-toggle {{ $filters['search'] !== '' ? 'is-active' : '' }}"
                title="Search" aria-expanded="{{ $filters['search'] !== '' ? 'true' : 'false' }}">
                <i class="fa fa-search"></i>
              </button>
              <div class="rd-search-wrap {{ $filters['search'] !== '' ? 'is-open' : '' }}" id="rdSearchWrap">
                <input type="search" class="rd-search-input js-rd-search-input" name="search"
                  value="{{ $filters['search'] }}" placeholder="Booking ID, guest, source" autocomplete="off">
              </div>

              <button type="submit" form="rdSyncForm" class="rd-icon-btn js-rd-sync-btn" title="Sync from Channel Manager">
                <i class="fa fa-exchange"></i>
              </button>

              <a class="rd-icon-btn" href="{{ route('hotel.reservations.export', request()->query()) }}" title="Download">
                <i class="fa fa-download"></i>
              </a>

              <button type="button" class="rd-icon-btn" disabled title="Upload (coming soon)">
                <i class="fa fa-upload"></i>
              </button>
            </div>
          </div>
        </form>

        <form method="POST" action="{{ route('hotel.reservations.sync') }}" class="d-none js-rd-sync-form" id="rdSyncForm">
          @csrf
          <input type="hidden" name="filter_by" value="{{ $filters['filter_by'] }}">
          <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
          <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
          <input type="hidden" name="search" value="{{ $filters['search'] }}">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
        </form>

        <div class="rd-table-wrap">
          <table class="rd-table">
            <thead>
              <tr>
                @foreach($ui['columns'] ?? [] as $column)
                  <th>{{ $column['label'] }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @forelse($bookings as $booking)
                <tr class="{{ $booking->isCancelled() ? 'is-cancelled' : '' }}">
                  <td><span class="rd-booking-id">{{ $booking->booking_id }}</span></td>
                  <td><strong>{{ $booking->guestName() }}</strong></td>
                  <td>{{ $booking->bookedOnLabel() }}</td>
                  <td>{{ $booking->checkinLabel() }}</td>
                  <td>{{ $booking->checkoutLabel() }}</td>
                  <td>
                    @if($booking->sourceDisplayLabel() === '—')
                      —
                    @else
                      <span class="rd-source {{ $booking->sourceBadgeClass() }}">{{ $booking->sourceDisplayLabel() }}</span>
                    @endif
                  </td>
                  <td>{{ $booking->guestCount() }}</td>
                  <td>{{ $booking->roomCount() ?: '—' }}</td>
                  <td>{{ $booking->roomNightCount() ?? '—' }}</td>
                  <td>{{ $booking->priceLabel() }}</td>
                  <td><span class="rd-pay {{ $booking->paymentBadgeClass() }}">{{ $booking->paymentLabel() }}</span></td>
                  <td>
                    @if($link = $booking->paymentLinkUrl())
                      <a href="{{ $link }}" class="rd-payment-link" target="_blank" rel="noopener noreferrer">Link</a>
                    @else
                      —
                    @endif
                  </td>
                  <td>{{ $booking->categoryLabel() }}</td>
                  <td>
                    @if($booking->mealPlanLabel() !== '—')
                      <span class="rd-meal">{{ $booking->mealPlanLabel() }}</span>
                    @else
                      —
                    @endif
                  </td>
                  <td>
                    <span class="rd-status {{ $booking->status === 'cancelled' ? 'is-cancelled' : ($booking->status === 'modified' ? 'is-modified' : 'is-confirmed') }}">
                      {{ $booking->statusLabel() }}
                    </span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-rd-view js-rd-view"
                      data-booking-id="{{ $booking->booking_id }}"
                      data-record-id="{{ $booking->id }}">
                      View
                    </button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="{{ count($ui['columns'] ?? []) }}" class="text-center text-muted py-4">
                    No reservations in this date range.
                    Use the sync icon to pull data from Channel Manager.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="rd-footer">
          <form method="GET" action="{{ route('hotel.reservations.index') }}" class="d-inline-flex align-items-center mb-0" style="gap:8px;">
            @foreach(request()->except('per_page', 'page') as $key => $value)
              @if(is_array($value))
                @continue
              @endif
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <span>Items per page:</span>
            <select class="form-control form-control-sm" name="per_page" onchange="this.form.submit()">
              @foreach($ui['per_page_options'] ?? [20] as $option)
                <option value="{{ $option }}" {{ (int) $filters['per_page'] === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
              @endforeach
            </select>
          </form>

          <span>{{ $bookings->firstItem() ?? 0 }} of {{ $bookings->total() }}</span>

          <div class="rd-footer-nav">
            @php
              $query = request()->query();
              $firstQuery = array_merge($query, ['page' => 1]);
              $prevQuery = array_merge($query, ['page' => max(1, $bookings->currentPage() - 1)]);
              $nextQuery = array_merge($query, ['page' => min($bookings->lastPage(), $bookings->currentPage() + 1)]);
              $lastQuery = array_merge($query, ['page' => max(1, $bookings->lastPage())]);
            @endphp
            <a href="{{ $bookings->onFirstPage() ? '#' : route('hotel.reservations.index', $firstQuery) }}"
              class="btn-nav {{ $bookings->onFirstPage() ? 'is-disabled' : '' }}" title="First page">&laquo;</a>
            <a href="{{ $bookings->onFirstPage() ? '#' : route('hotel.reservations.index', $prevQuery) }}"
              class="btn-nav {{ $bookings->onFirstPage() ? 'is-disabled' : '' }}" title="Previous page">&lsaquo;</a>
            <a href="{{ $bookings->onLastPage() ? '#' : route('hotel.reservations.index', $nextQuery) }}"
              class="btn-nav {{ $bookings->onLastPage() ? 'is-disabled' : '' }}" title="Next page">&rsaquo;</a>
            <a href="{{ $bookings->onLastPage() ? '#' : route('hotel.reservations.index', $lastQuery) }}"
              class="btn-nav {{ $bookings->onLastPage() ? 'is-disabled' : '' }}" title="Last page">&raquo;</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="rdDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background:#940000;color:#fff;">
          <h5 class="modal-title">Booking <span id="rdModalBookingId"></span></h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="rdModalSummary"></div>
          <details class="mt-3">
            <summary style="cursor:pointer;font-size:13px;font-weight:700;color:#940000;">Raw payload</summary>
            <pre class="rd-modal-pre" id="rdModalPayload"></pre>
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
  <script type="application/json" id="rd-bookings-data">@json($bookingDetails)</script>
  <script>
    (function () {
      var details = {};
      try {
        details = JSON.parse(document.getElementById('rd-bookings-data').textContent || '{}');
      } catch (e) {
        details = {};
      }

      function renderSummary(summary) {
        var html = '<table class="rd-detail-table"><tbody>';
        Object.keys(summary || {}).forEach(function (key) {
          html += '<tr><th>' + key + '</th><td>' + String(summary[key]) + '</td></tr>';
        });
        html += '</tbody></table>';
        return html;
      }

      document.querySelectorAll('.js-rd-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var recordId = btn.getAttribute('data-record-id');
          var detail = details[recordId] || { summary: {}, raw: {} };

          document.getElementById('rdModalBookingId').textContent = btn.getAttribute('data-booking-id') || '';
          document.getElementById('rdModalSummary').innerHTML = renderSummary(detail.summary || {});
          document.getElementById('rdModalPayload').textContent = JSON.stringify(detail.raw || {}, null, 2);
          $('#rdDetailModal').modal('show');
        });
      });

      var syncForm = document.getElementById('rdSyncForm');
      if (syncForm) {
        syncForm.addEventListener('submit', function () {
          var btn = document.querySelector('.js-rd-sync-btn');
          if (btn) btn.classList.add('is-spinning');
        });
      }

      var searchToggle = document.querySelector('.js-rd-search-toggle');
      var searchWrap = document.getElementById('rdSearchWrap');
      var searchInput = document.querySelector('.js-rd-search-input');
      var filterForm = document.getElementById('rdFilterForm');

      function setSearchOpen(open) {
        if (!searchWrap || !searchToggle) return;
        searchWrap.classList.toggle('is-open', open);
        searchToggle.classList.toggle('is-active', open);
        searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && searchInput) {
          window.setTimeout(function () { searchInput.focus(); }, 350);
        }
      }

      if (searchToggle) {
        searchToggle.addEventListener('click', function () {
          var isOpen = searchWrap.classList.contains('is-open');
          setSearchOpen(!isOpen);
          if (!isOpen && searchInput) {
            searchInput.setAttribute('name', 'search');
          } else if (isOpen && searchInput && searchInput.value === '') {
            searchInput.removeAttribute('name');
          }
        });
      }

      if (searchInput && filterForm) {
        searchInput.addEventListener('keydown', function (event) {
          if (event.key === 'Enter') {
            event.preventDefault();
            searchInput.setAttribute('name', 'search');
            filterForm.submit();
          }
        });
      }

      if (searchWrap && searchWrap.classList.contains('is-open') && searchInput) {
        searchInput.setAttribute('name', 'search');
      }

      document.querySelectorAll('.rd-date-field').forEach(function (wrap) {
        var input = wrap.querySelector('input[type="date"]');
        if (!input) return;

        wrap.addEventListener('click', function (event) {
          if (event.target === input) return;
          input.focus();
          if (typeof input.showPicker === 'function') {
            try { input.showPicker(); } catch (e) { /* ignore */ }
          }
        });
      });
    })();
  </script>
@endpush
