@extends('layouts.app')

@section('title', 'Booking '.$booking->booking_id)

@push('styles')
  <style>
    :root {
      --rs-brand: #940000;
      --rs-brand-dark: #7a0000;
      --rs-brand-soft: rgba(148, 0, 0, 0.08);
    }

    .rs-overview {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 4px;
    }

    .rs-overview__meta {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-top: 8px;
    }

    .rs-stat {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 16px 18px;
      height: 100%;
    }

    .rs-stat__label {
      font-size: 11px;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }

    .rs-stat__value {
      font-size: 18px;
      font-weight: 700;
      color: #111827;
      line-height: 1.25;
    }

    .rs-stat__sub {
      font-size: 12px;
      color: #6b7280;
      margin-top: 4px;
    }

    .rs-stat.is-brand {
      border-top: 3px solid var(--rs-brand);
    }

    .rs-date-field {
      position: relative;
      display: flex;
      align-items: stretch;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
      height: 38px;
      cursor: pointer;
    }

    .rs-date-field__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      min-width: 38px;
      background: #f6f6f6;
      border-right: 1px solid #e5e5e5;
      color: var(--rs-brand);
      font-size: 14px;
      pointer-events: none;
    }

    .rs-date-field input[type="date"] {
      flex: 1;
      width: 100%;
      height: 100%;
      padding: 0 10px;
      font-size: 13px;
      font-weight: 500;
      color: #333;
      border: none;
      background: transparent;
      cursor: pointer;
    }

    .rs-date-field:focus-within {
      border-color: var(--rs-brand);
      box-shadow: 0 0 0 1px rgba(148, 0, 0, 0.12);
    }

    .rs-date-field input[type="date"]:focus {
      outline: none;
    }

    .rs-date-field input[type="date"]::-webkit-calendar-picker-indicator {
      opacity: 0.6;
      cursor: pointer;
    }

    .rs-date-field.is-readonly {
      background: #f9fafb;
      cursor: default;
    }

    .rs-date-field.is-readonly input[type="date"] {
      cursor: default;
    }

    .rs-preview {
      margin-top: 14px;
      margin-bottom: 0;
      font-size: 13px;
    }

    .rs-preview .rs-price-row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-top: 4px;
    }

    .rs-preview .rs-price-diff.positive { color: #b45309; font-weight: 700; }
    .rs-preview .rs-price-diff.negative { color: #047857; font-weight: 700; }

    .rs-resend-wrap { display: none; }
    .rs-resend-wrap.is-visible { display: block; }

    .rs-history {
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px solid #eee;
    }

    .rs-history__title {
      font-size: 12px;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 8px;
    }

    .rs-table thead th {
      background: var(--rs-brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      border-color: var(--rs-brand-dark);
      white-space: nowrap;
    }

    .rs-summary-table th {
      width: 32%;
      background: #f9fafb;
      font-weight: 700;
      color: #374151;
    }

    .rs-help {
      font-size: 12px;
      color: #6b7280;
      margin-top: 4px;
    }

    .rs-form-divider {
      margin: 18px 0 14px;
      border-top: 1px solid #eee;
    }

    .rs-switch-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .rs-switch-row:last-of-type {
      border-bottom: none;
    }

    .rs-switch-row__text {
      font-size: 13px;
      font-weight: 600;
      color: #333;
      line-height: 1.4;
    }

    .rs-switch {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      flex-shrink: 0;
      margin: 0;
    }

    .rs-switch input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .rs-switch-track {
      position: relative;
      width: 42px;
      height: 22px;
      background: #9aa3ab;
      border-radius: 22px;
      transition: background 0.2s;
    }

    .rs-switch-track:before {
      content: '';
      position: absolute;
      width: 18px;
      height: 18px;
      left: 2px;
      top: 2px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.2s;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18);
    }

    .rs-switch input:checked + .rs-switch-track {
      background: var(--rs-brand);
    }

    .rs-switch input:checked + .rs-switch-track:before {
      transform: translateX(20px);
    }

    .rs-switch-label {
      font-size: 12px;
      font-weight: 700;
      min-width: 28px;
      text-align: left;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .rs-switch input:checked ~ .rs-switch-label {
      color: var(--rs-brand);
    }
  </style>
@endpush

@section('content')
  @php
    $summary = $detail['summary'] ?? [];
    $roomLines = $booking->roomLines();
    $statusBadge = match ($booking->status) {
      'cancelled' => 'badge-danger',
      'modified' => 'badge-warning',
      default => 'badge-success',
    };
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-file-text-o"></i> Booking Details</h1>
      <p>{{ $booking->booking_id }} · {{ $booking->guestName() }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.reservations.index') }}">Reservations</a></li>
      <li class="breadcrumb-item">{{ $booking->booking_id }}</li>
    </ul>
  </div>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fa fa-exclamation-circle mr-1"></i> {{ session('error') }}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  @endif
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fa fa-check-circle mr-1"></i> {{ session('success') }}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0 pl-3">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  @endif

  {{-- Overview stats --}}
  <div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
      <div class="rs-stat is-brand">
        <div class="rs-stat__label">Guest</div>
        <div class="rs-stat__value">{{ $booking->guestName() }}</div>
        <div class="rs-stat__sub">{{ $summary['Email'] ?? ($summary['Phone'] ?? '—') }}</div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
      <div class="rs-stat is-brand">
        <div class="rs-stat__label">Stay</div>
        <div class="rs-stat__value">{{ $booking->checkinLabel() }} → {{ $booking->checkoutLabel() }}</div>
        <div class="rs-stat__sub">{{ $booking->roomNightCount() ?? '—' }} night(s)</div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
      <div class="rs-stat is-brand">
        <div class="rs-stat__label">Room</div>
        <div class="rs-stat__value">{{ $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : ($booking->roomLabel() !== '—' ? $booking->roomLabel() : 'Unassigned') }}</div>
        <div class="rs-stat__sub">{{ $booking->pmsStatusLabel() }}</div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
      <div class="rs-stat is-brand">
        <div class="rs-stat__label">Amount</div>
        <div class="rs-stat__value">{{ $booking->priceLabel() }}</div>
        <div class="rs-stat__sub">
          @if($booking->balanceDue() > 0.009)
            Balance: {{ $booking->balanceLabel() }}
          @else
            {{ $booking->effectiveAmountPaid() > 0 ? 'Paid / advance received' : 'No payment recorded' }}
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Actions row --}}
  <div class="row">
    <div class="col-lg-7 mb-4">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title"><i class="fa fa-calendar"></i> Update Stay Dates</h3>
        </div>
        <div class="tile-body">
          <div class="rs-overview">
            <div>
              <div class="rs-overview__meta">
                <span class="badge {{ $statusBadge }}">{{ $booking->statusLabel() }}</span>
                <span class="badge {{ $booking->pmsStatusBadgeClass() }}">{{ $booking->pmsStatusLabel() }}</span>
                @if($booking->isArrivalToday())
                  <span class="badge badge-success">Arrival today</span>
                @endif
                @if($booking->isCheckoutToday())
                  <span class="badge badge-warning">Checkout today</span>
                @endif
              </div>
            </div>
            <a href="{{ route('hotel.reservations.index', request()->only(['filter_by', 'from_date', 'to_date', 'search', 'per_page'])) }}" class="btn btn-secondary btn-sm">
              <i class="fa fa-arrow-left"></i> Back to list
            </a>
          </div>

          @if($canManageStay)
            <p class="text-muted mb-3">Adjust check-in or check-out. Availability is checked against other bookings before saving.</p>

            <form method="POST" action="{{ route('hotel.reservations.update-stay', $booking) }}" id="rdStayForm">
              @csrf
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="control-label">Check-in</label>
                    <label class="rs-date-field w-100 mb-0 @if($booking->isCheckedIn()) is-readonly @endif">
                      <span class="rs-date-field__icon"><i class="fa fa-calendar"></i></span>
                      <input type="date" class="rd-stay-date @error('checkin') is-invalid @enderror" name="checkin"
                        id="rdStayCheckin"
                        value="{{ old('checkin', $booking->checkin?->format('Y-m-d')) }}"
                        @if($booking->isCheckedIn()) readonly @endif
                        required>
                    </label>
                    @if($booking->isCheckedIn())
                      <p class="rs-help"><i class="fa fa-lock"></i> Locked — guest is already checked in.</p>
                    @endif
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="control-label">Check-out</label>
                    <label class="rs-date-field w-100 mb-0">
                      <span class="rs-date-field__icon"><i class="fa fa-calendar"></i></span>
                      <input type="date" class="rd-stay-date @error('checkout') is-invalid @enderror" name="checkout"
                        id="rdStayCheckout"
                        value="{{ old('checkout', $booking->checkout?->format('Y-m-d')) }}" required>
                    </label>
                  </div>
                </div>
              </div>

              <div class="rs-form-divider"></div>

              <div class="form-group">
                <label class="control-label">Reason for change <span class="text-danger">*</span></label>
                <select class="form-control @error('reason') is-invalid @enderror" name="reason" id="rdStayReason" required>
                  <option value="">Select reason…</option>
                  @foreach(['Guest requested extension', 'Guest requested early departure', 'Guest requested date change', 'Room maintenance issue', 'Overbooking correction', 'Company / group adjustment', 'Other'] as $reasonOption)
                    <option value="{{ $reasonOption }}" @selected(old('reason') === $reasonOption)>{{ $reasonOption }}</option>
                  @endforeach
                </select>
              </div>

              <div class="rs-switch-row">
                <div class="rs-switch-row__text">Recalculate total based on new nights</div>
                <label class="rs-switch mb-0">
                  <input type="checkbox" class="js-rs-switch-input" name="recalculate_total" value="1" id="rdRecalculateTotal"
                    @checked(old('recalculate_total'))>
                  <span class="rs-switch-track"></span>
                  <span class="rs-switch-label js-rs-switch-label">{{ old('recalculate_total') ? 'On' : 'Off' }}</span>
                </label>
              </div>

              <div class="rs-switch-row mb-2">
                <div class="rs-switch-row__text">Resend updated invoice after saving</div>
                <label class="rs-switch mb-0">
                  <input type="checkbox" class="js-rs-switch-input" name="resend_invoice" value="1" id="rdResendInvoice"
                    @checked(old('resend_invoice'))>
                  <span class="rs-switch-track"></span>
                  <span class="rs-switch-label js-rs-switch-label">{{ old('resend_invoice') ? 'On' : 'Off' }}</span>
                </label>
              </div>

              <div class="rs-resend-wrap @if(old('resend_invoice')) is-visible @endif" id="rdResendInvoiceWrap">
                <div class="form-group mb-0">
                  <label class="control-label">Invoice email</label>
                  <input type="email" class="form-control @error('resend_invoice_email') is-invalid @enderror"
                    name="resend_invoice_email" id="rdResendInvoiceEmail"
                    value="{{ old('resend_invoice_email', $defaultInvoiceEmail) }}" placeholder="guest@example.com">
                </div>
              </div>

              <div id="rdStayPreview" class="alert rs-preview mt-3" style="display:none;" role="alert"></div>

              <div class="tile-footer mt-4 pt-3 border-top px-0 pb-0">
                <button type="submit" class="btn btn-primary" id="rdStaySubmit">
                  <i class="fa fa-save"></i> Save stay dates
                </button>
              </div>
            </form>

            @if($booking->stayChangeHistory() !== [])
              <div class="rs-history">
                <div class="rs-history__title"><i class="fa fa-history"></i> Stay change history</div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered table-hover mb-0">
                    <thead>
                      <tr>
                        <th>When</th>
                        <th>Dates</th>
                        <th>Reason</th>
                        <th>By</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach(array_reverse($booking->stayChangeHistory()) as $entry)
                        <tr>
                          <td>{{ isset($entry['changed_at']) ? \Carbon\Carbon::parse($entry['changed_at'])->format('d/m/Y H:i') : '—' }}</td>
                          <td>
                            {{ ($entry['from_checkin'] ?? '—') }} → {{ ($entry['to_checkin'] ?? '—') }}<br>
                            <span class="text-muted">{{ ($entry['from_checkout'] ?? '—') }} → {{ ($entry['to_checkout'] ?? '—') }}</span>
                          </td>
                          <td>{{ $entry['reason'] ?? '—' }}</td>
                          <td>{{ $entry['changed_by'] ?? '—' }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endif
          @else
            <div class="alert alert-secondary mb-0">
              <i class="fa fa-info-circle"></i> This booking can no longer be updated because it is cancelled or already checked out.
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-5 mb-4">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title"><i class="fa fa-envelope"></i> Send Invoice</h3>
        </div>
        <div class="tile-body">
          @if($booking->isCancelled())
            <div class="alert alert-secondary mb-0">
              <i class="fa fa-ban"></i> Invoices cannot be sent for cancelled bookings.
            </div>
          @else
            @if($booking->invoiceSentAtLabel())
              <div class="alert alert-info">
                <i class="fa fa-check"></i> Last sent to <strong>{{ $booking->invoiceSentToLabel() }}</strong>
                on {{ $booking->invoiceSentAtLabel() }}.
              </div>
            @else
              <p class="text-muted">Invoice has not been sent yet. Enter the company or guest email below.</p>
            @endif

            <form method="POST" action="{{ route('hotel.reservations.send-invoice', $booking) }}">
              @csrf
              <div class="form-group">
                <label class="control-label">Invoice email</label>
                <input type="email" class="form-control @error('invoice_email') is-invalid @enderror" name="invoice_email"
                  value="{{ old('invoice_email', $defaultInvoiceEmail) }}" placeholder="company@example.com" required>
              </div>
              <button type="submit" class="btn btn-primary">
                <i class="fa fa-paper-plane"></i>
                {{ $booking->invoiceSentAtLabel() ? 'Resend invoice' : 'Send invoice' }}
              </button>
            </form>

            @if($booking->invoiceSendHistory() !== [])
              <div class="rs-history">
                <div class="rs-history__title"><i class="fa fa-history"></i> Invoice send history</div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered table-hover mb-0">
                    <thead>
                      <tr>
                        <th>When</th>
                        <th>Email</th>
                        <th>By</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach(array_reverse($booking->invoiceSendHistory()) as $entry)
                        <tr>
                          <td>{{ isset($entry['sent_at']) ? \Carbon\Carbon::parse($entry['sent_at'])->format('d/m/Y H:i') : '—' }}</td>
                          <td>{{ $entry['email'] ?? '—' }}</td>
                          <td>{{ $entry['sent_by'] ?? '—' }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>

  @include('hotel.pms.reservations.partials._reservation-services', [
    'booking' => $booking,
    'hotel' => $hotel,
    'serviceRows' => $serviceRows ?? [],
    'canAddServices' => $canAddServices ?? false,
    'pmsServices' => $pmsServices ?? collect(),
    'laundryCreateUrl' => $laundryCreateUrl ?? null,
    'swimmingCreateUrl' => $swimmingCreateUrl ?? null,
  ])

  {{-- Booking summary --}}
  <div class="row">
    <div class="col-md-12 mb-4">
      <div class="tile">
        <div class="tile-title-w-btn">
          <h3 class="title"><i class="fa fa-list-alt"></i> Booking Summary</h3>
        </div>
        <div class="tile-body">
          <div class="table-responsive">
            <table class="table table-bordered rs-summary-table mb-0">
              <tbody>
                @foreach($summary as $label => $value)
                  <tr>
                    <th>{{ $label }}</th>
                    <td>{{ $value }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if($roomLines !== [])
    <div class="row">
      <div class="col-md-12 mb-4">
        <div class="tile">
          <div class="tile-title-w-btn">
            <h3 class="title"><i class="fa fa-bed"></i> Room Lines</h3>
          </div>
          <div class="tile-body">
            <div class="table-responsive">
              <table class="table table-hover table-bordered rs-table mb-0">
                <thead>
                  <tr>
                    <th>Room</th>
                    <th>Rate plan</th>
                    <th>Meal plan</th>
                    <th># Rooms</th>
                    <th>Adults</th>
                    <th>Children</th>
                    <th>Daily rate</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($roomLines as $line)
                    @php
                      $occupancy = is_array($line['occupancy'] ?? null) ? $line['occupancy'] : [];
                    @endphp
                    <tr>
                      <td>{{ $line['roomName'] ?? $line['room_name'] ?? $line['roomCode'] ?? '—' }}</td>
                      <td>{{ $line['rateplanCode'] ?? $line['rateplan_code'] ?? '—' }}</td>
                      <td>{{ $line['mealPlan'] ?? $line['meal_plan'] ?? '—' }}</td>
                      <td>{{ $line['numberOfRooms'] ?? $line['roomCount'] ?? 1 }}</td>
                      <td>{{ $occupancy['adults'] ?? '—' }}</td>
                      <td>{{ $occupancy['children'] ?? '—' }}</td>
                      <td>{{ isset($line['dailyRate']) ? number_format((float) $line['dailyRate'], 2) : '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif

@endsection

@push('scripts')
  @if($canManageStay)
    <script>
      (function () {
        var previewUrl = @json(route('hotel.reservations.stay-preview', $booking));
        var originalCheckin = @json($booking->checkin?->format('Y-m-d'));
        var originalCheckout = @json($booking->checkout?->format('Y-m-d'));
        var currency = @json($booking->currency ?? '');

        var checkinInput = document.getElementById('rdStayCheckin');
        var checkoutInput = document.getElementById('rdStayCheckout');
        var previewBox = document.getElementById('rdStayPreview');
        var stayForm = document.getElementById('rdStayForm');
        var recalculateCheckbox = document.getElementById('rdRecalculateTotal');
        var resendCheckbox = document.getElementById('rdResendInvoice');
        var resendWrap = document.getElementById('rdResendInvoiceWrap');
        var resendEmail = document.getElementById('rdResendInvoiceEmail');
        var previewTimer = null;
        var lastPreview = null;

        function formatMoney(value) {
          return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;
        }

        function previewErrors(data) {
          if (!data || !data.errors) {
            return [];
          }

          if (Array.isArray(data.errors)) {
            return data.errors;
          }

          return Object.keys(data.errors).reduce(function (list, key) {
            var messages = data.errors[key];

            if (Array.isArray(messages)) {
              return list.concat(messages);
            }

            if (messages) {
              list.push(String(messages));
            }

            return list;
          }, []);
        }

        function renderPreview(data) {
          lastPreview = data;
          previewBox.style.display = 'block';
          previewBox.className = 'alert rs-preview';

          if (!data) {
            previewBox.classList.add('alert-secondary');
            previewBox.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Checking availability…';
            return;
          }

          var html = '';
          var errors = previewErrors(data);

          if (errors.length) {
            previewBox.classList.add('alert-danger');
            html += '<strong><i class="fa fa-times-circle"></i> Cannot save these dates</strong><ul class="mb-0 mt-2 pl-3">';
            errors.forEach(function (msg) { html += '<li>' + msg + '</li>'; });
            html += '</ul>';
          } else if (data.ok === false) {
            previewBox.classList.add('alert-danger');
            html += '<strong><i class="fa fa-times-circle"></i> These dates are not available.</strong>';
          } else {
            previewBox.classList.add('alert-success');
            html += '<strong><i class="fa fa-check-circle"></i> Dates are available</strong>';
            html += '<div class="mt-1">' + (data.nights?.old ?? '—') + ' night(s) → ' + (data.nights?.new ?? '—') + ' night(s)</div>';

            if (data.pricing && data.pricing.new_total !== undefined) {
              var willRecalculate = recalculateCheckbox && recalculateCheckbox.checked;
              var amountPaid = Number(data.pricing.amount_paid || 0);
              var newTotal = Number(data.pricing.new_total || 0);
              var balanceDue = Number(data.pricing.balance_due || 0);

              if (willRecalculate) {
                html += '<div class="mt-2 mb-1"><strong>Pricing when saved (Recalculate ON)</strong></div>';
                html += '<div class="rs-price-row"><span>Current booking total</span><span>' + formatMoney(data.pricing.old_total) + '</span></div>';
                html += '<div class="rs-price-row"><span>New total</span><span>' + formatMoney(newTotal) + '</span></div>';
                var diff = Number(data.pricing.difference || 0);
                if (Math.abs(diff) > 0.009) {
                  var diffClass = diff > 0 ? 'positive' : 'negative';
                  var diffLabel = diff > 0 ? '+' : '';
                  html += '<div class="rs-price-row rs-price-diff ' + diffClass + '"><span>Change</span><span>' + diffLabel + formatMoney(Math.abs(diff)) + '</span></div>';
                }
                if (amountPaid > 0.009) {
                  html += '<div class="rs-price-row"><span>Already paid</span><span>− ' + formatMoney(amountPaid) + '</span></div>';
                }
                html += '<div class="rs-price-row"><strong>Balance due</strong><strong>' + (balanceDue > 0.009 ? formatMoney(balanceDue) : 'Paid / nothing due') + '</strong></div>';
              } else {
                html += '<div class="mt-2 text-muted"><i class="fa fa-info-circle"></i> Dates only — total stays at <strong>' + formatMoney(data.pricing.old_total) + '</strong>. Turn <strong>Recalculate</strong> ON to apply the new total of <strong>' + formatMoney(newTotal) + '</strong>.</div>';
              }
            }

            if (data.rooms && data.rooms.length) {
              html += '<ul class="mb-0 mt-2 pl-3">';
              data.rooms.forEach(function (room) {
                html += '<li>' + room.room_name + ': ' + room.requested + ' needed, '
                  + room.booked_by_others + ' booked by others, capacity ' + room.capacity + '</li>';
              });
              html += '</ul>';
            }

            if (data.assigned_room) {
              html += '<div class="mt-2"><small><i class="fa fa-key"></i> Assigned room: ' + data.assigned_room + '</small></div>';
            }
          }

          if (data.warnings && data.warnings.length) {
            html += '<div class="alert alert-warning mt-2 mb-0 py-2"><strong><i class="fa fa-exclamation-triangle"></i> Warnings</strong><ul class="mb-0 mt-1 pl-3">';
            data.warnings.forEach(function (msg) { html += '<li>' + msg + '</li>'; });
            html += '</ul></div>';
          }

          previewBox.innerHTML = html;
        }

        function fetchPreview() {
          var checkin = checkinInput.value;
          var checkout = checkoutInput.value;

          if (!checkin || !checkout) {
            previewBox.style.display = 'none';
            lastPreview = null;
            return;
          }

          if (checkin === originalCheckin && checkout === originalCheckout) {
            previewBox.style.display = 'none';
            lastPreview = null;
            return;
          }

          if (checkin >= checkout) {
            renderPreview({
              ok: false,
              errors: ['Check-out must be after check-in. Update the check-out date to continue.'],
              warnings: [],
              nights: { old: 0, new: 0 },
              pricing: {},
              rooms: []
            });
            return;
          }

          previewBox.style.display = 'block';
          previewBox.className = 'alert alert-secondary rs-preview';
          previewBox.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Checking availability…';

          var url = previewUrl + '?checkin=' + encodeURIComponent(checkin) + '&checkout=' + encodeURIComponent(checkout);

          fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          })
            .then(function (response) {
              return response.text().then(function (text) {
                var data = null;

                try {
                  data = text ? JSON.parse(text) : null;
                } catch (error) {
                  throw new Error('invalid_json');
                }

                if (!response.ok && data && data.errors && !Array.isArray(data.errors)) {
                  data = {
                    ok: false,
                    errors: previewErrors(data),
                    warnings: [],
                    nights: { old: 0, new: 0 },
                    pricing: {},
                    rooms: []
                  };
                }

                return data;
              });
            })
            .then(renderPreview)
            .catch(function () {
              previewBox.className = 'alert alert-danger rs-preview';
              previewBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> Could not check availability. Please refresh the page and try again.';
            });
        }

        function schedulePreview() {
          clearTimeout(previewTimer);
          previewTimer = setTimeout(fetchPreview, 350);
        }

        document.querySelectorAll('.rd-stay-date').forEach(function (input) {
          input.addEventListener('change', schedulePreview);
          input.addEventListener('input', schedulePreview);
        });

        function syncSwitchLabel(input) {
          var label = input.closest('.rs-switch').querySelector('.js-rs-switch-label');
          if (label) {
            label.textContent = input.checked ? 'On' : 'Off';
          }
        }

        document.querySelectorAll('.js-rs-switch-input').forEach(function (input) {
          input.addEventListener('change', function () {
            syncSwitchLabel(input);
            if (lastPreview) {
              renderPreview(lastPreview);
            }
          });
        });

        resendCheckbox.addEventListener('change', function () {
          syncSwitchLabel(resendCheckbox);
          resendWrap.classList.toggle('is-visible', resendCheckbox.checked);
          if (resendCheckbox.checked) {
            recalculateCheckbox.checked = true;
            syncSwitchLabel(recalculateCheckbox);
            if (resendEmail && !resendEmail.value) {
              resendEmail.focus();
            }
          }
        });

        function showStayWarning(title, text) {
          if (typeof swal === 'function') {
            swal(title, text, 'warning');
            return;
          }

          alert(text);
        }

        function buildStayConfirmText(checkin, checkout) {
          var lines = [
            'Check-in: ' + checkin,
            'Check-out: ' + checkout,
          ];

          if (recalculateCheckbox.checked && lastPreview && lastPreview.pricing) {
            lines.push('New total: ' + formatMoney(lastPreview.pricing.new_total));
            var diff = Number(lastPreview.pricing.difference || 0);
            if (Math.abs(diff) > 0.009) {
              lines.push('Change: ' + (diff > 0 ? '+' : '−') + formatMoney(Math.abs(diff)));
            }
            var balanceDue = Number(lastPreview.pricing.balance_due || 0);
            lines.push('Balance due: ' + (balanceDue > 0.009 ? formatMoney(balanceDue) : 'Paid / nothing due'));
          } else {
            lines.push('Booking total will stay unchanged (Recalculate is off).');
          }

          if (resendCheckbox.checked) {
            lines.push('Updated invoice will be sent to ' + (resendEmail.value || 'the entered email') + '.');
          }

          return lines.join('\n');
        }

        var staySubmitConfirmed = false;

        stayForm.addEventListener('submit', function (event) {
          if (staySubmitConfirmed) {
            return;
          }

          event.preventDefault();

          var checkin = checkinInput.value;
          var checkout = checkoutInput.value;
          var datesChanged = checkin !== originalCheckin || checkout !== originalCheckout;

          if (!datesChanged) {
            showStayWarning('No changes', 'Please change at least one date before saving.');
            return;
          }

          if (checkin >= checkout) {
            showStayWarning('Invalid dates', 'Check-out must be after check-in.');
            return;
          }

          if (lastPreview && previewErrors(lastPreview).length) {
            showStayWarning('Not available', 'These dates are not available. Please review the availability message.');
            return;
          }

          var confirmText = buildStayConfirmText(checkin, checkout);

          if (typeof swal === 'function') {
            swal({
              title: 'Save stay dates?',
              text: confirmText,
              type: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Yes, save',
              cancelButtonText: 'Cancel',
              confirmButtonColor: '#940000',
            }, function (confirmed) {
              if (confirmed) {
                staySubmitConfirmed = true;
                stayForm.submit();
              }
            });

            return;
          }

          if (window.confirm('Save stay date changes?\n\n' + confirmText)) {
            staySubmitConfirmed = true;
            stayForm.submit();
          }
        });

        schedulePreview();
      })();
    </script>
  @endif
@endpush
