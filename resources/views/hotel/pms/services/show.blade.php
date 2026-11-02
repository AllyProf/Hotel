@extends('layouts.app')

@section('title', $service->name)

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sv-show-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 18px 20px;
      border-bottom: 3px solid var(--sv-brand);
    }

    .sv-show-head__title {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .sv-show-head__meta {
      font-size: 13px;
      color: #666;
      margin-top: 4px;
    }

    .sv-show-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .sv-card-actions-stack {
      display: flex;
      flex-direction: column;
      gap: 6px;
      width: 100%;
    }

    .btn-sv-edit {
      background: #fff !important;
      border: 1px solid #6c757d !important;
      color: #495057 !important;
      font-size: 12px;
      font-weight: 600;
      padding: 5px 14px;
      border-radius: 3px;
      width: 100%;
      cursor: pointer;
    }

    .btn-sv-edit:hover {
      background: #6c757d !important;
      color: #fff !important;
    }

    .sv-badge-inactive {
      background: #fee2e2;
      color: #991b1b;
    }

    .sv-add-form--compact .form-control {
      margin-bottom: 6px;
    }

    .sv-template-card__abbr {
      font-size: 12px;
      color: #888;
      margin-top: -4px;
      margin-bottom: 8px;
    }

    .sv-settings-toggles {
      display: flex;
      flex-wrap: wrap;
      gap: 12px 24px;
    }

    .sv-price-badge {
      display: inline-block;
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 4px;
      padding: 2px 10px;
      font-size: 13px;
      color: #333;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-th-list"></i> {{ $service->name }}</h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.services.index') }}">Services</a></li>
      <li class="breadcrumb-item">{{ $service->name }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile sv-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        <div class="sv-show-head">
          <div class="sv-show-head__meta">
            @if($service->showsSettings())
              @if($service->isSwimming())
                <span class="sv-price-badge">Rates in pool plan table (TZS / USD)</span>
              @elseif($service->isConference())
                <span class="sv-price-badge">Rates in conference packages table (TZS / USD)</span>
              @elseif($service->isLaundry())
                <span class="sv-price-badge">Default {{ $service->displayCurrency($hotel) }}</span>
                @if($service->hasTax())
                  <span class="sv-price-badge">{{ $service->tax_category }}{{ $service->tax_inclusive ? ' (incl.)' : '' }}</span>
                @else
                  <span class="sv-price-badge">No Tax</span>
                @endif
              @else
                <span class="sv-price-badge">
                  {{ $service->displayCurrency($hotel) }} {{ number_format((float) $service->amount, 2) }}
                  · {{ $service->pricingUnitLabel() }}
                  @if($service->hasTax())
                    · {{ $service->tax_category }}{{ $service->tax_inclusive ? ' (incl.)' : '' }}
                  @else
                    · No Tax
                  @endif
                </span>
              @endif
              @if(! $service->is_active)
                <span class="sv-badge-custom sv-badge-inactive">Inactive</span>
              @endif
              @if($service->isParking())
                @php $parking = $service->parkingSettings(); @endphp
                @if($parking['complimentary_for_inhouse'] ?? false)
                  <span class="sv-badge-custom">Guest parking free</span>
                @endif
                @if(($parking['grace_period_minutes'] ?? 0) > 0)
                  <span class="sv-price-badge">{{ $parking['grace_period_minutes'] }} min grace</span>
                @endif
              @endif
              @if($service->isSwimming())
                @php $swim = $service->swimmingSettings(); @endphp
                <span class="sv-price-badge">{{ $swim['open_time'] ?? '07:00' }} – {{ $swim['close_time'] ?? '19:00' }}</span>
                @if($swim['complimentary_for_inhouse'] ?? false)
                  <span class="sv-badge-custom">In-house free</span>
                @endif
              @endif
              @if($service->isConference())
                @php $conf = $service->conferenceSettings(); @endphp
                <span class="sv-price-badge">{{ $conf['business_hours_start'] ?? '08:00' }} – {{ $conf['business_hours_end'] ?? '22:00' }}</span>
              @endif
              @if($service->isLaundry())
                @php $laundry = $service->laundrySettings(); @endphp
                <span class="sv-price-badge">{{ $laundry['turnaround_hours'] ?? 24 }}h turnaround</span>
                @if(($laundry['express_surcharge_percent'] ?? 0) > 0)
                  <span class="sv-price-badge">Express +{{ $laundry['express_surcharge_percent'] }}%</span>
                @endif
                @if(($laundry['complimentary_pieces'] ?? 0) > 0)
                  <span class="sv-badge-custom">{{ $laundry['complimentary_pieces'] }} free pcs</span>
                @endif
              @endif
            @endif
            @if($service->showsCatalog())
              @if($service->showsSettings()) · @endif
              {{ $service->service_categories_count }} categor{{ $service->service_categories_count === 1 ? 'y' : 'ies' }}
              @if($service->showsPackages())
                · {{ $service->service_packages_count }} package{{ $service->service_packages_count === 1 ? '' : 's' }}
              @endif
            @endif
            @if($service->isCustom())
              <span class="sv-badge-custom">Custom</span>
            @endif
          </div>
          <div class="sv-show-actions">
            <a href="{{ route('hotel.services.index') }}" class="btn btn-light border">
              <i class="fa fa-arrow-left"></i> Back
            </a>
            @if($service->isLaundry())
              <a href="{{ route('hotel.laundry.index') }}" class="btn btn-sv">
                <i class="fa fa-shirtsinbulk"></i> Laundry Desk
              </a>
            @endif
            @if($service->isSwimming())
              <a href="{{ route('hotel.swimming.index') }}" class="btn btn-sv">
                <i class="fa fa-tint"></i> Pool Desk
              </a>
            @endif
            @if($service->isConference())
              <a href="{{ route('hotel.conference.index') }}" class="btn btn-sv">
                <i class="fa fa-users"></i> Events Desk
              </a>
            @endif
            @if($service->showsCatalog())
              @if($hasTemplates)
                <form method="POST" action="{{ route('hotel.services.categories.import-all', $service) }}"
                  class="d-inline js-swal-add">
                  @csrf
                  <button type="submit" class="btn btn-sv"
                    data-title="Import categories?"
                    data-text="Import all category templates for {{ $service->name }}?"
                    data-confirm="Yes, import all"
                    data-cancel="Cancel">
                    Import Categories
                  </button>
                </form>
              @endif
              @if($service->showsPackages() && $hasPackageTemplates)
                <form method="POST" action="{{ route('hotel.services.packages.import-all', $service) }}"
                  class="d-inline js-swal-add">
                  @csrf
                  <button type="submit" class="btn btn-sv"
                    data-title="Import packages?"
                    data-text="Import all package templates for {{ $service->name }}?"
                    data-confirm="Yes, import all"
                    data-cancel="Cancel">
                    Import Packages
                  </button>
                </form>
              @endif
            @endif
          </div>
        </div>

        @if($service->showsSettings())
          @include('hotel.pms.services.partials._settings-form')
        @endif

        @if($service->isLaundry())
          @include('hotel.pms.services.partials._laundry-prices')
        @endif

        @if($service->isSwimming())
          @include('hotel.pms.services.partials._swimming-rates')
        @endif

        @if($service->isConference())
          @include('hotel.pms.services.partials._conference-rates', ['conferenceRateRows' => $conferenceRateRows ?? collect()])
        @endif

        @if($service->showsCatalog())
        <div class="sv-section">
          <h4 class="sv-section-title"><i class="fa fa-th-list"></i> Categories</h4>
          @if($cards->isEmpty() && ! $hasTemplates)
            <p class="text-muted">No categories yet. Add custom categories below.</p>
          @endif

          <div class="sv-template-grid">
            @foreach($cards as $card)
              <div class="sv-template-card {{ $card['is_added'] ? 'is-added' : '' }} {{ ! $card['is_active'] ? 'is-inactive' : '' }}">
                <div class="sv-template-card__icon">
                  <i class="{{ $card['icon'] }}"></i>
                </div>
                <p class="sv-template-card__name">{{ $card['name'] }}</p>
                @if($card['is_custom'])
                  <span class="sv-badge-custom">Custom</span>
                @endif
                @if($card['is_added'] && ! $card['is_active'])
                  <span class="sv-badge-custom sv-badge-inactive">Inactive</span>
                @endif
                <div class="sv-template-card__actions">
                  @if($card['is_added'] && $card['category_id'])
                    <div class="sv-card-actions-stack">
                      <button type="button" class="btn btn-sv-edit js-sv-cat-edit"
                        data-category='@json($card)'
                        data-toggle="modal" data-target="#svCategoryModal">
                        Edit
                      </button>
                      <form method="POST"
                        action="{{ route('hotel.services.categories.destroy', [$service, $card['category_id']]) }}"
                        class="js-swal-delete">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-sv-remove"
                          data-title="Remove category?"
                          data-text="Remove {{ $card['name'] }} from {{ $service->name }}?"
                          data-confirm="Yes, remove it"
                          data-cancel="Cancel">
                          Remove
                        </button>
                      </form>
                    </div>
                  @elseif($card['type'] === 'template')
                    <form method="POST" action="{{ route('hotel.services.categories.from-template', $service) }}"
                      class="js-swal-add">
                      @csrf
                      <input type="hidden" name="template_key" value="{{ $card['template_key'] }}">
                      <button type="submit" class="btn btn-sv-outline"
                        data-title="Add category?"
                        data-text="Add {{ $card['name'] }} to {{ $service->name }}?"
                        data-confirm="Yes, add it"
                        data-cancel="Cancel">
                        Add
                      </button>
                    </form>
                  @endif
                </div>
              </div>
            @endforeach

            <div class="sv-template-card sv-template-card--add">
              <div class="sv-template-card__icon">
                <i class="fa fa-plus"></i>
              </div>
              <form method="POST" action="{{ route('hotel.services.categories.store', $service) }}"
                class="sv-add-form js-swal-add">
                @csrf
                <input type="text" class="form-control" name="name" placeholder="Custom category" required maxlength="120"
                  value="{{ old('name') }}">
                <button type="submit" class="btn btn-sv"
                  data-title="Add custom category?"
                  data-text="Add :name to {{ $service->name }}?"
                  data-confirm="Yes, add it"
                  data-cancel="Cancel">
                  Add
                </button>
              </form>
              @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>

        @if($service->showsPackages())
        <div class="sv-section border-top" id="packages">
          <h4 class="sv-section-title"><i class="fa fa-cube"></i> Packages</h4>
          <p class="text-muted small mb-3">Units used to sell or charge items for {{ $service->name }} — kg, box, bottle, piece, etc.</p>

          @if($packageCards->isEmpty() && ! $hasPackageTemplates)
            <p class="text-muted">No packages yet. Add custom packages below.</p>
          @endif

          <div class="sv-template-grid">
            @foreach($packageCards as $card)
              <div class="sv-template-card {{ $card['is_added'] ? 'is-added' : '' }} {{ ! $card['is_active'] ? 'is-inactive' : '' }}">
                <div class="sv-template-card__icon">
                  <i class="{{ $card['icon'] }}"></i>
                </div>
                <p class="sv-template-card__name">{{ $card['name'] }}</p>
                @if(!empty($card['abbreviation']))
                  <div class="sv-template-card__abbr">{{ $card['abbreviation'] }}</div>
                @endif
                @if($card['is_custom'])
                  <span class="sv-badge-custom">Custom</span>
                @endif
                @if($card['is_added'] && ! $card['is_active'])
                  <span class="sv-badge-custom sv-badge-inactive">Inactive</span>
                @endif
                <div class="sv-template-card__actions">
                  @if($card['is_added'] && $card['package_id'])
                    <div class="sv-card-actions-stack">
                      <button type="button" class="btn btn-sv-edit js-sv-pkg-edit"
                        data-package='@json($card)'
                        data-toggle="modal" data-target="#svPackageModal">
                        Edit
                      </button>
                      <form method="POST"
                        action="{{ route('hotel.services.packages.destroy', [$service, $card['package_id']]) }}"
                        class="js-swal-delete">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-sv-remove"
                          data-title="Remove package?"
                          data-text="Remove {{ $card['name'] }} from {{ $service->name }}?"
                          data-confirm="Yes, remove it"
                          data-cancel="Cancel">
                          Remove
                        </button>
                      </form>
                    </div>
                  @elseif($card['type'] === 'template')
                    <form method="POST" action="{{ route('hotel.services.packages.from-template', $service) }}"
                      class="js-swal-add">
                      @csrf
                      <input type="hidden" name="template_key" value="{{ $card['template_key'] }}">
                      <button type="submit" class="btn btn-sv-outline"
                        data-title="Add package?"
                        data-text="Add {{ $card['name'] }} ({{ $card['abbreviation'] }}) to {{ $service->name }}?"
                        data-confirm="Yes, add it"
                        data-cancel="Cancel">
                        Add
                      </button>
                    </form>
                  @endif
                </div>
              </div>
            @endforeach

            <div class="sv-template-card sv-template-card--add">
              <div class="sv-template-card__icon">
                <i class="fa fa-plus"></i>
              </div>
              <form method="POST" action="{{ route('hotel.services.packages.store', $service) }}"
                class="sv-add-form sv-add-form--compact js-swal-add">
                @csrf
                <input type="text" class="form-control" name="package_name" placeholder="Package name" required
                  maxlength="120" value="{{ old('package_name') }}">
                <input type="text" class="form-control" name="package_abbreviation" placeholder="Short code (e.g. kg)"
                  maxlength="20" value="{{ old('package_abbreviation') }}">
                <button type="submit" class="btn btn-sv js-swal-pkg-add-btn"
                  data-title="Add custom package?"
                  data-text="Add :name to {{ $service->name }}?"
                  data-confirm="Yes, add it"
                  data-cancel="Cancel">
                  Add
                </button>
              </form>
              @error('package_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>
        @endif
        @endif
      </div>
    </div>
  </div>

  @if($service->showsCatalog())
    @include('hotel.pms.services.partials._category-modal', ['service' => $service])
    @if($service->showsPackages())
      @include('hotel.pms.services.partials._package-modal')
    @endif
  @endif
@endsection

@if($service->showsCatalog())
@push('scripts')
  <script>
    (function () {
      var catModal = document.getElementById('svCategoryModal');
      var catForm = document.getElementById('svCategoryForm');
      if (catModal && catForm) {
        document.querySelectorAll('.js-sv-cat-edit').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var category = JSON.parse(btn.getAttribute('data-category') || '{}');
            catForm.action = @json(url('/hotel/services/'.$service->id.'/categories')) + '/' + category.category_id;
            catForm.querySelector('[name="name"]').value = category.name || '';
            catForm.querySelector('[name="is_active"]').checked = category.is_active !== false;
          });
        });

        catModal.addEventListener('hidden.bs.modal', function () {
          catForm.action = '#';
          catForm.reset();
        });
      }

      @if($service->showsPackages())
      var pkgModal = document.getElementById('svPackageModal');
      var pkgForm = document.getElementById('svPackageForm');
      if (pkgModal && pkgForm) {
        document.querySelectorAll('.js-sv-pkg-edit').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var pkg = JSON.parse(btn.getAttribute('data-package') || '{}');
            pkgForm.action = @json(url('/hotel/services/'.$service->id.'/packages')) + '/' + pkg.package_id;
            pkgForm.querySelector('[name="name"]').value = pkg.name || '';
            pkgForm.querySelector('[name="abbreviation"]').value = pkg.abbreviation || '';
            pkgForm.querySelector('[name="is_active"]').checked = pkg.is_active !== false;
          });
        });

        pkgModal.addEventListener('hidden.bs.modal', function () {
          pkgForm.action = '#';
          pkgForm.reset();
        });
      }
      @endif
    })();
  </script>
@endpush
@endif
