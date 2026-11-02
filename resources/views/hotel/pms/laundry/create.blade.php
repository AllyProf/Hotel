@extends('layouts.app')

@section('title', 'New Laundry Ticket')

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sw-panel {
      background: #fafafa;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      padding: 16px;
    }
    .sw-panel__title {
      font-size: 13px;
      font-weight: 700;
      color: #555;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .sv-header {
      padding: 18px 20px;
      border-bottom: 3px solid var(--sv-brand);
    }

    .sv-header h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-plus"></i> New Laundry Ticket</h1>
      <p>Amounts use laundry default currency ({{ $defaultCurrency ?? 'TZS' }}) from item prices</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.laundry.index') }}">Laundry</a></li>
      <li class="breadcrumb-item">New</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile sv-page">
        @if(session('error'))
          <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        <div class="sv-header">
          <h3>New ticket</h3>
        </div>

        <div class="sv-section">
    <form method="POST" action="{{ route('hotel.laundry.store') }}" id="ldOrderForm">
      @csrf

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label class="control-label">In-house guest</label>
            <select class="form-control js-ld-reservation" name="cm_reservation_id">
              <option value="">Walk-in / manual room</option>
              @foreach($reservations as $res)
                <option value="{{ $res->id }}" data-room="{{ $res->roomUnit?->room_number }}" data-guest="{{ $res->guestName() }}"
                  {{ (int) old('cm_reservation_id', $prefillReservationId ?? 0) === (int) $res->id ? 'selected' : '' }}>
                  Room {{ $res->roomUnit?->room_number ?? '—' }} — {{ $res->guestName() }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="col-md-2">
          <div class="form-group">
            <label class="control-label">
              Room
              <span class="text-danger js-ld-room-required" style="display:none;">*</span>
              <span class="text-muted small js-ld-room-walkin-hint">(optional for walk-in)</span>
            </label>
            <input type="text" class="form-control js-ld-room" name="room_no" maxlength="20" value="{{ old('room_no') }}"
              placeholder="Optional for walk-in">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label class="control-label">Guest name</label>
            <input type="text" class="form-control js-ld-guest" name="guest_name" maxlength="120" value="{{ old('guest_name') }}">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label class="control-label">&nbsp;</label>
            <label class="animated-checkbox d-block mb-0">
              <input type="checkbox" name="is_express" value="1" {{ old('is_express') ? 'checked' : '' }}>
              <span class="label-text">Express (+{{ $settings['express_surcharge_percent'] ?? 0 }}%)</span>
            </label>
          </div>
        </div>
      </div>

      <h5 class="mt-2 mb-3">Items</h5>
      <div class="table-responsive">
        <table class="table table-bordered table-sm" id="ldLinesTable">
          <thead>
            <tr>
              <th>Item</th>
              <th style="width:100px;">Qty</th>
              <th style="width:120px;">Unit price</th>
              <th style="width:180px;">Notes</th>
              <th style="width:48px;"></th>
            </tr>
          </thead>
          <tbody class="js-ld-lines">
            <tr class="js-ld-line">
              <td>
                <select class="form-control form-control-sm js-ld-price-select" name="lines[0][price_id]">
                  <option value="">Custom</option>
                  @foreach($prices as $p)
                    @php
                      $unit = $p->package?->abbreviation ?: ($p->package?->name ?: 'unit');
                    @endphp
                    <option value="{{ $p->id }}" data-name="{{ $p->item_name }}" data-price="{{ $p->unit_price }}">
                      {{ $p->item_name }} — {{ number_format((float) $p->unit_price, 2) }} {{ $p->currency }}/{{ $unit }}
                    </option>
                  @endforeach
                </select>
                <input type="text" class="form-control form-control-sm mt-1 js-ld-item-name" name="lines[0][item_name]" placeholder="Item name" maxlength="120">
              </td>
              <td><input type="number" class="form-control form-control-sm" name="lines[0][quantity]" value="1" min="0.01" step="0.01"></td>
              <td><input type="number" class="form-control form-control-sm js-ld-unit-price" name="lines[0][unit_price]" value="0" min="0" step="0.01"></td>
              <td><input type="text" class="form-control form-control-sm" name="lines[0][item_notes]" maxlength="500" placeholder="Color, make…"></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-light border mb-3 js-ld-add-line"><i class="fa fa-plus"></i> Add line</button>

      <div class="mb-3 p-3 bg-light border rounded js-ld-totals">
        <span class="mr-4">Subtotal: <strong class="js-ld-subtotal">0.00</strong> {{ $defaultCurrency ?? 'TZS' }}</span>
        <span class="mr-4 js-ld-surcharge-wrap" style="display:none;">Express surcharge: <strong class="js-ld-surcharge">0.00</strong> {{ $defaultCurrency ?? 'TZS' }}</span>
        <span>Total: <strong class="js-ld-total">0.00</strong> {{ $defaultCurrency ?? 'TZS' }}</span>
      </div>

      @include('hotel.pms.partials._desk-payment-fields', ['paymentOptions' => $paymentOptions ?? []])

      <div class="form-group">
        <label class="control-label">Order notes</label>
        <input type="text" class="form-control" name="notes" maxlength="500" value="{{ old('notes') }}">
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;">Create ticket</button>
        <a href="{{ route('hotel.laundry.index') }}" class="btn btn-light border">Cancel</a>
      </div>
    </form>
        </div>
      </div>
    </div>
  </div>

  <template id="ldLineTemplate">
    <tr class="js-ld-line">
      <td>
        <select class="form-control form-control-sm js-ld-price-select" data-name-template="lines[__INDEX__][price_id]">
          <option value="">Custom</option>
          @foreach($prices as $p)
            <option value="{{ $p->id }}" data-name="{{ $p->item_name }}" data-price="{{ $p->unit_price }}">
              {{ $p->item_name }}
            </option>
          @endforeach
        </select>
        <input type="text" class="form-control form-control-sm mt-1 js-ld-item-name" data-name-template="lines[__INDEX__][item_name]" placeholder="Item name" maxlength="120">
      </td>
      <td><input type="number" class="form-control form-control-sm" data-name-template="lines[__INDEX__][quantity]" value="1" min="0.01" step="0.01"></td>
      <td><input type="number" class="form-control form-control-sm js-ld-unit-price" data-name-template="lines[__INDEX__][unit_price]" value="0" min="0" step="0.01"></td>
      <td><input type="text" class="form-control form-control-sm" data-name-template="lines[__INDEX__][item_notes]" maxlength="500"></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger js-ld-remove-line"><i class="fa fa-trash"></i></button></td>
    </tr>
  </template>
@endsection

@push('scripts')
  @include('hotel.pms.partials._desk-payment-script', ['paymentOptions' => $paymentOptions ?? []])
  <script>
    (function () {
      var resSelect = document.querySelector('.js-ld-reservation');
      var roomInput = document.querySelector('.js-ld-room');
      var guestInput = document.querySelector('.js-ld-guest');
      var roomRequiredMark = document.querySelector('.js-ld-room-required');
      var walkinHint = document.querySelector('.js-ld-room-walkin-hint');
      var requireRoomForInHouse = @json(! empty($settings['require_room_number']));
      var expressPercent = @json((int) ($settings['express_surcharge_percent'] ?? 0));
      var subtotalEl = document.querySelector('.js-ld-subtotal');
      var surchargeEl = document.querySelector('.js-ld-surcharge');
      var surchargeWrap = document.querySelector('.js-ld-surcharge-wrap');
      var totalEl = document.querySelector('.js-ld-total');
      var expressCb = document.querySelector('[name="is_express"]');

      function syncGuestFields() {
        if (!resSelect) return;
        var isWalkIn = resSelect.value === '';
        if (isWalkIn) {
          if (roomRequiredMark) roomRequiredMark.style.display = 'none';
          if (walkinHint) walkinHint.style.display = '';
          if (roomInput) roomInput.removeAttribute('required');
        } else {
          if (walkinHint) walkinHint.style.display = 'none';
          if (roomRequiredMark) {
            roomRequiredMark.style.display = requireRoomForInHouse ? '' : 'none';
          }
          if (roomInput) {
            if (requireRoomForInHouse) roomInput.setAttribute('required', 'required');
            else roomInput.removeAttribute('required');
          }
          var opt = resSelect.options[resSelect.selectedIndex];
          if (roomInput && !roomInput.value.trim()) {
            roomInput.value = opt.getAttribute('data-room') || '';
          }
          if (guestInput && !guestInput.value.trim()) {
            guestInput.value = opt.getAttribute('data-guest') || '';
          }
        }
        if (typeof window.deskPaymentSetWalkIn === 'function') {
          window.deskPaymentSetWalkIn(isWalkIn);
        }
      }

      if (resSelect) {
        resSelect.addEventListener('change', function () {
          var opt = resSelect.options[resSelect.selectedIndex];
          if (resSelect.value !== '') {
            if (roomInput) roomInput.value = opt.getAttribute('data-room') || '';
            if (guestInput) guestInput.value = opt.getAttribute('data-guest') || '';
          }
          syncGuestFields();
        });
        syncGuestFields();
      }

      function updateOrderTotals() {
        if (!tbody) return;
        var subtotal = 0;
        tbody.querySelectorAll('.js-ld-line').forEach(function (row) {
          var qty = parseFloat(row.querySelector('[name*="[quantity]"]')?.value || '0') || 0;
          var price = parseFloat(row.querySelector('.js-ld-unit-price')?.value || '0') || 0;
          subtotal += qty * price;
        });
        subtotal = Math.round(subtotal * 100) / 100;
        var surcharge = expressCb && expressCb.checked ? Math.round(subtotal * expressPercent / 100 * 100) / 100 : 0;
        var total = Math.round((subtotal + surcharge) * 100) / 100;
        if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);
        if (surchargeEl) surchargeEl.textContent = surcharge.toFixed(2);
        if (surchargeWrap) surchargeWrap.style.display = surcharge > 0 ? '' : 'none';
        if (totalEl) totalEl.textContent = total.toFixed(2);
        if (typeof window.deskPaymentUpdateTotal === 'function') {
          window.deskPaymentUpdateTotal(total, @json($defaultCurrency ?? 'TZS'));
        }
      }

      function bindPriceSelect(row) {
        var sel = row.querySelector('.js-ld-price-select');
        var nameInput = row.querySelector('.js-ld-item-name');
        var priceInput = row.querySelector('.js-ld-unit-price');
        if (!sel) return;
        sel.addEventListener('change', function () {
          var opt = sel.options[sel.selectedIndex];
          if (opt.value && nameInput) nameInput.value = opt.getAttribute('data-name') || '';
          if (opt.value && priceInput) priceInput.value = opt.getAttribute('data-price') || '0';
          updateOrderTotals();
        });
        if (priceInput) priceInput.addEventListener('input', updateOrderTotals);
        var qtyInput = row.querySelector('[name*="[quantity]"]');
        if (qtyInput) qtyInput.addEventListener('input', updateOrderTotals);
      }

      document.querySelectorAll('.js-ld-line').forEach(bindPriceSelect);
      if (expressCb) expressCb.addEventListener('change', updateOrderTotals);
      updateOrderTotals();

      var tbody = document.querySelector('.js-ld-lines');
      var template = document.getElementById('ldLineTemplate');
      var addBtn = document.querySelector('.js-ld-add-line');
      var lineIndex = 1;

      function applyNames(row, index) {
        row.querySelectorAll('[data-name-template]').forEach(function (el) {
          el.name = el.getAttribute('data-name-template').replace('__INDEX__', String(index));
        });
        var sel = row.querySelector('.js-ld-price-select');
        if (sel && !sel.getAttribute('name')) {
          sel.name = 'lines[' + index + '][price_id]';
        }
      }

      if (addBtn && template && tbody) {
        addBtn.addEventListener('click', function () {
          var clone = template.content.cloneNode(true);
          var row = clone.querySelector('.js-ld-line');
          applyNames(row, lineIndex++);
          tbody.appendChild(clone);
          bindPriceSelect(tbody.lastElementChild);
          updateOrderTotals();
        });

        tbody.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-ld-remove-line');
          if (!btn) return;
          var rows = tbody.querySelectorAll('.js-ld-line');
          if (rows.length <= 1) return;
          btn.closest('.js-ld-line').remove();
          updateOrderTotals();
        });
      }
    })();
  </script>
@endpush
