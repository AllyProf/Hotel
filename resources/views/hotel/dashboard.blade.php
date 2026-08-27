@extends('layouts.app')

@section('title', 'Hotel Dashboard')

@push('styles')
  <style>
    .hotel-module-card {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 4px;
      padding: 18px;
      height: 100%;
      background: #fff;
      transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .hotel-module-card:hover {
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
      border-color: rgba(148, 0, 0, 0.25);
    }
    .hotel-module-card__icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(148, 0, 0, 0.1);
      color: #940000;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      margin-bottom: 12px;
    }
    .hotel-start-step {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      padding: 14px 0;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }
    .hotel-start-step:last-child { border-bottom: 0; padding-bottom: 0; }
    .hotel-start-step__num {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #940000;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      flex: 0 0 32px;
    }
    .integration-tile {
      border-top: 3px solid #940000;
    }
    .hotel-dash-card {
      border-top: 3px solid #940000;
    }
    .integration-ota-chip {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 10px;
      border: 1px solid rgba(0,0,0,.08);
      border-radius: 4px;
      background: #fff;
      font-size: 13px;
      font-weight: 600;
    }
    .integration-ota-chip img {
      width: 24px;
      height: 24px;
      object-fit: contain;
    }
    .integration-accordion .card-header {
      padding: 0;
    }
    .integration-accordion .btn-link {
      color: #333;
      font-weight: 700;
      text-decoration: none;
      padding: 12px 16px;
    }
    .integration-accordion .btn-link:hover {
      color: #940000;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-dashboard"></i> Welcome, {{ auth()->user()->name }}</h1>
      <p>{{ $hotel?->name ?? 'Hotel' }} — choose a module below to get started</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-6 col-lg-3">
      <div class="widget-small primary coloured-icon">
        <i class="icon fa fa-credit-card fa-3x"></i>
        <div class="info">
          <h4>Your Plan</h4>
          <p><b>{{ $hotel?->plan?->name ?? 'None' }}</b></p>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="widget-small info coloured-icon">
        <i class="icon fa fa-th-large fa-3x"></i>
        <div class="info">
          <h4>Active Modules</h4>
          <p><b>{{ count($moduleCards) }}</b></p>
        </div>
      </div>
    </div>
    @if($hotel?->supportsMultiBranch())
      <div class="col-md-6 col-lg-3">
        <div class="widget-small warning coloured-icon">
          <i class="icon fa fa-sitemap fa-3x"></i>
          <div class="info">
            <h4>Branches</h4>
            <p><b>{{ $hotel->branches_count }}</b></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="widget-small danger coloured-icon">
          <i class="icon fa fa-plus-circle fa-3x"></i>
          <div class="info">
            <h4>Branch Limit</h4>
            <p><b>{{ $hotel->maxBranches() === 0 ? 'Unlimited' : $hotel->maxBranches() }}</b></p>
          </div>
        </div>
      </div>
    @endif
  </div>

  @if($hasChannelManager && $cmStatus)
    <div class="row">
      <div class="col-md-12">
        @include('hotel.partials.dashboard-channel-manager')
      </div>
    </div>
  @endif

  @if($hasBookingEngine && $bookingEngine)
    <div class="row">
      <div class="col-md-12">
        @include('hotel.partials.dashboard-booking-engine')
      </div>
    </div>
  @endif

  @if(count($gettingStartedSteps))
    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <h3 class="tile-title"><i class="fa fa-flag-checkered"></i> Where to start</h3>
          <div class="tile-body">
            <p class="text-muted mb-3">Follow these steps to set up your hotel account. You can also open any module from the sidebar or the cards below.</p>
            @foreach($gettingStartedSteps as $step)
              <div class="hotel-start-step">
                <span class="hotel-start-step__num">{{ $step['step'] }}</span>
                <div class="flex-grow-1">
                  <h5 class="mb-1">{{ $step['title'] }}</h5>
                  <p class="text-muted mb-2">{{ $step['description'] }}</p>
                  @if($step['available'])
                    <a class="btn btn-primary btn-sm" href="{{ $step['url'] }}">
                      <i class="{{ $step['icon'] }}"></i> Start now
                    </a>
                  @else
                    <span class="badge badge-secondary">Coming soon</span>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  @endif

  @if($hotel?->supportsMultiBranch() && $hotel->branches_count === 0)
    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <div class="tile-title-w-btn">
            <h3 class="tile-title"><i class="fa fa-sitemap"></i> Set up your first branch</h3>
            <p>
              @if($hotel->canAddBranch())
                <a class="btn btn-primary icon-btn" href="{{ route('hotel.branches.create') }}">
                  <i class="fa fa-plus"></i> Add Branch
                </a>
              @endif
            </p>
          </div>
          <div class="tile-body">
            <p>Your Enterprise plan supports multiple locations. Add your headquarters or first property to begin managing branches.</p>
            <a class="btn btn-primary" href="{{ route('hotel.branches.index') }}">
              <i class="fa fa-sitemap"></i> Go to Branches
            </a>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <h3 class="tile-title"><i class="fa fa-th"></i> Your modules</h3>
        <div class="tile-body">
          @if(count($moduleCards))
            <div class="row">
              @foreach($moduleCards as $module)
                <div class="col-md-6 col-lg-4 mb-4">
                  <div class="hotel-module-card">
                    <div class="hotel-module-card__icon">
                      <i class="{{ $module['icon'] }}"></i>
                    </div>
                    <h5 class="mb-2">{{ $module['label'] }}</h5>
                    <p class="text-muted small mb-3">{{ $module['description'] }}</p>
                    @if($module['available'])
                      <a class="btn btn-primary btn-sm" href="{{ $module['url'] }}">
                        <i class="fa fa-arrow-right"></i> Open module
                      </a>
                    @else
                      <button type="button" class="btn btn-secondary btn-sm" disabled>
                        Coming soon
                      </button>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <p class="text-muted mb-0">No modules are enabled on your subscription plan. Contact the platform owner.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if($hotel?->plan)
    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <h3 class="tile-title">Subscription details</h3>
          <div class="tile-body">
            <p class="mb-1"><strong>Plan:</strong> {{ $hotel->plan->name }} — {{ $hotel->plan->billingLabel() }}</p>
            <p class="mb-1"><strong>Limits:</strong> {{ $hotel->plan->roomsLimitLabel() }}, {{ $hotel->plan->usersLimitLabel() }}, {{ $hotel->plan->branchesLimitLabel() }}</p>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    (function () {
      document.querySelectorAll('.js-copy-text').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var text = this.getAttribute('data-copy') || '';
          if (!text) return;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
              if (typeof swal === 'function') swal('Copied', 'Link copied to clipboard.', 'success');
            });
          } else {
            var input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            if (typeof swal === 'function') swal('Copied', 'Link copied to clipboard.', 'success');
          }
        });
      });
    })();
  </script>
@endpush
