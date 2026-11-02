@php
  $currency = $sellCurrency ?? $defaultCurrency ?? 'TZS';
  $allowWalkIn = ! empty($barSettings['allow_walk_in']);
  $catalog = $sellCatalog ?? [];
  $sellCatalogLayout = $sellCatalogLayout ?? 'search';
  $catalogView = $catalogView ?? 'list';
  $salesCreateUrl = route('sales.create');
@endphp

@push('styles')
  <style>
    .bar-menu-search-wrap {
      position: relative;
    }
    .bar-menu-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 40;
      background: #fff;
      border: 1px solid #ddd;
      border-top: 0;
      border-radius: 0 0 4px 4px;
      max-height: 280px;
      overflow-y: auto;
      display: none;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .bar-menu-dropdown.is-open {
      display: block;
    }
    .bar-menu-dropdown button {
      display: block;
      width: 100%;
      text-align: left;
      border: 0;
      border-bottom: 1px solid #f0f0f0;
      background: #fff;
      padding: 10px 12px;
      font-size: 13px;
      cursor: pointer;
    }
    .bar-menu-dropdown button:last-child {
      border-bottom: 0;
    }
    .bar-menu-dropdown button:hover,
    .bar-menu-dropdown button:focus {
      background: #f5f5f5;
      outline: none;
    }
    .bar-menu-dropdown .bar-menu-empty {
      padding: 12px;
      font-size: 13px;
      color: #888;
      margin: 0;
    }
    #barCartTable .js-bar-line-qty {
      -moz-appearance: textfield;
    }
    #barCartTable .js-bar-line-qty::-webkit-outer-spin-button,
    #barCartTable .js-bar-line-qty::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .bar-sell-view-toggle .btn.is-active {
      background: var(--brand);
      border-color: var(--brand);
      color: #fff;
    }
    .bar-sell-catalog-list .table td {
      vertical-align: middle;
    }
    .bar-sell-catalog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 10px;
      max-height: 420px;
      overflow-y: auto;
    }
    .bar-sell-catalog-grid.is-hidden,
    .bar-sell-catalog-list.is-hidden {
      display: none;
    }
    .bar-sell-grid-card {
      border: 1px solid #dde1e6;
      border-radius: 4px;
      padding: 10px;
      background: #fff;
      cursor: pointer;
      text-align: left;
      width: 100%;
    }
    .bar-sell-grid-card:hover {
      border-color: var(--brand);
      background: #fdf5f5;
    }
    .bar-sell-grid-card .name {
      font-weight: 700;
      font-size: 13px;
      display: block;
    }
    .bar-sell-grid-card .meta {
      font-size: 11px;
      color: #666;
    }
  </style>
@endpush

<div class="px-3">
  @if(empty($catalog))
    <p class="text-muted mb-0">
      No items ready to sell. Register bar items with sell prices, receive stock to the bar, then return here.
      <a href="{{ route('hotel.bar.index', ['tab' => 'stock']) }}">View stock</a>
    </p>
  @else
    <form method="POST" action="{{ route('hotel.bar.sales.store') }}" id="barSaleForm" class="mb-4" @if($sellCatalogLayout === 'browse') data-no-loader @endif>
      @csrf
      @if($sellCatalogLayout === 'browse')
        <input type="hidden" name="_return" value="sales.create">
      @endif

      <div class="row mb-3">
        <div class="col-md-4">
          <div class="form-group mb-md-0">
            <label class="control-label">Guest</label>
            <select class="form-control js-ld-reservation" name="cm_reservation_id">
              @if($allowWalkIn)
                <option value="">Walk-in / external</option>
              @else
                <option value="">Select in-house guest…</option>
              @endif
              @foreach($inHouseReservations ?? [] as $res)
                <option value="{{ $res->id }}" data-room="{{ $res->roomUnit?->room_number }}" data-guest="{{ $res->guestName() }}"
                  {{ (int) old('cm_reservation_id') === (int) $res->id ? 'selected' : '' }}>
                  Room {{ $res->roomUnit?->room_number ?? '—' }} — {{ $res->guestName() }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="col-md-2">
          <div class="form-group mb-md-0">
            <label class="control-label">Room</label>
            <input type="text" class="form-control js-bar-room" name="room_no" maxlength="40" value="{{ old('room_no') }}">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group mb-md-0">
            <label class="control-label">Guest name @if($allowWalkIn)<span class="text-muted small">(walk-in)</span>@endif</label>
            <input type="text" class="form-control js-bar-guest" name="guest_name" maxlength="200" value="{{ old('guest_name') }}">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-5 mb-3">
          <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <h5 class="mb-0">Menu</h5>
            @if($sellCatalogLayout === 'browse')
              <div class="btn-group btn-group-sm bar-sell-view-toggle" role="group" aria-label="Catalog view">
                <a href="{{ $salesCreateUrl }}?view=list" class="btn btn-light border {{ $catalogView === 'list' ? 'is-active' : '' }}">
                  <i class="fa fa-list"></i> List
                </a>
                <a href="{{ $salesCreateUrl }}?view=grid" class="btn btn-light border {{ $catalogView === 'grid' ? 'is-active' : '' }}">
                  <i class="fa fa-th"></i> Grid
                </a>
              </div>
            @endif
          </div>

          @if($sellCatalogLayout === 'browse')
            <input type="search" class="form-control mb-2 js-bar-browse-filter" placeholder="Filter items…" autocomplete="off">
            <div class="bar-sell-catalog-list {{ $catalogView === 'grid' ? 'is-hidden' : '' }}" data-browse-panel="list">
              <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                <table class="table table-sm table-hover table-bordered mb-0">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th class="text-right">Stock</th>
                      <th style="width:72px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($catalog as $entry)
                      <tr class="js-bar-browse-row" data-search="{{ strtolower($entry['name'].' '.($entry['category'] ?? '')) }}">
                        <td>
                          <strong>{{ $entry['name'] }}</strong>
                          @if(!empty($entry['category']))
                            <span class="text-muted small"> · {{ $entry['category'] }}</span>
                          @endif
                        </td>
                        <td class="text-right">{{ (int) round($entry['stock_pieces'] ?? 0) }} pc</td>
                        <td class="text-right">
                          <button type="button" class="btn btn-sm btn-primary js-bar-browse-add" data-item-id="{{ $entry['id'] }}">Add</button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
            <div class="bar-sell-catalog-grid {{ $catalogView === 'list' ? 'is-hidden' : '' }}" data-browse-panel="grid">
              @foreach($catalog as $entry)
                <button type="button" class="bar-sell-grid-card js-bar-browse-add js-bar-browse-row"
                  data-item-id="{{ $entry['id'] }}"
                  data-search="{{ strtolower($entry['name'].' '.($entry['category'] ?? '')) }}">
                  <span class="name">{{ $entry['name'] }}</span>
                  <span class="meta">{{ (int) round($entry['stock_pieces'] ?? 0) }} pc @if(!empty($entry['category'])) · {{ $entry['category'] }} @endif</span>
                </button>
              @endforeach
            </div>
          @else
            <div class="bar-menu-search-wrap">
              <input type="search" class="form-control js-bar-menu-search" placeholder="Type item name to search…" autocomplete="off">
              <div class="bar-menu-dropdown js-bar-menu-dropdown" role="listbox"></div>
            </div>
          @endif
        </div>

        <div class="col-lg-7 mb-3">
          <h5 class="mb-2">Cart</h5>
          <div class="table-responsive">
            <table class="table table-bordered table-sm mb-2" id="barCartTable">
              <thead>
                <tr>
                  <th>Item</th>
                  <th style="width:130px;">Sell as</th>
                  <th style="width:80px;">Qty</th>
                  <th style="width:100px;">Price</th>
                  <th style="width:90px;">Line</th>
                  <th style="width:40px;"></th>
                </tr>
              </thead>
              <tbody class="js-bar-cart-lines"></tbody>
            </table>
          </div>
          <p class="text-muted small mb-2 js-bar-cart-empty">Search the menu and pick items to add here.</p>

          <div class="p-3 bg-light border rounded mb-3">
            Total: <strong class="js-bar-sale-total">0</strong> {{ $currency }}
          </div>

          @include('hotel.pms.partials._desk-payment-fields', [
            'paymentOptions' => array_merge($paymentOptions ?? [], ['currency' => $currency]),
            'deskPaymentHideIntro' => true,
            'deskPaymentHideInhouseHint' => true,
            'deskPaymentCompactAmount' => true,
          ])

          <div class="form-group">
            <label class="control-label">Sale notes</label>
            <input type="text" class="form-control" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Optional">
          </div>

          <button type="submit" class="btn btn-primary" id="barSaleSubmit" disabled>
            <i class="fa fa-check"></i> Complete sale
          </button>
        </div>
      </div>
    </form>
  @endif

  @if(($recentBarSales ?? collect())->isNotEmpty())
    <h5 class="mt-4 mb-2">Recent sales</h5>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Sale #</th>
            <th>When</th>
            <th>Guest</th>
            <th>Total</th>
            <th>Folio</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recentBarSales as $sale)
            <tr>
              <td>{{ $sale->sale_no }}</td>
              <td>{{ $sale->sold_at?->format('d/m/Y H:i') }}</td>
              <td>
                @if($sale->room_no)
                  Rm {{ $sale->room_no }} —
                @endif
                {{ $sale->guest_name ?? '—' }}
              </td>
              <td>{{ $sale->formattedTotal($hotel) }}</td>
              <td>{{ $sale->posted_to_folio ? 'On folio' : ($sale->cm_reservation_id ? 'Not posted' : 'Walk-in') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<template id="barCartLineTemplate">
  <tr class="js-bar-cart-line">
    <td class="js-bar-line-name"></td>
    <td>
      <select class="form-control form-control-sm js-bar-line-method" data-name-template="lines[__INDEX__][sell_package_id]"></select>
      <input type="hidden" data-name-template="lines[__INDEX__][item_id]" class="js-bar-line-item-id">
    </td>
    <td><input type="number" class="form-control form-control-sm js-bar-line-qty" data-name-template="lines[__INDEX__][quantity]" value="1" min="0.01" step="0.01"></td>
    <td class="js-bar-line-unit text-right small"></td>
    <td class="js-bar-line-total text-right font-weight-bold"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger js-bar-remove-line"><i class="fa fa-trash"></i></button></td>
  </tr>
</template>

@push('scripts')
  @if(! empty($catalog))
    @include('hotel.pms.partials._desk-payment-script', ['paymentOptions' => $paymentOptions ?? []])
    <script>
      (function () {
        var currency = @json($currency);
        var menuCatalog = @json($catalog);
        var cartBody = document.querySelector('.js-bar-cart-lines');
        var cartEmpty = document.querySelector('.js-bar-cart-empty');
        var totalEl = document.querySelector('.js-bar-sale-total');
        var submitBtn = document.getElementById('barSaleSubmit');
        var lineIndex = 0;
        var template = document.getElementById('barCartLineTemplate');
        var resSelect = document.querySelector('.js-ld-reservation');
        var roomInput = document.querySelector('.js-bar-room');
        var guestInput = document.querySelector('.js-bar-guest');

        function formatMoney(n) {
          var v = Math.round((parseFloat(n) || 0) * 100) / 100;
          if (Math.abs(v - Math.round(v)) < 0.001) {
            return String(Math.round(v));
          }
          return v.toFixed(2);
        }

        function syncGuestFromReservation() {
          if (!resSelect) return;
          var opt = resSelect.options[resSelect.selectedIndex];
          if (!opt || !opt.value) return;
          if (roomInput && opt.dataset.room) roomInput.value = opt.dataset.room;
          if (guestInput && opt.dataset.guest) guestInput.value = opt.dataset.guest;
        }

        if (resSelect) {
          resSelect.addEventListener('change', syncGuestFromReservation);
          syncGuestFromReservation();
        }

        function applyNameTemplates(row, index) {
          row.querySelectorAll('[data-name-template]').forEach(function (el) {
            el.name = el.getAttribute('data-name-template').replace('__INDEX__', String(index));
          });
        }

        function lineTotal(row) {
          var qty = parseFloat(row.querySelector('.js-bar-line-qty').value || '0') || 0;
          var price = parseFloat(row.dataset.unitPrice || '0') || 0;
          return Math.round(qty * price * 100) / 100;
        }

        function syncTotals() {
          var total = 0;
          cartBody.querySelectorAll('.js-bar-cart-line').forEach(function (row) {
            var lt = lineTotal(row);
            total += lt;
            row.querySelector('.js-bar-line-total').textContent = formatMoney(lt);
          });
          total = Math.round(total * 100) / 100;
          totalEl.textContent = formatMoney(total);
          var hasLines = cartBody.querySelectorAll('.js-bar-cart-line').length > 0;
          if (cartEmpty) cartEmpty.style.display = hasLines ? 'none' : '';
          if (submitBtn) submitBtn.disabled = !hasLines;
          if (window.deskPaymentUpdateTotal) {
            window.deskPaymentUpdateTotal(total, currency);
          }
        }

        function bindLineEvents(row) {
          row.querySelector('.js-bar-line-qty').addEventListener('input', syncTotals);
          row.querySelector('.js-bar-line-method').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            row.dataset.unitPrice = opt.dataset.price || '0';
            row.querySelector('.js-bar-line-unit').textContent = formatMoney(opt.dataset.price || '0');
            syncTotals();
          });
          row.querySelector('.js-bar-remove-line').addEventListener('click', function () {
            row.remove();
            syncTotals();
          });
        }

        function addLine(itemId, name, methods) {
          if (!template || !cartBody) return;
          var row = template.content.firstElementChild.cloneNode(true);
          var idx = lineIndex++;
          applyNameTemplates(row, idx);
          row.querySelector('.js-bar-line-name').textContent = name;
          row.querySelector('.js-bar-line-item-id').value = itemId;
          var methodSelect = row.querySelector('.js-bar-line-method');
          methods.forEach(function (m, i) {
            var opt = document.createElement('option');
            opt.value = m.package_id || '';
            opt.textContent = m.label + ' — ' + parseFloat(m.unit_price).toFixed(2);
            opt.dataset.price = m.unit_price;
            if (i === 0) opt.selected = true;
            methodSelect.appendChild(opt);
          });
          var firstPrice = methods[0] ? methods[0].unit_price : 0;
          row.dataset.unitPrice = firstPrice;
          row.querySelector('.js-bar-line-unit').textContent = formatMoney(firstPrice);
          bindLineEvents(row);
          cartBody.appendChild(row);
          syncTotals();
        }

        var menuSearch = document.querySelector('.js-bar-menu-search');
        var menuDropdown = document.querySelector('.js-bar-menu-dropdown');
        function closeMenuDropdown() {
          if (menuDropdown) {
            menuDropdown.classList.remove('is-open');
            menuDropdown.innerHTML = '';
          }
        }

        function pickMenuEntry(entry) {
          if (!entry || !entry.methods || !entry.methods.length) return;
          addLine(String(entry.id), entry.name, entry.methods);
          if (menuSearch) {
            menuSearch.value = '';
          }
          closeMenuDropdown();
          if (menuSearch) menuSearch.focus();
        }

        function renderMenuDropdown() {
          if (!menuSearch || !menuDropdown) return;
          var q = (menuSearch.value || '').trim().toLowerCase();
          menuDropdown.innerHTML = '';

          if (q.length === 0) {
            menuDropdown.classList.remove('is-open');
            return;
          }

          var matches = menuCatalog.filter(function (entry) {
            var text = (entry.name + ' ' + (entry.category || '')).toLowerCase();
            return text.indexOf(q) !== -1;
          });

          if (matches.length === 0) {
            menuDropdown.innerHTML = '<p class="bar-menu-empty">No items match your search.</p>';
            menuDropdown.classList.add('is-open');
            return;
          }

          matches.slice(0, 40).forEach(function (entry) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('role', 'option');
            var stock = Math.round(parseFloat(entry.stock_pieces) || 0);
            var nameEl = document.createElement('span');
            nameEl.className = 'font-weight-bold';
            nameEl.textContent = entry.name;
            btn.appendChild(nameEl);
            if (entry.category) {
              var catEl = document.createElement('span');
              catEl.className = 'text-muted small';
              catEl.textContent = ' · ' + entry.category;
              btn.appendChild(catEl);
            }
            var stockEl = document.createElement('span');
            stockEl.className = 'float-right text-muted small';
            stockEl.textContent = stock + ' pc';
            btn.appendChild(stockEl);
            btn.addEventListener('click', function () {
              pickMenuEntry(entry);
            });
            menuDropdown.appendChild(btn);
          });

          menuDropdown.classList.add('is-open');
        }

        if (menuSearch) {
          menuSearch.addEventListener('input', renderMenuDropdown);
          menuSearch.addEventListener('focus', renderMenuDropdown);
          menuSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
              closeMenuDropdown();
            }
          });
        }

        document.addEventListener('click', function (e) {
          if (!menuSearch || !menuDropdown) return;
          if (menuSearch.contains(e.target) || menuDropdown.contains(e.target)) return;
          closeMenuDropdown();
        });

        var catalogById = {};
        menuCatalog.forEach(function (entry) {
          catalogById[String(entry.id)] = entry;
        });

        document.querySelectorAll('.js-bar-browse-add').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var entry = catalogById[String(btn.getAttribute('data-item-id') || '')];
            pickMenuEntry(entry);
          });
        });

        var browseFilter = document.querySelector('.js-bar-browse-filter');
        if (browseFilter) {
          browseFilter.addEventListener('input', function () {
            var q = (this.value || '').trim().toLowerCase();
            document.querySelectorAll('.js-bar-browse-row').forEach(function (row) {
              var hay = row.getAttribute('data-search') || '';
              row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
            });
          });
        }

        syncTotals();
      })();
    </script>
  @endif
@endpush
