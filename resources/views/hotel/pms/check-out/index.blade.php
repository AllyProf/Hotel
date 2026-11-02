@extends('layouts.app')

@section('title', $ui['title'] ?? 'Front Desk Check-out')

@push('styles')
  @include('hotel.pms.check-out.partials._stay-detail-styles')
  <style>
    :root {
      --co-brand: #940000;
      --co-brand-dark: #7a0000;
      --co-brand-soft: rgba(148, 0, 0, 0.08);
    }

    .co-topbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 0;
      padding-bottom: 14px;
      border-bottom: 1px solid #eee;
    }

    .co-topbar__left h3 {
      margin: 0;
      font-size: 18px;
      font-weight: 400;
      color: #333;
      white-space: nowrap;
    }

    .co-topbar__left h3 .co-count {
      color: #9ca3af;
      font-weight: 400;
      font-size: 14px;
    }

    .co-topbar__left h3 .co-count::before {
      content: '·';
      margin: 0 10px;
      color: #d1d5db;
      font-weight: 300;
    }

    .co-topbar__right {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }

    .co-topbar__right label {
      margin: 0;
      font-size: 12px;
      font-weight: 700;
      color: #555;
      white-space: nowrap;
    }

    .co-date-field {
      display: inline-flex;
      align-items: stretch;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
      height: 34px;
      min-width: 178px;
      cursor: pointer;
    }

    .co-date-field__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      min-width: 38px;
      background: #f6f6f6;
      border-right: 1px solid #e5e5e5;
      color: var(--co-brand);
      font-size: 14px;
      pointer-events: none;
    }

    .co-date-field input[type="date"] {
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
    }

    .co-date-field:focus-within {
      border-color: var(--co-brand);
      box-shadow: 0 0 0 1px rgba(148, 0, 0, 0.12);
    }

    .co-date-field input[type="date"]:focus { outline: none; }

    .btn-co-outline {
      background: #fff !important;
      border-color: #d1d5db !important;
      color: #555 !important;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 12px;
      height: 34px;
    }

    .co-tabs {
      display: flex;
      gap: 0;
      margin: 14px 0 16px;
      border-bottom: 1px solid #eee;
    }

    .co-tab {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      margin-bottom: -1px;
      border: none;
      border-bottom: 2px solid transparent;
      background: transparent;
      color: #666;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: color 0.15s ease, border-color 0.15s ease;
    }

    .co-tab:hover {
      color: var(--co-brand);
      text-decoration: none;
    }

    .co-tab.is-active {
      color: var(--co-brand);
      border-bottom-color: var(--co-brand);
      font-weight: 600;
    }

    .co-tab__count {
      font-size: 11px;
      color: #9ca3af;
      font-weight: 600;
    }

    .co-tab.is-active .co-tab__count {
      color: var(--co-brand);
    }

    .co-table-wrap { overflow-x: auto; }

    .co-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1040px;
    }

    .co-table thead th {
      background: var(--co-brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 10px;
      border: 1px solid var(--co-brand-dark);
      white-space: nowrap;
      vertical-align: middle;
    }

    .co-table tbody td {
      border: 1px solid #ddd;
      padding: 10px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
    }

    .co-table tbody tr:hover td { background: #fafafa; }

    .co-booking-id {
      font-size: 12px;
      font-weight: 600;
      color: #333;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 2px 6px;
      white-space: nowrap;
    }

    .co-guest-name { font-weight: 700; color: #222; }

    .co-room-badge {
      display: inline-block;
      font-size: 12px;
      font-weight: 700;
      color: var(--co-brand);
      background: var(--co-brand-soft);
      border: 1px solid rgba(148, 0, 0, 0.12);
      border-radius: 4px;
      padding: 3px 8px;
    }

    .co-stay-nights {
      display: inline-block;
      font-size: 12px;
      font-weight: 700;
      color: #374151;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 3px 8px;
      white-space: nowrap;
    }

    .co-stay-remaining {
      display: block;
      margin-top: 3px;
      font-size: 11px;
      color: #6b7280;
      font-weight: 500;
    }

    .co-status {
      display: inline-flex;
      align-items: center;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .co-status.is-checked-in { background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; }
    .co-status.is-assigned { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .co-status.is-late { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .co-status.is-on-time { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

    .co-actions {
      display: flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 8px;
    }

    .co-late-fee {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: #555;
      white-space: nowrap;
    }

    .co-late-fee input[type="number"] {
      width: 72px;
      height: 30px;
      font-size: 12px;
      padding: 0 6px;
      border: 1px solid #d1d5db;
      border-radius: 4px;
    }

    .co-late-fee input[type="checkbox"] {
      margin: 0;
    }

    .btn-co-primary {
      background: var(--co-brand) !important;
      border-color: var(--co-brand) !important;
      color: #fff !important;
    }

    .btn-co-primary:hover {
      background: var(--co-brand-dark) !important;
      border-color: var(--co-brand-dark) !important;
    }

    .co-icon-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      padding: 0;
    }

    .co-icon-btn.btn-co-outline:hover {
      background: var(--co-brand-soft) !important;
      border-color: var(--co-brand) !important;
      color: var(--co-brand) !important;
    }

    .co-icon-btn.is-disabled,
    .co-icon-btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
      pointer-events: none;
    }

    .co-stay-detail-btn .fa { font-size: 15px; }

    .co-payment-due {
      font-size: 12px;
      font-weight: 700;
      color: #b91c1c;
      white-space: nowrap;
    }

    .co-payment-paid {
      font-size: 12px;
      font-weight: 600;
      color: #047857;
      white-space: nowrap;
    }

    .co-payment-prepaid {
      font-size: 11px;
      font-weight: 600;
      color: #6b7280;
    }

    .co-payment-link {
      display: block;
      margin-top: 4px;
      font-size: 11px;
      font-weight: 600;
    }

    .co-payment-link a {
      color: var(--co-brand);
      text-decoration: none;
    }

    .co-payment-link a:hover {
      text-decoration: underline;
    }

    .co-link-modal .modal-header {
      background: var(--co-brand);
      color: #fff;
    }

    .co-link-modal .modal-header .close,
    .co-pay-modal .modal-header .close {
      color: #fff;
      opacity: 0.9;
    }

    .co-pay-modal .modal-header {
      background: var(--co-brand);
      color: #fff;
    }

    .co-empty {
      padding: 56px 20px;
      text-align: center;
      color: #888;
      border: 1px dashed #ddd;
      border-radius: 4px;
      background: #fafafa;
    }

    .co-empty i { font-size: 36px; color: #ccc; margin-bottom: 12px; }

    .rd-source {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 4px;
      white-space: nowrap;
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
  </style>
@endpush

@section('content')
  @php
    $tabTitle = match($tab) {
      'in_house' => 'In House',
      'checked_out' => 'Checked Out',
      default => 'Departures',
    };
    $guestLabel = Str::plural('guest', $bookings->count());
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-sign-out"></i> {{ $ui['title'] ?? 'Front Desk Check-out' }}</h1>
      <p>
        @if($tab === 'departures')
          Guests scheduled to leave today who still need to check out.
        @elseif($tab === 'checked_out')
          Guests who already checked out on the selected date.
        @else
          Guests currently in-house on the selected date.
        @endif
      </p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Check-out</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="co-topbar">
          <div class="co-topbar__left">
            <h3>
              {{ $tabTitle }}
              <span class="co-count">{{ $bookings->count() }} {{ $guestLabel }}</span>
            </h3>
          </div>

          <form method="GET" action="{{ route('hotel.check-out.index') }}" class="co-topbar__right">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="co_date">Date</label>
            <div class="co-date-field">
              <span class="co-date-field__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
              <input type="date" id="co_date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            @if($date->format('Y-m-d') !== now()->format('Y-m-d'))
              <a href="{{ route('hotel.check-out.index', ['tab' => $tab]) }}" class="btn btn-sm btn-co-outline">Today</a>
            @endif
          </form>
        </div>

        <div class="co-tabs">
          @foreach($ui['tabs'] ?? [] as $tabKey => $tabLabel)
            <a href="{{ route('hotel.check-out.index', ['tab' => $tabKey, 'date' => $date->format('Y-m-d')]) }}"
               class="co-tab {{ $tab === $tabKey ? 'is-active' : '' }}">
              {{ $tabLabel }}
              <span class="co-tab__count">{{ $stats[$tabKey] ?? 0 }}</span>
            </a>
          @endforeach
        </div>

        <div class="co-table-wrap">
          @if($bookings->isEmpty())
            <div class="co-empty">
              <i class="fa fa-sign-out"></i>
              <div>No {{ strtolower($tabTitle) }} for {{ $date->format('d M Y') }}.</div>
            </div>
          @else
            <table class="co-table table table-hover table-bordered mb-0">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Guest</th>
                  <th>Source</th>
                  <th>Room Type</th>
                  <th>Room No</th>
                  <th>Nights</th>
                  @if($tab === 'in_house')
                    <th>Check-out</th>
                  @elseif($tab === 'checked_out')
                    <th>Checked out</th>
                  @else
                    <th>Late</th>
                  @endif
                  <th>Payment</th>
                  <th class="text-center co-th-detail" title="Stay &amp; services"><i class="fa fa-list-alt"></i></th>
                  @if($tab !== 'checked_out')
                    <th>Action</th>
                  @endif
                </tr>
              </thead>
              <tbody>
                @foreach($bookings as $booking)
                  @php
                    $ctx = $checkoutContexts[$booking->id] ?? [];
                    $isLate = !empty($ctx['is_late']);
                    $defaultLateFee = (float) ($frontDesk['late_checkout_amount'] ?? 0);
                    $paymentCleared = !empty($ctx['payment_cleared']);
                    $balanceDue = (float) ($ctx['balance_due'] ?? 0);
                    $amountPaid = (float) ($ctx['amount_paid'] ?? 0);
                    $totalDue = (float) ($ctx['total_due'] ?? 0);
                    $isPrepaid = !empty($ctx['is_prepaid']);
                    $paymentLinkUrl = $ctx['payment_link_url'] ?? null;
                    $guestEmail = $ctx['guest_email'] ?? '';
                    $guestPhone = $ctx['guest_phone'] ?? '';
                    $billToCompanyId = (int) ($ctx['bill_to_company_id'] ?? 0);
                    $companyAr = $billToCompanyId > 0 ? ($companyReceivables[$billToCompanyId] ?? null) : null;
                    $foundInRoom = ($foundItemsByReservation[$booking->id] ?? collect());
                    $foundRefs = $foundInRoom->pluck('reference_no')->implode(', ');
                  @endphp
                  <tr class="js-co-row"
                      data-balance="{{ number_format($balanceDue, 2, '.', '') }}"
                      @if($foundRefs !== '') data-found-items="{{ $foundRefs }}" @endif
                      data-total-due="{{ number_format($totalDue, 2, '.', '') }}"
                      data-amount-paid="{{ number_format($amountPaid, 2, '.', '') }}"
                      data-late-fee="{{ number_format($defaultLateFee, 2, '.', '') }}"
                      data-currency="{{ $booking->currency ?? '' }}"
                      data-is-prepaid="{{ $isPrepaid ? '1' : '0' }}">
                    <td><span class="co-booking-id">{{ $booking->booking_id }}</span></td>
                    <td>
                      <span class="co-guest-name js-co-guest-name">{{ $booking->guestName() }}</span>
                      @if($companyAr && ($companyAr['outstanding'] ?? 0) > 0)
                        <div class="small mt-1">
                          <a href="{{ route('hotel.accounts.index', ['tab' => 'receivables']) }}" style="color:#b45309;">
                            Company AR: {{ number_format($companyAr['outstanding'], 2) }} {{ $companyAr['currency'] ?? '' }}
                          </a>
                        </div>
                      @endif
                    </td>
                    <td>
                      @if($booking->sourceDisplayLabel() === '—')
                        —
                      @else
                        <span class="rd-source {{ $booking->sourceBadgeClass() }}">{{ $booking->sourceDisplayLabel() }}</span>
                      @endif
                    </td>
                    <td>{{ $booking->roomLabel() }}</td>
                    <td>
                      @if($booking->isRoomAssigned())
                        <span class="co-room-badge">{{ $booking->assignedRoomLabel() }}</span>
                        @if($foundInRoom->isNotEmpty())
                          <div class="small mt-1" style="color:#b45309;">
                            <i class="fa fa-exclamation-triangle"></i>
                            {{ $foundInRoom->count() }} found item(s) in room
                            <a href="{{ route('hotel.lost-found.index', ['filter' => 'open']) }}">View</a>
                          </div>
                        @endif
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>
                      @php
                        $nights = $booking->roomNightCount();
                        $nightsRemaining = $tab === 'in_house' ? $booking->nightsRemaining($date) : null;
                      @endphp
                      @if($nights)
                        <span class="co-stay-nights">{{ $nights }} {{ Str::plural('night', $nights) }}</span>
                        @if($tab === 'in_house')
                          @if($nightsRemaining === 0)
                            <span class="co-stay-remaining">Checking out today</span>
                          @elseif($nightsRemaining !== null && $nightsRemaining < $nights)
                            <span class="co-stay-remaining">{{ $nightsRemaining }} {{ Str::plural('night', $nightsRemaining) }} left</span>
                          @endif
                        @endif
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    @if($tab === 'in_house')
                      <td>{{ $booking->checkoutLabel() }}</td>
                    @elseif($tab === 'checked_out')
                      <td>{{ $booking->checked_out_at?->format('d/m/Y g:i A') ?? '—' }}</td>
                    @else
                      <td>
                        @if($isLate)
                          <span class="co-status is-late">Late</span>
                        @else
                          <span class="co-status is-on-time">On time</span>
                        @endif
                      </td>
                    @endif
                    <td class="js-co-payment-cell">
                      @if($tab === 'checked_out' || ($isPrepaid && $balanceDue <= 0))
                        @if($isPrepaid)
                          <span class="co-payment-prepaid">Prepaid</span>
                        @else
                          <span class="co-payment-paid">Paid</span>
                        @endif
                      @elseif($paymentCleared)
                        <span class="co-payment-paid">Paid</span>
                      @else
                        <span class="co-payment-due js-co-balance-label">{{ $booking->balanceLabel() }}</span>
                      @endif
                      @if($paymentLinkUrl && !$paymentCleared)
                        <span class="co-payment-link">
                          <a href="{{ $paymentLinkUrl }}" target="_blank" rel="noopener">Payment link sent</a>
                        </span>
                      @endif
                    </td>
                    <td class="text-center">
                      <a href="{{ route('hotel.check-out.stay', ['reservation' => $booking, 'date' => $date->format('Y-m-d'), 'tab' => $tab]) }}"
                         class="btn btn-sm co-stay-detail-btn"
                         title="View stay, services &amp; charges"
                         aria-label="View stay details">
                        <i class="fa fa-list-alt"></i>
                      </a>
                    </td>
                    @if($tab !== 'checked_out')
                    <td>
                      <div class="co-actions">
                        @unless($isPrepaid)
                          <button type="button"
                                  class="btn btn-sm btn-co-outline co-icon-btn js-co-pay-btn {{ $paymentCleared ? 'd-none' : '' }}"
                                  title="Collect payment"
                                  aria-label="Collect payment"
                                  data-booking-id="{{ $booking->booking_id }}"
                                  data-guest="{{ $booking->guestName() }}"
                                  data-pay-url="{{ route('hotel.check-out.pay', $booking) }}"
                                  data-bill-to-company-id="{{ $billToCompanyId > 0 ? $billToCompanyId : '' }}">
                            <i class="fa fa-money"></i>
                          </button>

                          <button type="button"
                                  class="btn btn-sm btn-co-outline co-icon-btn js-co-link-btn {{ $paymentCleared ? 'd-none' : '' }}"
                                  title="Send payment link"
                                  aria-label="Send payment link"
                                  data-booking-id="{{ $booking->booking_id }}"
                                  data-guest="{{ $booking->guestName() }}"
                                  data-email="{{ $guestEmail }}"
                                  data-phone="{{ $guestPhone }}"
                                  data-link-url="{{ route('hotel.check-out.send-link', $booking) }}">
                            <i class="fa fa-link"></i>
                          </button>
                        @endunless

                        <form method="POST" action="{{ route('hotel.check-out.process', $booking) }}" class="d-inline js-co-checkout-form">
                          @csrf
                          <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                          <input type="hidden" name="tab" value="{{ $tab }}">

                          @if($tab === 'departures' && $defaultLateFee > 0)
                            <label class="co-late-fee mb-0" title="Late check-out fee">
                              <input type="checkbox" name="apply_late_fee" value="1" class="js-co-late-toggle" @checked($isLate)>
                              <span>Fee</span>
                              <input type="number" step="0.01" min="0" name="late_fee_amount" class="js-co-late-amount"
                                     value="{{ number_format($defaultLateFee, 2, '.', '') }}"
                                     @disabled(!$isLate)>
                            </label>
                          @endif

                          <button type="button"
                                  class="btn btn-sm btn-co-primary co-icon-btn js-co-checkout-btn {{ $paymentCleared ? '' : 'is-disabled' }}"
                                  title="{{ $paymentCleared ? 'Check out' : 'Collect payment first' }}"
                                  aria-label="Check out guest"
                                  data-loader-text="Checking out..."
                                  @unless($paymentCleared) disabled @endunless>
                            <i class="fa fa-sign-out"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                    @endif
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade co-pay-modal" id="coPayModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="#" id="coPayForm">
          @csrf
          <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
          <input type="hidden" name="tab" value="{{ $tab }}">
          <input type="hidden" name="late_fee_amount" value="" class="js-co-pay-late-fee">
          <div class="modal-header">
            <h5 class="modal-title">Collect Payment</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <p class="mb-2 text-muted js-co-pay-guest" style="font-size:13px;"></p>
            <div class="form-group mb-2">
              <label class="control-label">Amount</label>
              <input type="number" step="0.01" min="0.01" name="amount" class="form-control js-co-pay-amount" required>
            </div>
            <div class="form-group mb-2">
              <label class="control-label">Payment Mode</label>
              <select name="payment_mode" class="form-control js-co-pay-mode" required>
                @foreach($paymentModes as $mode)
                  <option value="{{ $mode }}">{{ $mode }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group mb-2 js-co-pay-provider-wrap d-none">
              <label class="control-label js-co-pay-provider-label">Provider <span class="text-danger">*</span></label>
              <select name="payment_provider" class="form-control js-co-pay-provider">
                <option value="">Select provider</option>
              </select>
            </div>
            <div class="form-group mb-2 js-co-pay-company-wrap d-none">
              <label class="control-label">Company <span class="text-danger">*</span></label>
              <select name="bill_to_company_id" class="form-control js-co-pay-company">
                <option value="">Select company</option>
                @foreach($companies as $company)
                  <option value="{{ $company['id'] }}">{{ $company['name'] }}</option>
                @endforeach
              </select>
              @if(empty($companies))
                <small class="text-muted d-block mt-1">
                  No companies yet. <a href="{{ route('hotel.companies.index') }}" target="_blank" rel="noopener">Add a company</a> first.
                </small>
              @endif
            </div>
            <div class="form-group mb-0 js-co-pay-reference-wrap">
              <label class="control-label js-co-pay-reference-label">Reference No.</label>
              <input type="text" name="payment_reference" class="form-control js-co-pay-reference" maxlength="80" placeholder="Transaction / receipt reference">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-co-primary btn-sm" data-loader-text="Recording payment...">
              <i class="fa fa-check"></i> Record Payment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade co-link-modal" id="coLinkModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="#" id="coLinkForm">
          @csrf
          <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
          <input type="hidden" name="tab" value="{{ $tab }}">
          <input type="hidden" name="late_fee_amount" value="" class="js-co-link-late-fee">
          <div class="modal-header">
            <h5 class="modal-title">Send Payment Link</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <p class="mb-3 text-muted js-co-link-guest" style="font-size:13px;"></p>
            <p class="mb-3" style="font-size:12px;color:#666;">
              A secure payment link will be sent to the guest. You can also open the link from the Payment column after sending.
            </p>
            <div class="form-group">
              <label class="control-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control js-co-link-email" required maxlength="160">
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Phone No. <span class="text-danger">*</span></label>
                  <input type="text" name="phone" class="form-control js-co-link-phone" required maxlength="40">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label">Amount <span class="text-danger">*</span></label>
                  <input type="number" step="0.01" min="0.01" name="amount" class="form-control js-co-link-amount" required>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-co-primary btn-sm" data-loader-text="Sending link...">
              <i class="fa fa-paper-plane"></i> Send Link
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script type="application/json" id="co-payment-provider-config">@json($paymentProviderConfig)</script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var paymentReferenceRequired = @json(! empty($frontDesk['payment_reference_required']));
      var paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
      try {
        paymentProviderConfig = JSON.parse(document.getElementById('co-payment-provider-config').textContent || '{}');
      } catch (e) {
        paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
      }

      var payModal = document.getElementById('coPayModal');
      var payForm = document.getElementById('coPayForm');
      var payGuest = payModal ? payModal.querySelector('.js-co-pay-guest') : null;
      var payAmount = payModal ? payModal.querySelector('.js-co-pay-amount') : null;
      var payLateFee = payModal ? payModal.querySelector('.js-co-pay-late-fee') : null;
      var payMode = payModal ? payModal.querySelector('.js-co-pay-mode') : null;
      var payProviderWrap = payModal ? payModal.querySelector('.js-co-pay-provider-wrap') : null;
      var payProviderLabel = payModal ? payModal.querySelector('.js-co-pay-provider-label') : null;
      var payProvider = payModal ? payModal.querySelector('.js-co-pay-provider') : null;
      var payCompanyWrap = payModal ? payModal.querySelector('.js-co-pay-company-wrap') : null;
      var payCompany = payModal ? payModal.querySelector('.js-co-pay-company') : null;
      var payReference = payModal ? payModal.querySelector('.js-co-pay-reference') : null;
      var payReferenceWrap = payModal ? payModal.querySelector('.js-co-pay-reference-wrap') : null;
      var payReferenceLabel = payModal ? payModal.querySelector('.js-co-pay-reference-label') : null;
      var linkModal = document.getElementById('coLinkModal');
      var linkForm = document.getElementById('coLinkForm');
      var linkGuest = linkModal ? linkModal.querySelector('.js-co-link-guest') : null;
      var linkEmail = linkModal ? linkModal.querySelector('.js-co-link-email') : null;
      var linkPhone = linkModal ? linkModal.querySelector('.js-co-link-phone') : null;
      var linkAmount = linkModal ? linkModal.querySelector('.js-co-link-amount') : null;
      var linkLateFee = linkModal ? linkModal.querySelector('.js-co-link-late-fee') : null;

      function parseAmount(value) {
        var amount = parseFloat(value || '0');
        return isNaN(amount) ? 0 : amount;
      }

      function formatAmount(value) {
        return Math.max(0, value).toFixed(2);
      }

      function isPrepaidMode(mode) {
        return (mode || '').toLowerCase().indexOf('prepaid') !== -1;
      }

      function isBillToCompanyMode(mode) {
        var value = (mode || '').toLowerCase();
        return value.indexOf('bill') !== -1 && value.indexOf('company') !== -1;
      }

      function providerOptionsForMode(mode) {
        var value = (mode || '').toLowerCase();

        if (value.indexOf('bank') !== -1) {
          return paymentProviderConfig.bank_providers || [];
        }

        if (/mobile|m-pesa|mpesa|wallet|tigo|airtel|halopesa|upi/.test(value)) {
          return paymentProviderConfig.mobile_payment_providers || [];
        }

        return [];
      }

      function updatePayReferenceField() {
        if (!payReference || !payReferenceWrap) {
          return;
        }

        var hide = !payMode
          || isBillToCompanyMode(payMode.value)
          || isPrepaidMode(payMode.value);
        var required = !hide && paymentReferenceRequired;

        payReferenceWrap.classList.toggle('d-none', hide);
        payReference.required = required;

        if (payReferenceLabel) {
          payReferenceLabel.innerHTML = 'Reference No.' + (required ? ' <span class="text-danger">*</span>' : '');
        }

        if (hide) {
          payReference.value = '';
        }
      }

      function updatePayCompanyField() {
        if (!payMode || !payCompanyWrap || !payCompany) {
          return;
        }

        var show = isBillToCompanyMode(payMode.value);
        payCompanyWrap.classList.toggle('d-none', !show);
        payCompany.required = show;

        if (!show) {
          payCompany.value = '';
        }
      }

      function updatePayProviderField() {
        if (!payMode || !payProviderWrap || !payProvider) {
          return;
        }

        var options = providerOptionsForMode(payMode.value);
        var modeValue = (payMode.value || '').toLowerCase();

        payProvider.innerHTML = '<option value="">Select provider</option>';
        options.forEach(function (name) {
          var option = document.createElement('option');
          option.value = name;
          option.textContent = name;
          payProvider.appendChild(option);
        });

        var show = options.length > 0;
        payProviderWrap.classList.toggle('d-none', !show);
        payProvider.required = show;
        payProvider.value = '';

        if (payProviderLabel) {
          var label = modeValue.indexOf('bank') !== -1 ? 'Bank' : 'Mobile Payment Provider';
          payProviderLabel.innerHTML = label + (show ? ' <span class="text-danger">*</span>' : '');
        }
      }

      function lateFeeForRow(row) {
        var toggle = row.querySelector('.js-co-late-toggle');
        var lateFee = parseAmount(row.getAttribute('data-late-fee'));

        if (!toggle || !toggle.checked || lateFee <= 0) {
          return 0;
        }

        return lateFee;
      }

      function effectiveBalance(row) {
        var totalDue = parseAmount(row.getAttribute('data-total-due'));
        var amountPaid = parseAmount(row.getAttribute('data-amount-paid'));

        return Math.max(0, totalDue + lateFeeForRow(row) - amountPaid);
      }

      function updatePaymentCell(row, balance) {
        var cell = row.querySelector('.js-co-payment-cell');
        if (!cell) return;

        var isPrepaid = row.getAttribute('data-is-prepaid') === '1';
        var currency = row.getAttribute('data-currency') || '';
        var label = cell.querySelector('.js-co-balance-label');

        if (balance <= 0.009) {
          cell.innerHTML = isPrepaid
            ? '<span class="co-payment-prepaid">Prepaid</span>'
            : '<span class="co-payment-paid">Paid</span>';
          return;
        }

        var text = formatAmount(balance) + (currency ? ' ' + currency : '');

        if (label) {
          label.textContent = text;
        } else {
          cell.innerHTML = '<span class="co-payment-due js-co-balance-label">' + text + '</span>';
        }
      }

      function updateCheckoutState(row) {
        var balance = effectiveBalance(row);
        var payBtn = row.querySelector('.js-co-pay-btn');
        var checkoutBtn = row.querySelector('.js-co-checkout-btn');
        var cleared = balance <= 0.009;

        updatePaymentCell(row, balance);

        if (payBtn) {
          payBtn.classList.toggle('d-none', cleared);
        }

        var linkBtn = row.querySelector('.js-co-link-btn');
        if (linkBtn) {
          linkBtn.classList.toggle('d-none', cleared);
        }

        if (checkoutBtn) {
          checkoutBtn.disabled = !cleared;
          checkoutBtn.classList.toggle('is-disabled', !cleared);
          checkoutBtn.title = cleared ? 'Check out' : 'Collect payment first';
        }
      }

      document.querySelectorAll('.js-co-row').forEach(function (row) {
        var lateToggle = row.querySelector('.js-co-late-toggle');
        if (lateToggle) {
          lateToggle.addEventListener('change', function () {
            var amount = row.querySelector('.js-co-late-amount');
            if (amount) amount.disabled = !lateToggle.checked;
            updateCheckoutState(row);
          });
        }
        updateCheckoutState(row);
      });

      document.querySelectorAll('.js-co-pay-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          if (!payForm || !payAmount) return;

          var row = button.closest('tr');
          var balance = row ? effectiveBalance(row) : 0;
          var lateFee = row ? lateFeeForRow(row) : 0;

          payForm.action = button.getAttribute('data-pay-url') || '#';
          payAmount.value = formatAmount(balance);

          if (payLateFee) {
            payLateFee.value = lateFee > 0 ? formatAmount(lateFee) : '';
          }

          if (payGuest) {
            payGuest.textContent = (button.getAttribute('data-guest') || 'Guest') + ' · ' + (button.getAttribute('data-booking-id') || '');
          }

          updatePayProviderField();
          updatePayCompanyField();
          updatePayReferenceField();

          if (payReference) {
            payReference.value = '';
          }

          if (payCompany) {
            var presetCompanyId = button.getAttribute('data-bill-to-company-id') || '';
            payCompany.value = presetCompanyId;
          }

          if (window.jQuery && payModal) {
            window.jQuery(payModal).modal('show');
          }
        });
      });

      if (payMode) {
        payMode.addEventListener('change', function () {
          updatePayProviderField();
          updatePayCompanyField();
          updatePayReferenceField();
        });
      }

      updatePayReferenceField();

      document.querySelectorAll('.js-co-link-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          if (!linkForm || !linkAmount) return;

          var row = button.closest('tr');
          var balance = row ? effectiveBalance(row) : 0;
          var lateFee = row ? lateFeeForRow(row) : 0;

          linkForm.action = button.getAttribute('data-link-url') || '#';
          linkAmount.value = formatAmount(balance);

          if (linkEmail) {
            linkEmail.value = button.getAttribute('data-email') || '';
          }

          if (linkPhone) {
            linkPhone.value = button.getAttribute('data-phone') || '';
          }

          if (linkLateFee) {
            linkLateFee.value = lateFee > 0 ? formatAmount(lateFee) : '';
          }

          if (linkGuest) {
            linkGuest.textContent = (button.getAttribute('data-guest') || 'Guest') + ' · ' + (button.getAttribute('data-booking-id') || '');
          }

          if (window.jQuery && linkModal) {
            window.jQuery(linkModal).modal('show');
          }
        });
      });

      if (linkForm) {
        linkForm.addEventListener('submit', function (event) {
          if (window.AppAlerts && typeof window.AppAlerts.confirm === 'function') {
            event.preventDefault();
            var email = linkEmail ? linkEmail.value : '';
            AppAlerts.confirm({
              title: 'Send payment link?',
              text: 'Send payment link to ' + email + '?',
              confirmText: 'Yes, send',
              cancelText: 'Cancel',
            }, function () {
              linkForm.submit();
            });
          }
        });
      }

      if (payForm) {
        payForm.addEventListener('submit', function (event) {
          var providerRequired = payProvider && payProvider.required;
          if (providerRequired && !payProvider.value) {
            event.preventDefault();
            if (typeof swal === 'function') {
              swal('Select provider', 'Please choose a bank or mobile payment provider.', 'warning');
            } else {
              alert('Please choose a bank or mobile payment provider.');
            }
            return;
          }

          if (payReference && payReference.required && !payReference.value.trim()) {
            event.preventDefault();
            if (typeof swal === 'function') {
              swal('Reference required', 'Please enter the payment reference number.', 'warning');
            } else {
              alert('Please enter the payment reference number.');
            }
            return;
          }

          if (payCompany && payCompany.required && !payCompany.value) {
            event.preventDefault();
            if (typeof swal === 'function') {
              swal('Select company', 'Please choose a company for Bill to Company payment.', 'warning');
            } else {
              alert('Please choose a company for Bill to Company payment.');
            }
            return;
          }

          if (window.AppAlerts && typeof window.AppAlerts.confirm === 'function') {
            event.preventDefault();
            var amount = payAmount ? payAmount.value : '';
            AppAlerts.confirm({
              title: 'Record payment?',
              text: 'Confirm payment of ' + amount + '.',
              confirmText: 'Yes, record',
              cancelText: 'Cancel',
            }, function () {
              payForm.submit();
            });
          }
        });
      }

      function confirmAction(options, onConfirm) {
        if (window.AppAlerts && typeof window.AppAlerts.confirm === 'function') {
          window.AppAlerts.confirm(options, onConfirm);
          return;
        }
        if (confirm((options.title || 'Confirm') + '\n' + (options.text || ''))) {
          onConfirm();
        }
      }

      document.querySelectorAll('.js-co-checkout-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          if (button.disabled) {
            if (typeof swal === 'function') {
              swal('Payment required', 'Please collect the full payment before check-out.', 'warning');
            }
            return;
          }

          var form = button.closest('form');
          var row = button.closest('tr');
          var guestEl = row ? row.querySelector('.js-co-guest-name') : null;
          var guest = guestEl ? guestEl.textContent.trim() : 'this guest';
          var feeToggle = form ? form.querySelector('.js-co-late-toggle') : null;
          var feeAmount = form ? form.querySelector('.js-co-late-amount') : null;
          var text = 'Confirm check-out for ' + guest + '.';
          var foundItems = row ? row.getAttribute('data-found-items') : '';

          if (foundItems) {
            text += ' Warning: open Lost & Found item(s) for this room (' + foundItems + ').';
          }

          if (feeToggle && feeToggle.checked && feeAmount && feeAmount.value) {
            text += ' Late fee: ' + feeAmount.value + '.';
          }

          confirmAction({
            title: 'Check out guest?',
            text: text,
            confirmText: 'Yes, check out',
            cancelText: 'Cancel',
          }, function () {
            form.submit();
          });
        });
      });
    });
  </script>
@endpush
