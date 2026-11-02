<div class="row">
  <div class="col-md-12 mb-4">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title"><i class="fa fa-th-large"></i> Services &amp; folio charges</h3>
        @if($canAddServices ?? false)
          <div class="btn-group">
            @if(! empty($laundryCreateUrl))
              <a href="{{ $laundryCreateUrl }}" class="btn btn-primary btn-sm" style="background:#940000;border-color:#940000;">
                <i class="fa fa-shirtsinbulk"></i> Add laundry
              </a>
            @endif
            @if(! empty($swimmingCreateUrl))
              <a href="{{ $swimmingCreateUrl }}" class="btn btn-primary btn-sm" style="background:#940000;border-color:#940000;">
                <i class="fa fa-tint"></i> Add pool entry
              </a>
            @endif
          </div>
        @endif
      </div>
      <div class="tile-body">
        @if(empty($serviceRows))
          <p class="text-muted mb-0">No extra services recorded for this stay yet.</p>
        @else
          <div class="table-responsive mb-3">
            <table class="table table-hover table-bordered rs-table mb-0">
              <thead>
                <tr>
                  <th>Service</th>
                  <th>Reference</th>
                  <th>Details</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Folio</th>
                  <th>Staff</th>
                  <th>When</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach($serviceRows as $row)
                  <tr>
                    <td><strong>{{ $row['service'] }}</strong></td>
                    <td>
                      @if(! empty($row['url']))
                        <a href="{{ $row['url'] }}">{{ $row['reference'] }}</a>
                      @else
                        {{ $row['reference'] }}
                      @endif
                    </td>
                    <td>{{ $row['description'] ?? '—' }}</td>
                    <td>{{ $row['amount'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>
                      @if($row['posted'] ?? false)
                        <span class="badge badge-success">{{ $row['posted_label'] ?? 'On folio' }}</span>
                      @else
                        <span class="badge badge-secondary">{{ $row['posted_label'] ?? 'Not posted' }}</span>
                      @endif
                    </td>
                    <td>{{ $row['staff'] ?? '—' }}</td>
                    <td>{{ $row['when'] ?? '—' }}</td>
                    <td class="text-nowrap">
                      @if(! empty($row['print_url']))
                        <a href="{{ $row['print_url'] }}" class="btn btn-sm btn-light border" target="_blank" rel="noopener" title="Print bill">
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

        @if($canAddServices ?? false)
          <div class="border-top pt-3 mt-2">
            <h5 class="mb-3" style="font-size:14px;font-weight:700;"><i class="fa fa-plus-circle"></i> Add service charge</h5>
            <form method="POST" action="{{ route('hotel.reservations.service-charges.store', $booking) }}" class="row">
              @csrf
              <div class="col-md-3">
                <div class="form-group mb-2">
                  <label class="control-label">From catalog (optional)</label>
                  <select class="form-control form-control-sm js-rs-service-pick" name="hotel_pms_service_id">
                    <option value="">Custom / other</option>
                    @foreach($pmsServices as $svc)
                      <option value="{{ $svc->id }}"
                        data-name="{{ $svc->name }}"
                        data-amount="{{ $svc->amount }}"
                        data-currency="{{ $svc->displayCurrency($hotel) }}">
                        {{ $svc->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-2">
                  <label class="control-label">Service name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control form-control-sm js-rs-service-name" name="service_name" maxlength="120" required value="{{ old('service_name') }}">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group mb-2">
                  <label class="control-label">Amount <span class="text-danger">*</span></label>
                  <input type="number" class="form-control form-control-sm js-rs-service-amount" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group mb-2">
                  <label class="control-label">Currency</label>
                  <input type="text" class="form-control form-control-sm js-rs-service-currency" name="currency" maxlength="3"
                    value="{{ old('currency', $booking->currency ?? $hotel->currency ?? 'TZS') }}">
                </div>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <div class="form-group mb-2 w-100">
                  <button type="submit" class="btn btn-primary btn-sm btn-block" style="background:#940000;border-color:#940000;">
                    Add to folio
                  </button>
                </div>
              </div>
              <div class="col-md-12">
                <div class="form-group mb-0">
                  <label class="control-label">Notes (optional)</label>
                  <input type="text" class="form-control form-control-sm" name="description" maxlength="500" value="{{ old('description') }}" placeholder="e.g. Spa treatment, minibar">
                </div>
              </div>
            </form>
            <p class="text-muted small mt-2 mb-0">Charges are added to the guest folio total and included in the check-out balance.</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    (function () {
      var pick = document.querySelector('.js-rs-service-pick');
      if (!pick) return;
      var nameInput = document.querySelector('.js-rs-service-name');
      var amountInput = document.querySelector('.js-rs-service-amount');
      var currencyInput = document.querySelector('.js-rs-service-currency');
      pick.addEventListener('change', function () {
        var opt = pick.options[pick.selectedIndex];
        if (!opt.value) return;
        if (nameInput) nameInput.value = opt.getAttribute('data-name') || '';
        if (amountInput && opt.getAttribute('data-amount')) amountInput.value = opt.getAttribute('data-amount');
        if (currencyInput && opt.getAttribute('data-currency')) currencyInput.value = opt.getAttribute('data-currency');
      });
    })();
  </script>
@endpush
