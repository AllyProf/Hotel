{{-- Calendar day detail modal --}}
<div class="modal fade" id="calendarDayModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content cal-day-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="calendarDayModalTitle">Selected Date</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="cal-day-section">
          <div class="cal-day-section-head">
            <span class="cal-day-section-label">Dynamic Rates:</span>
            <span class="cal-day-pill js-cal-dynamic-pill is-off">Off</span>
          </div>
          <div class="table-responsive">
            <table class="cal-day-table">
              <thead>
                <tr>
                  <th>Room</th>
                  <th>Mealplan</th>
                  <th>Occupancy</th>
                  <th>Rate</th>
                </tr>
              </thead>
              <tbody id="calDayRatesBody"></tbody>
            </table>
          </div>
        </div>

        <div class="cal-day-section mt-4">
          <div class="cal-day-section-head">
            <span class="cal-day-section-label">Inventory Reallocation:</span>
            <span class="cal-day-pill js-cal-realloc-pill is-off">Off</span>
          </div>
          <div class="table-responsive">
            <table class="cal-day-table">
              <thead>
                <tr>
                  <th>Room</th>
                  <th>Inventory</th>
                </tr>
              </thead>
              <tbody id="calDayInventoryBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn cal-day-close-btn" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@push('styles')
  <style>
    .cal-day-modal .modal-header {
      background: #940000;
      color: #fff;
      border: 0;
      padding: 10px 16px;
    }

    .cal-day-modal .modal-title {
      font-size: 15px;
      font-weight: 600;
    }

    .cal-day-modal .modal-body {
      padding: 18px 20px;
    }

    .cal-day-modal .modal-footer {
      border-top: 1px solid #e8eaed;
      padding: 10px 16px;
      justify-content: flex-end;
    }

    .cal-day-section-head {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .cal-day-section-label {
      font-weight: 700;
      font-size: 14px;
      color: #333;
    }

    .cal-day-pill {
      display: inline-block;
      min-width: 36px;
      padding: 2px 10px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: 700;
      text-align: center;
      color: #fff;
    }

    .cal-day-pill.is-off {
      background: #f15f5f;
    }

    .cal-day-pill.is-on {
      background: #940000;
    }

    .cal-day-table {
      width: 100%;
      border-collapse: collapse;
      margin: 0;
    }

    .cal-day-table thead th {
      background: #5a5a5a;
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      padding: 8px 12px;
      border: 1px solid #4a4a4a;
      text-align: left;
    }

    .cal-day-table tbody td {
      border: 1px solid #ddd;
      padding: 8px 12px;
      font-size: 13px;
      color: #333;
      background: #fff;
    }

    .cal-day-close-btn {
      background: #940000 !important;
      border: 1px solid #940000 !important;
      color: #fff !important;
      font-weight: 600;
      font-size: 13px;
      padding: 6px 20px;
      border-radius: 3px;
    }

    .cal-day-close-btn:hover {
      background: #7a0000 !important;
      border-color: #7a0000 !important;
      color: #fff !important;
    }

    .cal-day-empty {
      color: #888;
      font-style: italic;
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function () {
      var dayDetails = @json($calendar['dayDetails'] ?? []);
      var modal = $('#calendarDayModal');

      function setPill(el, enabled) {
        el.textContent = enabled ? 'On' : 'Off';
        el.classList.toggle('is-on', enabled);
        el.classList.toggle('is-off', !enabled);
      }

      function fillTable(tbody, rows, columns, emptyText) {
        tbody.innerHTML = '';
        if (!rows || !rows.length) {
          var tr = document.createElement('tr');
          var td = document.createElement('td');
          td.colSpan = columns;
          td.className = 'cal-day-empty';
          td.textContent = emptyText;
          tr.appendChild(td);
          tbody.appendChild(tr);
          return;
        }
        rows.forEach(function (row) {
          var tr = document.createElement('tr');
          columns.forEach(function (key) {
            var td = document.createElement('td');
            td.textContent = row[key] ?? '';
            tr.appendChild(td);
          });
          tbody.appendChild(tr);
        });
      }

      window.openCalendarDayModal = function (dateKey) {
        var detail = dayDetails[dateKey];
        if (!detail) return;

        document.getElementById('calendarDayModalTitle').textContent = 'Selected Date : ' + detail.label;
        setPill(document.querySelector('.js-cal-dynamic-pill'), !!detail.dynamic_rates);
        setPill(document.querySelector('.js-cal-realloc-pill'), !!detail.inventory_reallocation);
        fillTable(
          document.getElementById('calDayRatesBody'),
          detail.rates,
          ['room', 'mealplan', 'occupancy', 'rate'],
          'No rate plans configured.'
        );
        fillTable(
          document.getElementById('calDayInventoryBody'),
          detail.inventory,
          ['room', 'inventory'],
          'No rooms configured.'
        );
        modal.modal('show');
      };
    })();
  </script>
@endpush
