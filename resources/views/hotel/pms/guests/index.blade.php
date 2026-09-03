@extends('layouts.app')

@section('title', $ui['title'] ?? 'Guests')

@push('styles')
  <style>
    :root {
      --gst-brand: #940000;
      --gst-brand-dark: #7a0000;
    }

    .gst-page {
      background: #fff;
    }

    .gst-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 18px 20px;
      border-bottom: 1px solid #e5e7eb;
    }

    .gst-header h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .gst-header-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }

    .btn-gst {
      background: var(--gst-brand) !important;
      border-color: var(--gst-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 3px;
      white-space: nowrap;
    }

    .btn-gst:hover {
      background: var(--gst-brand-dark) !important;
      border-color: var(--gst-brand-dark) !important;
      color: #fff !important;
    }

    .gst-search {
      display: flex;
      align-items: stretch;
      min-width: 220px;
    }

    .gst-search input {
      height: 36px;
      border: 1px solid #ccc;
      border-right: none;
      border-radius: 3px 0 0 3px;
      padding: 0 12px;
      font-size: 13px;
      min-width: 180px;
    }

    .gst-search input:focus {
      outline: none;
      border-color: var(--gst-brand);
      box-shadow: none;
    }

    .gst-search button {
      width: 42px;
      border: none;
      background: var(--gst-brand);
      color: #fff;
      border-radius: 0 3px 3px 0;
      cursor: pointer;
    }

    .gst-search button:hover {
      background: var(--gst-brand-dark);
    }

    .gst-table-wrap {
      overflow-x: auto;
      padding: 0 20px 20px;
    }

    .gst-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 0;
    }

    .gst-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      padding: 12px 14px;
      border: 1px solid #2d3238;
      white-space: nowrap;
      vertical-align: middle;
    }

    .gst-table tbody td {
      border: 1px solid #dee2e6;
      padding: 12px 14px;
      font-size: 13px;
      color: #333;
      background: #fff;
      vertical-align: middle;
    }

    .gst-table tbody tr:hover td {
      background: #fafafa;
    }

    .gst-photo {
      width: 56px;
      height: 40px;
      border: 1px solid #ddd;
      border-radius: 2px;
      background: #f8f9fa;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .gst-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .gst-photo i {
      color: #adb5bd;
      font-size: 18px;
    }

    .gst-name {
      font-weight: 600;
      color: #212529;
    }

    .gst-muted {
      color: #adb5bd;
    }

    .gst-action-btn {
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

    .gst-action-btn:hover {
      border-color: var(--gst-brand);
      color: var(--gst-brand);
      background: #fef2f2;
    }

    .gst-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      padding: 16px 20px;
      border-top: 1px solid #e5e7eb;
    }

    .gst-page-btn {
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

    .gst-page-btn:hover {
      background: #f8f9fa;
      text-decoration: none;
      color: #495057;
    }

    .gst-page-btn.is-active {
      background: var(--gst-brand);
      border-color: var(--gst-brand);
      color: #fff;
    }

    .gst-page-btn.is-disabled {
      opacity: 0.45;
      pointer-events: none;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-id-card-o"></i> {{ $ui['title'] ?? 'Guests' }}</h1>
      <p>Guest profiles aggregated from reservations</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Guests' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile gst-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
          <div class="alert alert-warning mx-3 mt-3 mb-0">{{ session('warning') }}</div>
        @endif

        <div class="gst-header">
          <h3>{{ $ui['title'] ?? 'Guests' }}</h3>

          <div class="gst-header-actions">
            <form method="POST" action="{{ route('hotel.guests.remove-duplicates') }}" class="d-inline mb-0 js-gst-dedupe-form">
              @csrf
              <input type="hidden" name="search" value="{{ $filters['search'] }}">
              <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
              <button type="submit" class="btn btn-sm btn-gst">Remove Duplicates</button>
            </form>

            <button type="button" class="btn btn-sm btn-gst" data-toggle="modal" data-target="#gstUploadModal">
              <i class="fa fa-upload"></i> Upload
            </button>

            <a class="btn btn-sm btn-gst" href="{{ route('hotel.guests.export', request()->query()) }}">
              <i class="fa fa-download"></i> Download
            </a>

            <form method="GET" action="{{ route('hotel.guests.index') }}" class="gst-search mb-0">
              <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
              <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search...">
              <button type="submit" title="Search"><i class="fa fa-search"></i></button>
            </form>
          </div>
        </div>

        <div class="gst-table-wrap">
          <table class="gst-table">
            <thead>
              <tr>
                @foreach($ui['columns'] ?? [] as $column)
                  <th>{{ $column['label'] }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @forelse($guests as $guest)
                <tr>
                  <td>
                    <span class="gst-photo">
                      @if($guest->photoUrl())
                        <img src="{{ $guest->photoUrl() }}" alt="Photo ID">
                      @else
                        <i class="fa fa-id-card-o"></i>
                      @endif
                    </span>
                  </td>
                  <td><span class="gst-name">{{ $guest->name }}</span></td>
                  <td>{{ $guest->phone ?: '—' }}</td>
                  <td>{{ $guest->email ?: '—' }}</td>
                  <td>{{ $guest->totalValueLabel() }}</td>
                  <td>{{ $guest->previous_stays }}</td>
                  <td>
                    <button type="button" class="gst-action-btn js-gst-view" title="View guest"
                      data-name="{{ $guest->name }}"
                      data-phone="{{ $guest->phone }}"
                      data-email="{{ $guest->email }}"
                      data-value="{{ $guest->totalValueLabel() }}"
                      data-stays="{{ $guest->previous_stays }}">
                      <i class="fa fa-ellipsis-v"></i>
                    </button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="{{ count($ui['columns'] ?? []) }}" class="text-center text-muted py-5">
                    No guests found.
                    @if($filters['search'] !== '')
                      Try a different search term.
                    @else
                      Create a reservation to add guest records.
                    @endif
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($guests->hasPages() || $guests->total() > 0)
          <div class="gst-footer">
            @php
              $query = request()->query();
              $prevQuery = array_merge($query, ['page' => max(1, $guests->currentPage() - 1)]);
              $nextQuery = array_merge($query, ['page' => min($guests->lastPage(), $guests->currentPage() + 1)]);
            @endphp

            <a href="{{ $guests->onFirstPage() ? '#' : route('hotel.guests.index', $prevQuery) }}"
              class="gst-page-btn {{ $guests->onFirstPage() ? 'is-disabled' : '' }}">Previous</a>

            <span class="gst-page-btn is-active">{{ $guests->currentPage() }}</span>

            <a href="{{ $guests->onLastPage() ? '#' : route('hotel.guests.index', $nextQuery) }}"
              class="gst-page-btn {{ $guests->onLastPage() ? 'is-disabled' : '' }}">Next</a>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="modal fade" id="gstUploadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="{{ route('hotel.guests.upload') }}" enctype="multipart/form-data" class="js-gst-upload-form">
          @csrf
          <input type="hidden" name="search" value="{{ $filters['search'] }}">
          <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
          <div class="modal-header" style="background:#940000;color:#fff;">
            <h5 class="modal-title">Upload Guests (Excel)</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3" style="font-size:13px;">
              Upload an Excel file (.xlsx, .xls) or CSV with columns:
              <strong>Name</strong>, Phone, Email, Total Value, Previous Stays, Currency.
            </p>
            <div class="form-group mb-2">
              <label class="control-label">Excel file</label>
              <input type="file" class="form-control-file" name="file" accept=".xlsx,.xls,.csv" required>
            </div>
            <a href="{{ route('hotel.guests.template') }}" class="small">Download sample template</a>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary js-gst-upload-submit" style="background:#940000;border-color:#940000;">
              <i class="fa fa-upload"></i> Upload
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="gstGuestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background:#940000;color:#fff;">
          <h5 class="modal-title js-gst-modal-title">Guest</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <table class="table table-sm mb-0">
            <tbody>
              <tr><th style="width:35%;">Name</th><td class="js-gst-modal-name"></td></tr>
              <tr><th>Phone</th><td class="js-gst-modal-phone"></td></tr>
              <tr><th>Email</th><td class="js-gst-modal-email"></td></tr>
              <tr><th>Total Value</th><td class="js-gst-modal-value"></td></tr>
              <tr><th>Previous Stays</th><td class="js-gst-modal-stays"></td></tr>
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
      document.querySelectorAll('.js-gst-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          document.querySelector('.js-gst-modal-title').textContent = btn.getAttribute('data-name') || 'Guest';
          document.querySelector('.js-gst-modal-name').textContent = btn.getAttribute('data-name') || '—';
          document.querySelector('.js-gst-modal-phone').textContent = btn.getAttribute('data-phone') || '—';
          document.querySelector('.js-gst-modal-email').textContent = btn.getAttribute('data-email') || '—';
          document.querySelector('.js-gst-modal-value').textContent = btn.getAttribute('data-value') || '—';
          document.querySelector('.js-gst-modal-stays').textContent = btn.getAttribute('data-stays') || '0';
          $('#gstGuestModal').modal('show');
        });
      });

      var dedupeForm = document.querySelector('.js-gst-dedupe-form');
      if (dedupeForm) {
        dedupeForm.addEventListener('submit', function () {
          var btn = dedupeForm.querySelector('button[type="submit"]');
          if (btn) {
            btn.disabled = true;
            btn.textContent = 'Processing...';
          }
        });
      }

      var uploadForm = document.querySelector('.js-gst-upload-form');
      if (uploadForm) {
        uploadForm.addEventListener('submit', function () {
          var btn = uploadForm.querySelector('.js-gst-upload-submit');
          if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';
          }
        });
      }
    })();
  </script>
@endpush
