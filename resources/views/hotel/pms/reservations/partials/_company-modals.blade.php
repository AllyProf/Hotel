@push('styles')
  <style>
    .res-company-modal .modal-header {
      background: #940000;
      color: #fff;
    }
    .res-company-modal .modal-header .close { color: #fff; opacity: 0.9; }
    .res-company-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
    }
    .res-company-toolbar__search {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
    }
    .res-company-toolbar__search label {
      margin: 0;
      font-size: 13px;
      font-weight: 700;
      color: #333;
    }
    .res-company-toolbar__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .res-company-table {
      width: 100%;
      border-collapse: collapse;
    }
    .res-company-table thead th {
      background: #2f2f2f;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 9px 10px;
      border: 1px solid #222;
      white-space: nowrap;
    }
    .res-company-table tbody td {
      border: 1px solid #ddd;
      padding: 8px 10px;
      font-size: 13px;
      background: #fff;
      vertical-align: middle;
    }
    .res-company-table tbody tr {
      cursor: pointer;
    }
    .res-company-table tbody tr:hover td {
      background: rgba(148, 0, 0, 0.05);
    }
    .res-company-table tbody tr.is-selected td {
      background: rgba(148, 0, 0, 0.1);
    }
    .res-company-section-title {
      font-size: 14px;
      font-weight: 700;
      color: #333;
      margin: 18px 0 12px;
      padding-bottom: 6px;
      border-bottom: 2px solid #940000;
      display: inline-block;
    }
  </style>
@endpush

<div class="modal fade res-company-modal" id="resSelectCompanyModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Company</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="res-company-toolbar">
          <div class="res-company-toolbar__search">
            <label for="resCompanySearch">Search Name</label>
            <input type="search" class="form-control form-control-sm" id="resCompanySearch"
              placeholder="Search..." style="min-width:220px;">
          </div>
          <div class="res-company-toolbar__actions">
            <button type="button" class="btn btn-sm btn-primary js-res-company-create">Create</button>
            <button type="button" class="btn btn-sm btn-primary js-res-company-clear">Clear</button>
            <button type="button" class="btn btn-sm btn-primary" data-dismiss="modal">Back</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="res-company-table">
            <thead>
              <tr>
                <th style="width:42px;"></th>
                <th>Name</th>
                <th>Email</th>
                <th>GST</th>
                <th>Contracted rates</th>
              </tr>
            </thead>
            <tbody id="resCompanyTableBody">
              <tr>
                <td colspan="5" class="text-center text-muted py-4">Loading companies...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade res-company-modal" id="resAddCompanyModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Company</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="resAddCompanyForm">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Company Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Email</label>
                <input type="email" class="form-control" name="email">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Phone</label>
                <input type="text" class="form-control" name="phone">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-9">
              <div class="form-group">
                <label class="control-label">Address</label>
                <input type="text" class="form-control" name="address">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">GST/ VAT</label>
                <input type="text" class="form-control" name="gst_vat">
              </div>
            </div>
          </div>

          <div class="res-company-section-title">Contracted Rate :</div>
          <div class="row">
            @foreach($options['contracted_rate_fields'] ?? [] as $field)
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label">{{ $field['label'] }}</label>
                  <input type="number" step="0.01" min="0" class="form-control"
                    name="contracted_rates[{{ $field['key'] }}]">
                </div>
              </div>
            @endforeach
          </div>
          <div class="alert alert-danger d-none js-res-company-error mb-0"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary js-res-add-company-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
  <script>
    (function () {
      var companiesUrl = @json(route('hotel.companies.list'));
      var storeUrl = @json(route('hotel.companies.store'));
      var csrf = @json(csrf_token());

      var billToInput = document.querySelector('.js-res-bill-to');
      var billToCompanyId = document.querySelector('.js-res-bill-to-company-id');
      var searchInput = document.getElementById('resCompanySearch');
      var tableBody = document.getElementById('resCompanyTableBody');
      var addForm = document.getElementById('resAddCompanyForm');
      var addError = document.querySelector('.js-res-company-error');
      var searchTimer = null;

      function renderCompanies(companies) {
        if (!tableBody) return;

        if (!companies.length) {
          tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No companies found. Click <strong>Create</strong> to add one.</td></tr>';
          return;
        }

        tableBody.innerHTML = companies.map(function (company) {
          return '<tr data-company-id="' + company.id + '" data-company-name="' + escapeHtml(company.name) + '">' +
            '<td><input type="radio" name="res_company_pick" value="' + company.id + '"></td>' +
            '<td><strong>' + escapeHtml(company.name) + '</strong></td>' +
            '<td>' + escapeHtml(company.email) + '</td>' +
            '<td>' + escapeHtml(company.gst_vat) + '</td>' +
            '<td>' + escapeHtml(company.contracted_rates) + '</td>' +
            '</tr>';
        }).join('');

        tableBody.querySelectorAll('tr[data-company-id]').forEach(function (row) {
          row.addEventListener('click', function () {
            selectCompany(row);
          });
        });
      }

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function selectCompany(row) {
        var id = row.getAttribute('data-company-id');
        var name = row.getAttribute('data-company-name');

        tableBody.querySelectorAll('tr').forEach(function (tr) {
          tr.classList.remove('is-selected');
        });
        row.classList.add('is-selected');

        var radio = row.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;

        if (billToInput) billToInput.value = name || '';
        if (billToCompanyId) billToCompanyId.value = id || '';

        $('#resSelectCompanyModal').modal('hide');
      }

      function loadCompanies() {
        if (!tableBody) return;

        tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading companies...</td></tr>';

        var url = companiesUrl + (searchInput && searchInput.value ? ('?search=' + encodeURIComponent(searchInput.value)) : '');

        fetch(url, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin'
        })
          .then(function (response) { return response.json(); })
          .then(function (data) { renderCompanies(data.companies || []); })
          .catch(function () {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Could not load companies.</td></tr>';
          });
      }

      document.querySelectorAll('.js-res-bill-to-search').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (searchInput) searchInput.value = '';
          loadCompanies();
          $('#resSelectCompanyModal').modal('show');
        });
      });

      document.querySelectorAll('.js-res-company-clear').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (searchInput) searchInput.value = '';
          loadCompanies();
        });
      });

      document.querySelectorAll('.js-res-company-create').forEach(function (btn) {
        btn.addEventListener('click', function () {
          $('#resSelectCompanyModal').modal('hide');
          if (addForm) addForm.reset();
          if (addError) {
            addError.classList.add('d-none');
            addError.textContent = '';
          }
          $('#resAddCompanyModal').modal('show');
        });
      });

      document.querySelectorAll('.js-res-add-company-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
          $('#resAddCompanyModal').modal('hide');
          $('#resSelectCompanyModal').modal('show');
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          window.clearTimeout(searchTimer);
          searchTimer = window.setTimeout(loadCompanies, 300);
        });
      }

      if (addForm) {
        addForm.addEventListener('submit', function (event) {
          event.preventDefault();

          if (addError) {
            addError.classList.add('d-none');
            addError.textContent = '';
          }

          var formData = new FormData(addForm);
          var payload = {
            name: formData.get('name'),
            contact_person: formData.get('contact_person'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            address: formData.get('address'),
            gst_vat: formData.get('gst_vat'),
            contracted_rates: {}
          };

          addForm.querySelectorAll('input[name^="contracted_rates"]').forEach(function (input) {
            if (input.value !== '') {
              var key = input.name.match(/\[([^\]]+)\]/);
              if (key) payload.contracted_rates[key[1]] = input.value;
            }
          });

          fetch(storeUrl, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
          })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok) throw data;
                return data;
              });
            })
            .then(function (data) {
              if (billToInput) billToInput.value = data.company.name || '';
              if (billToCompanyId) billToCompanyId.value = data.company.id || '';
              $('#resAddCompanyModal').modal('hide');
            })
            .catch(function (error) {
              var message = 'Could not create company.';
              if (error && error.message) message = error.message;
              if (error && error.errors) {
                message = Object.values(error.errors).flat().join(' ');
              }
              if (addError) {
                addError.textContent = message;
                addError.classList.remove('d-none');
              }
            });
        });
      }
    })();
  </script>
@endpush
