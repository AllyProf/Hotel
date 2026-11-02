@php
  $s = $summary;
  $nightsLeft = $s['nights_remaining'] ?? null;
  $nightsLeftLabel = $nightsLeft === null ? '—' : ($nightsLeft === 0 ? 'Today' : $nightsLeft.' left');
  $nightsSpent = isset($s['nights_stayed']) ? (string) $s['nights_stayed'] : '—';
  $balanceLabel = $s['balance_due'] ?? 'Paid';
@endphp

<div class="co-stay-stats">
  <div class="co-stay-stat">
    <div class="co-stay-stat__icon"><i class="fa fa-moon-o"></i></div>
    <div class="co-stay-stat__label">Nights spent</div>
    <div class="co-stay-stat__value">{{ $nightsSpent }}</div>
  </div>
  <div class="co-stay-stat {{ ! empty($s['is_checkout_day']) ? 'is-highlight' : '' }}">
    <div class="co-stay-stat__icon"><i class="fa fa-calendar-check-o"></i></div>
    <div class="co-stay-stat__label">Nights remaining</div>
    <div class="co-stay-stat__value">{{ $nightsLeftLabel }}</div>
  </div>
  <div class="co-stay-stat {{ ! empty($s['balance_due']) ? 'is-highlight' : '' }}">
    <div class="co-stay-stat__icon"><i class="fa fa-credit-card"></i></div>
    <div class="co-stay-stat__label">Balance</div>
    <div class="co-stay-stat__value">{{ $balanceLabel }}</div>
  </div>
</div>

<div class="co-stay-panel">
  <div class="co-stay-panel__head">
    <i class="fa fa-bed"></i>
    <span>Stay overview</span>
    @if(! empty($s['is_checkout_day']))
      <span class="co-stay-badge">Checkout day</span>
    @endif
  </div>
  <div class="co-stay-panel__body">
    <div class="co-stay-kv">
      @foreach([
        'Booking ID' => $s['booking_id'] ?? null,
        'Guest' => $s['guest'] ?? null,
        'PMS status' => $s['pms_status'] ?? null,
        'Source' => ($s['source'] ?? '') !== '—' ? ($s['source'] ?? null) : null,
        'Room type' => $s['room_type'] ?? null,
        'Room no.' => $s['room_no'] ?? null,
        'Check-in' => $s['checkin'] ?? null,
        'Check-out' => $s['checkout'] ?? null,
        'Checked in' => $s['checked_in_at'] ?? null,
        'Checked out' => $s['checked_out_at'] ?? null,
        'Guests' => $s['guest_count'] ?? null,
        'Meal plan' => $s['meal_plan'] ?? null,
        'Nights booked' => $s['nights_booked'] ?? null,
      ] as $label => $value)
        @if($value !== null && $value !== '')
          <div>
            <span class="co-stay-kv__label">{{ $label }}</span>
            <span class="co-stay-kv__value">{{ $value }}</span>
          </div>
        @endif
      @endforeach
    </div>
  </div>
</div>

<div class="co-stay-panel">
  <div class="co-stay-panel__head">
    <i class="fa fa-money"></i>
    <span>Charges &amp; payment</span>
  </div>
  <div class="co-stay-panel__body">
    <div class="co-stay-kv">
      @foreach([
        'Room rate' => $s['room_rate'] ?? null,
        'Payment' => $s['payment_type'] ?? null,
        'Total due' => $s['total_due'] ?? null,
        'Amount paid' => $s['amount_paid'] ?? null,
        'Balance due' => $s['balance_due'] ?? null,
        'Late checkout fee' => $s['late_checkout_fee'] ?? null,
      ] as $label => $value)
        @if($value !== null && $value !== '')
          <div>
            <span class="co-stay-kv__label">{{ $label }}</span>
            <span class="co-stay-kv__value">{{ $value }}</span>
          </div>
        @endif
      @endforeach
    </div>
  </div>
</div>

<div class="co-stay-panel">
  <div class="co-stay-panel__head">
    <i class="fa fa-shirtsinbulk"></i>
    <span>Laundry &amp; services</span>
  </div>
  <div class="co-stay-panel__body">
    @if(! empty($s['laundry_tickets']))
      <div class="table-responsive">
        <table class="table table-bordered co-stay-table mb-0">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Status</th>
              <th>Total</th>
              <th>Items</th>
              <th>Received</th>
              <th>Created by</th>
              <th>Done by</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($s['laundry_tickets'] as $t)
              <tr>
                <td>
                  <a href="{{ $t['url'] }}" target="_blank" rel="noopener">{{ $t['ticket_no'] }}</a>
                  @if(! empty($t['express']))
                    <span class="co-stay-badge">Express</span>
                  @endif
                </td>
                <td>{{ $t['status'] }}</td>
                <td>{{ $t['total'] }}</td>
                <td>{{ $t['items'] }}</td>
                <td>{{ $t['received_at'] ?? '—' }}</td>
                <td>{{ $t['created_by'] ?? '—' }}</td>
                <td>
                  @if(! empty($t['completed_by']))
                    {{ $t['completed_by'] }}
                    @if(! empty($t['completed_at']))
                      <br><small class="text-muted">{{ $t['completed_at'] }}</small>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td class="text-nowrap">
                  @if(! empty($t['print_url']))
                    <a href="{{ $t['print_url'] }}" class="btn btn-sm btn-light border" target="_blank" rel="noopener" title="Print bill">
                      <i class="fa fa-print"></i>
                    </a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if(! empty($s['folio_service_charges']))
      <div class="{{ ! empty($s['laundry_tickets']) ? 'mt-3' : '' }}">
        @if(! empty($s['laundry_tickets']))
          <h5 class="mb-2" style="font-size:13px;font-weight:700;">Other services (folio)</h5>
        @endif
        <div class="table-responsive">
          <table class="table table-bordered co-stay-table mb-0">
            <thead>
              <tr>
                <th>Service</th>
                <th>Amount</th>
                <th>Posted</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody>
              @foreach($s['folio_service_charges'] as $c)
                <tr>
                  <td>
                    {{ $c['service'] }}
                    @if(! empty($c['description']))
                      <br><small class="text-muted">{{ $c['description'] }}</small>
                    @endif
                  </td>
                  <td>{{ $c['amount'] }}</td>
                  <td>{{ $c['posted_at'] ?? '—' }}</td>
                  <td>{{ $c['posted_by'] ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    @if(empty($s['laundry_tickets']) && empty($s['folio_service_charges']) && ! empty($s['folio_laundry_charges']))
      <div class="table-responsive">
        <table class="table table-bordered co-stay-table mb-0">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Amount</th>
              <th>Posted</th>
            </tr>
          </thead>
          <tbody>
            @foreach($s['folio_laundry_charges'] as $c)
              <tr>
                <td>{{ $c['ticket_no'] }}</td>
                <td>{{ $c['amount'] }}</td>
                <td>{{ $c['posted_at'] ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @elseif(empty($s['laundry_tickets']) && empty($s['folio_service_charges']))
      <p class="co-stay-empty"><i class="fa fa-shirtsinbulk"></i>No laundry or extra services on this stay yet.</p>
    @endif
  </div>
</div>

@if(! empty($s['special_request']))
  <div class="co-stay-panel">
    <div class="co-stay-panel__head">
      <i class="fa fa-sticky-note-o"></i>
      <span>Guest notes</span>
    </div>
    <div class="co-stay-panel__body">
      <p class="mb-0" style="font-size:13px;line-height:1.5;">{{ $s['special_request'] }}</p>
    </div>
  </div>
@endif
