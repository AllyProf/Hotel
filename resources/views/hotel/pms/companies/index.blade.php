@extends('layouts.app')

@section('title', $ui['title'] ?? 'Companies')

@push('styles')
  <style>
    :root {
      --co-brand: #940000;
      --co-brand-dark: #7a0000;
    }

    .co-page { background: #fff; }

    .co-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 18px 20px;
      border-bottom: 1px solid #e5e7eb;
    }

    .co-header h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .co-header-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }

    .btn-co {
      background: var(--co-brand) !important;
      border-color: var(--co-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 3px;
      white-space: nowrap;
      text-decoration: none;
    }

    .btn-co:hover {
      background: var(--co-brand-dark) !important;
      border-color: var(--co-brand-dark) !important;
      color: #fff !important;
      text-decoration: none;
    }

    .btn-co.is-muted {
      opacity: 0.65;
      cursor: not-allowed;
    }

    .co-search {
      display: flex;
      align-items: stretch;
      min-width: 220px;
    }

    .co-search input {
      height: 36px;
      border: 1px solid #ccc;
      border-right: none;
      border-radius: 3px 0 0 3px;
      padding: 0 12px;
      font-size: 13px;
      min-width: 180px;
    }

    .co-search input:focus {
      outline: none;
      border-color: var(--co-brand);
    }

    .co-search button {
      width: 42px;
      border: none;
      background: var(--co-brand);
      color: #fff;
      border-radius: 0 3px 3px 0;
      cursor: pointer;
    }

    .co-table-wrap {
      overflow-x: auto;
      padding: 0 20px 20px;
    }

    .co-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 980px;
    }

    .co-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      padding: 12px 14px;
      border: 1px solid #2d3238;
      white-space: nowrap;
      vertical-align: middle;
    }

    .co-table tbody td {
      border: 1px solid #dee2e6;
      padding: 12px 14px;
      font-size: 13px;
      color: #333;
      background: #fff;
      vertical-align: middle;
    }

    .co-table tbody tr:hover td { background: #fafafa; }

    .co-name { font-weight: 600; color: #212529; }

    .co-action-btn {
      width: 32px;
      height: 32px;
      border: 1px solid #dee2e6;
      background: #fff;
      color: #666;
      border-radius: 3px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      padding: 0;
    }

    .co-action-btn:hover {
      border-color: var(--co-brand);
      color: var(--co-brand);
      background: #fef2f2;
    }

    .co-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      padding: 16px 20px;
      border-top: 1px solid #e5e7eb;
    }

    .co-page-btn {
      min-width: 36px;
      height: 34px;
      padding: 0 12px;
      border: 1px solid #dee2e6;
      background: #fff;
      color: #495057;
      font-size: 13px;
      border-radius: 3px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .co-page-btn.is-active {
      background: var(--co-brand);
      border-color: var(--co-brand);
      color: #fff;
    }

    .co-page-btn.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }

    .co-add-modal__header {
      background: #940000;
      color: #fff;
      border-bottom: none;
      padding-bottom: 0;
    }

    .co-add-modal__divider {
      height: 3px;
      background: var(--co-brand);
      margin: 0 0 20px;
    }

    .co-add-modal__section {
      font-size: 14px;
      font-weight: 700;
      color: #333;
      margin: 4px 0 14px;
    }
  </style>
@endpush

@section('content')
  @inject('companyData', 'App\Services\CompanyDataService')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-building-o"></i> {{ $ui['title'] ?? 'Companies' }}</h1>
      <p>Corporate accounts, billing, and contracted rates</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Companies' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile co-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
          <div class="alert alert-warning mx-3 mt-3 mb-0">{{ session('warning') }}</div>
        @endif

        <div class="co-header">
          <h3>{{ $ui['title'] ?? 'Companies' }}</h3>

          <div class="co-header-actions">
            <button type="button" class="btn btn-sm btn-co js-co-open-add" data-toggle="modal" data-target="#coAddCompanyModal">
              Add Company
            </button>
            <button type="button" class="btn btn-sm btn-co is-muted" disabled title="Coming soon">City Ledger</button>
            <button type="button" class="btn btn-sm btn-co is-muted" disabled title="Coming soon">Ageing Report</button>
            <button type="button" class="btn btn-sm btn-co" data-toggle="modal" data-target="#coUploadModal">
              <i class="fa fa-upload"></i> Upload
            </button>

            <form method="GET" action="{{ route('hotel.companies.index') }}" class="co-search mb-0">
              <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
              <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search...">
              <button type="submit" title="Search"><i class="fa fa-search"></i></button>
            </form>
          </div>
        </div>

        <div class="co-table-wrap">
          <table class="co-table">
            <thead>
              <tr>
                @foreach($ui['columns'] ?? [] as $column)
                  <th>{{ $column['label'] }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @forelse($companies as $company)
                @php
                  $stats = $billingStats[$company->id] ?? [
                    'billed' => 0,
                    'outstanding' => 0,
                    'invoices' => 0,
                    'payments' => 0,
                    'currency' => strtoupper($hotel->currency ?: 'USD'),
                  ];
                  $currency = $stats['currency'] ?? strtoupper($hotel->currency ?: 'USD');
                @endphp
                <tr>
                  <td><span class="co-name">{{ $company->name }}</span></td>
                  <td>{{ $company->gst_vat ?: '—' }}</td>
                  <td>{{ $companyData->moneyLabel($stats['billed'], $currency) }}</td>
                  <td>{{ $companyData->moneyLabel($stats['outstanding'], $currency) }}</td>
                  <td>{{ $stats['invoices'] > 0 ? $stats['invoices'] : '—' }}</td>
                  <td>{{ $stats['payments'] > 0 ? $companyData->moneyLabel($stats['payments'], $currency) : '—' }}</td>
                  <td>
                    <button type="button" class="co-action-btn js-co-view" title="View company"
                      data-name="{{ $company->name }}"
                      data-contact="{{ $company->contact_person }}"
                      data-email="{{ $company->email }}"
                      data-phone="{{ $company->phone }}"
                      data-address="{{ $company->address }}"
                      data-gst="{{ $company->gst_vat }}"
                      data-rates="{{ $company->contractedRatesSummary() }}"
                      data-billed="{{ $companyData->moneyLabel($stats['billed'], $currency) }}"
                      data-outstanding="{{ $companyData->moneyLabel($stats['outstanding'], $currency) }}">
                      <i class="fa fa-ellipsis-v"></i>
                    </button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="{{ count($ui['columns'] ?? []) }}" class="text-center text-muted py-5">
                    No companies found.
                    <button type="button" class="btn btn-link p-0 align-baseline js-co-open-add" data-toggle="modal" data-target="#coAddCompanyModal">Add Company</button> to get started.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($companies->hasPages() || $companies->total() > 0)
          <div class="co-footer">
            @php
              $query = request()->query();
              $prevQuery = array_merge($query, ['page' => max(1, $companies->currentPage() - 1)]);
              $nextQuery = array_merge($query, ['page' => min($companies->lastPage(), $companies->currentPage() + 1)]);
            @endphp
            <a href="{{ $companies->onFirstPage() ? '#' : route('hotel.companies.index', $prevQuery) }}"
              class="co-page-btn {{ $companies->onFirstPage() ? 'is-disabled' : '' }}">Previous</a>
            <span class="co-page-btn is-active">{{ $companies->currentPage() }}</span>
            <a href="{{ $companies->onLastPage() ? '#' : route('hotel.companies.index', $nextQuery) }}"
              class="co-page-btn {{ $companies->onLastPage() ? 'is-disabled' : '' }}">Next</a>
          </div>
        @endif
      </div>
    </div>
  </div>

  @include('hotel.pms.companies.partials._add-company-modal')

  <div class="modal fade" id="coUploadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="{{ route('hotel.companies.upload') }}" enctype="multipart/form-data" class="js-co-upload-form">
          @csrf
          <input type="hidden" name="search" value="{{ $filters['search'] }}">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
          <div class="modal-header" style="background:#940000;color:#fff;">
            <h5 class="modal-title">Upload Companies (Excel)</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3" style="font-size:13px;">
              Upload Excel (.xlsx, .xls) or CSV with columns:
              <strong>Name</strong>, Contact Person, Email, Phone, Address, GST/ VAT.
            </p>
            <div class="form-group mb-2">
              <label class="control-label">Excel file</label>
              <input type="file" class="form-control-file" name="file" accept=".xlsx,.xls,.csv" required>
            </div>
            <a href="{{ route('hotel.companies.template') }}" class="small">Download sample template</a>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary js-co-upload-submit" style="background:#940000;border-color:#940000;">
              <i class="fa fa-upload"></i> Upload
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="coDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background:#940000;color:#fff;">
          <h5 class="modal-title js-co-modal-title">Company</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <table class="table table-sm mb-0">
            <tbody>
              <tr><th style="width:34%;">Contact Person</th><td class="js-co-modal-contact"></td></tr>
              <tr><th>Email</th><td class="js-co-modal-email"></td></tr>
              <tr><th>Phone</th><td class="js-co-modal-phone"></td></tr>
              <tr><th>Address</th><td class="js-co-modal-address"></td></tr>
              <tr><th>GST/ VAT</th><td class="js-co-modal-gst"></td></tr>
              <tr><th>Contracted Rates</th><td class="js-co-modal-rates"></td></tr>
              <tr><th>Total Billed</th><td class="js-co-modal-billed"></td></tr>
              <tr><th>Total Outstanding</th><td class="js-co-modal-outstanding"></td></tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      document.querySelectorAll('.js-co-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          document.querySelector('.js-co-modal-title').textContent = btn.getAttribute('data-name') || 'Company';
          document.querySelector('.js-co-modal-contact').textContent = btn.getAttribute('data-contact') || '—';
          document.querySelector('.js-co-modal-email').textContent = btn.getAttribute('data-email') || '—';
          document.querySelector('.js-co-modal-phone').textContent = btn.getAttribute('data-phone') || '—';
          document.querySelector('.js-co-modal-address').textContent = btn.getAttribute('data-address') || '—';
          document.querySelector('.js-co-modal-gst').textContent = btn.getAttribute('data-gst') || '—';
          document.querySelector('.js-co-modal-rates').textContent = btn.getAttribute('data-rates') || '—';
          document.querySelector('.js-co-modal-billed').textContent = btn.getAttribute('data-billed') || '—';
          document.querySelector('.js-co-modal-outstanding').textContent = btn.getAttribute('data-outstanding') || '—';
          $('#coDetailModal').modal('show');
        });
      });

      var uploadForm = document.querySelector('.js-co-upload-form');
      if (uploadForm) {
        uploadForm.addEventListener('submit', function () {
          var btn = uploadForm.querySelector('.js-co-upload-submit');
          if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';
          }
        });
      }

      var addForm = document.getElementById('coAddCompanyForm');
      var addError = document.querySelector('.js-co-add-error');
      var storeUrl = @json(route('hotel.companies.store'));
      var csrf = @json(csrf_token());

      document.querySelectorAll('.js-co-open-add').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (addForm) addForm.reset();
          if (addError) {
            addError.classList.add('d-none');
            addError.textContent = '';
          }
        });
      });

      if (addForm) {
        addForm.addEventListener('submit', function (event) {
          event.preventDefault();

          if (addError) {
            addError.classList.add('d-none');
            addError.textContent = '';
          }

          var submitBtn = addForm.querySelector('.js-co-add-submit');
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
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
            .then(function () {
              $('#coAddCompanyModal').modal('hide');
              window.location.reload();
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
            })
            .finally(function () {
              if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit';
              }
            });
        });
      }
    })();
  </script>
@endpush
