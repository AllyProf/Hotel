<div class="modal fade res-guest-modal" id="gbSelectGuestModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Guest</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group mb-3">
          <input type="search" class="form-control" id="gbGuestSearch" placeholder="Search by name, email, or phone">
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th></th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
              </tr>
            </thead>
            <tbody id="gbGuestTableBody">
              <tr><td colspan="4" class="text-center text-muted py-4">Type at least 2 characters to search.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@push('styles')
  <style>
    .res-guest-modal .modal-header {
      background: #940000;
      color: #fff;
    }
    .res-guest-modal .modal-header .close { color: #fff; opacity: 0.9; }
    #gbGuestTableBody tr { cursor: pointer; }
    #gbGuestTableBody tr.is-selected { background: rgba(148, 0, 0, 0.08); }
  </style>
@endpush

@push('scripts')
  <script>
    (function () {
      var searchUrl = @json(route('hotel.reservations.guests.search'));
      var searchInput = document.getElementById('gbGuestSearch');
      var tableBody = document.getElementById('gbGuestTableBody');
      var searchTimer = null;

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function fillGuest(guest) {
        if (typeof window.fillGbGuest === 'function') {
          window.fillGbGuest(guest);
          if (typeof jQuery !== 'undefined') {
            jQuery('#gbSelectGuestModal').modal('hide');
          }
          return;
        }

        var nameInput = document.querySelector('.js-gb-guest-name');
        var emailInput = document.querySelector('.js-gb-guest-email');
        var phoneInput = document.querySelector('.js-gb-guest-phone');

        if (nameInput) nameInput.value = guest.name || '';
        if (emailInput) emailInput.value = guest.email || '';
        if (phoneInput) phoneInput.value = guest.phone || '';

        if (typeof jQuery !== 'undefined') {
          jQuery('#gbSelectGuestModal').modal('hide');
        }
      }

      function renderGuests(guests) {
        if (!tableBody) return;

        if (!guests.length) {
          tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No guests found.</td></tr>';
          return;
        }

        tableBody.innerHTML = guests.map(function (guest) {
          return '<tr data-guest=\'' + JSON.stringify(guest).replace(/'/g, '&#39;') + '\'>' +
            '<td><input type="radio" name="gb_guest_pick"></td>' +
            '<td><strong>' + escapeHtml(guest.name) + '</strong></td>' +
            '<td>' + escapeHtml(guest.email) + '</td>' +
            '<td>' + escapeHtml(guest.phone) + '</td>' +
            '</tr>';
        }).join('');

        tableBody.querySelectorAll('tr[data-guest]').forEach(function (row) {
          row.addEventListener('click', function () {
            try {
              fillGuest(JSON.parse(row.getAttribute('data-guest')));
            } catch (e) {}
          });
        });
      }

      function loadGuests() {
        if (!tableBody || !searchInput) return;

        var q = searchInput.value.trim();
        if (q.length < 2) {
          tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Type at least 2 characters to search.</td></tr>';
          return;
        }

        tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Searching...</td></tr>';

        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (res) { return res.json(); })
          .then(function (data) { renderGuests(data.guests || []); })
          .catch(function () {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Could not load guests.</td></tr>';
          });
      }

      document.querySelectorAll('.js-gb-guest-search').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (typeof jQuery !== 'undefined') {
            jQuery('#gbSelectGuestModal').modal('show');
          }
          if (searchInput) {
            searchInput.value = document.querySelector('.js-gb-guest-name')?.value.trim() || '';
            loadGuests();
          }
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          clearTimeout(searchTimer);
          searchTimer = setTimeout(loadGuests, 300);
        });
      }
    })();
  </script>
@endpush
