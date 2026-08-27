@extends('layouts.app')

@section('title', 'Integrations')

@push('styles')
  <style>
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
    .integration-tile { border-top: 3px solid #940000; }
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
    .integration-ota-chip img { width: 24px; height: 24px; object-fit: contain; }
    .integration-accordion .card-header { padding: 0; }
    .integration-accordion .btn-link {
      color: #333;
      font-weight: 700;
      text-decoration: none;
      padding: 12px 16px;
    }
    .integration-accordion .btn-link:hover { color: #940000; }
    .cm-test-pass { color: #28a745; font-weight: 700; }
    .cm-test-fail { color: #dc3545; font-weight: 700; }
    .cm-test-response { font-size: 12px; color: #666; max-width: 420px; word-break: break-word; }
  </style>
@endpush

@section('content')
  @php
    $cmDoc = config('channel_manager_integration');
    $otas = config('otas', []);
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-plug"></i> Software Integrations</h1>
      <p>Platform-wide Channel Manager API setup for {{ config('app.name') }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item">Integrations</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile integration-tile" id="cm-integration">
        <div class="tile-title-w-btn">
          <h3 class="tile-title"><i class="fa fa-exchange"></i> Channel Manager API</h3>
          @if($channelManager['enabled'] ?? false)
            <span class="badge badge-success">Enabled</span>
          @else
            <span class="badge badge-secondary">Not configured</span>
          @endif
        </div>
        <div class="tile-body">
          <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            This configures how <strong>{{ config('app.name') }}</strong> connects to the Channel Manager on behalf of all hotels.
            Hotel owners never see these credentials — they only manage rooms, mapping, and availability.
          </div>

          <p class="text-muted">{{ $cmDoc['getting_started']['intro'] }}</p>

          <form method="POST" action="{{ route('admin.integrations.channel-manager') }}" class="mb-4">
            @csrf
            @method('PUT')

            <div class="card border mb-4">
              <div class="card-header bg-light font-weight-bold">
                <i class="fa fa-key"></i> API Credentials
                <small class="text-muted font-weight-normal ml-2">Platform-wide — stored encrypted</small>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="control-label">Provider name</label>
                      <input type="text" class="form-control" name="provider_name"
                        value="{{ old('provider_name', $channelManager['provider_name'] ?? '') }}">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="control-label d-block">Status</label>
                      <label class="mb-0">
                        <input type="checkbox" name="enabled" value="1" {{ old('enabled', $channelManager['enabled'] ?? false) ? 'checked' : '' }}>
                        Enable Channel Manager integration
                      </label>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="form-group">
                      <label class="control-label">Base URL</label>
                      <input type="url" class="form-control" name="base_url"
                        value="{{ old('base_url', $channelManager['base_url'] ?? '') }}"
                        placeholder="{{ config('channel_manager_integration.default_base_url') }}">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="control-label">Partner ID <small class="text-muted">({pms})</small></label>
                      <input type="text" class="form-control" name="partner_id"
                        value="{{ old('partner_id', $channelManager['partner_id'] ?? '') }}">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="control-label">API username</label>
                      <input type="text" class="form-control" name="api_username" autocomplete="off"
                        value="{{ old('api_username', $channelManager['api_username'] ?? '') }}">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="control-label">API password</label>
                      <input type="password" class="form-control" name="api_password" autocomplete="new-password"
                        placeholder="{{ ($channelManager['has_api_password'] ?? false) ? '•••••••• (leave blank to keep)' : 'Enter password' }}">
                    </div>
                  </div>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="cmUseSandbox" name="use_sandbox" value="1"
                    {{ old('use_sandbox', $channelManager['use_sandbox'] ?? false) ? 'checked' : '' }}>
                  <label class="custom-control-label" for="cmUseSandbox">
                    Use sandbox mode
                    <small class="text-muted">({{ config('channel_manager_integration.sandbox.hotel_code') }} / {{ config('channel_manager_integration.sandbox.partner_id') }})</small>
                  </label>
                </div>
              </div>
            </div>

            <div class="card border mb-4" id="cm-webhook-settings">
              <div class="card-header bg-light font-weight-bold">
                <i class="fa fa-plug"></i> Reservation Webhook <small class="text-muted font-weight-normal">(inbound)</small>
              </div>
              <div class="card-body">
                <div class="form-group">
                  <label class="control-label">Webhook URL</label>
                  <div class="input-group">
                    <input type="text" class="form-control" readonly value="{{ $channelManager['webhook_url'] ?? '' }}">
                    <div class="input-group-append">
                      <button type="button" class="btn btn-outline-secondary js-copy-text" data-copy="{{ $channelManager['webhook_url'] ?? '' }}">Copy</button>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="control-label">Webhook path</label>
                  <input type="text" class="form-control" name="webhook_path"
                    value="{{ old('webhook_path', $channelManager['webhook_path'] ?? '') }}">
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group mb-md-0">
                      <label class="control-label">Webhook username</label>
                      <input type="text" class="form-control" name="webhook_username" autocomplete="off"
                        value="{{ old('webhook_username', $channelManager['webhook_username'] ?? '') }}">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group mb-0">
                      <label class="control-label">Webhook password</label>
                      <input type="password" class="form-control" name="webhook_password" autocomplete="new-password"
                        placeholder="{{ ($channelManager['has_webhook_password'] ?? false) ? '•••••••• (leave blank to keep)' : 'Enter password' }}">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Integration Settings</button>
          </form>

          <div class="card border mb-4" id="cm-api-tests">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
              <span class="font-weight-bold"><i class="fa fa-flask"></i> Test All APIs</span>
              <form method="POST" action="{{ route('admin.integrations.test-apis') }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">
                  <i class="fa fa-play"></i> Run All Tests
                </button>
              </form>
            </div>
            <div class="card-body">
              <p class="text-muted mb-3">
                Runs every outbound Channel Manager call plus your inbound reservation webhook using saved credentials and sandbox sample data.
              </p>

              @if(!empty($testReport))
                @php $summary = $testReport['summary']; @endphp
                <div class="alert alert-{{ $summary['failed'] > 0 ? 'warning' : 'success' }} mb-3">
                  <strong>{{ $summary['passed'] }}/{{ $summary['total'] }} passed</strong>
                  @if($summary['failed'] > 0)
                    — {{ $summary['failed'] }} failed
                  @endif
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0">
                    <thead>
                      <tr>
                        <th>Status</th>
                        <th>API</th>
                        <th>HTTP</th>
                        <th>Message</th>
                        <th>Response</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($testReport['results'] as $row)
                        <tr>
                          <td class="{{ $row['status'] === 'pass' ? 'cm-test-pass' : ($row['status'] === 'skip' ? 'text-warning font-weight-bold' : 'cm-test-fail') }}">
                            {{ strtoupper($row['status']) }}
                          </td>
                          <td>
                            <span class="badge badge-{{ $row['method'] === 'GET' ? 'info' : 'primary' }}">{{ $row['method'] }}</span>
                            {{ $row['name'] }}
                          </td>
                          <td>{{ $row['http_code'] ?? '—' }}</td>
                          <td>{{ $row['message'] }}</td>
                          <td class="cm-test-response"><code>{{ $row['response'] ?? '—' }}</code></td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <p class="text-muted mb-0">Click <strong>Run All Tests</strong> to verify connectivity for all endpoints.</p>
              @endif
            </div>
          </div>

          <h5 class="font-weight-bold mb-3"><i class="fa fa-flag-checkered"></i> Integration steps</h5>
          @foreach($cmDoc['getting_started']['steps'] as $step)
            <div class="hotel-start-step">
              <span class="hotel-start-step__num">{{ $step['number'] }}</span>
              <div class="flex-grow-1">
                <h5 class="mb-1">{{ $step['title'] }}</h5>
                <p class="text-muted mb-0">{{ $step['body'] }}</p>
              </div>
            </div>
          @endforeach

          <div class="mt-4" id="cm-api-overview">
            <h5 class="font-weight-bold mb-2">API Overview</h5>
            <p class="text-muted">{{ $cmDoc['overview']['mapping_note'] }}</p>
            <p class="text-muted">{{ $cmDoc['overview']['auth_note'] }}</p>

            @if(!empty($channelManager['property_details_url']))
              <div class="alert alert-light border mb-3">
                <strong>Get Mapping Details (example)</strong>
                <code class="d-block mt-2 small text-break">{{ $channelManager['property_details_url'] }}</code>
              </div>
            @endif

            <div class="accordion integration-accordion" id="cmApiAccordion">
              <div class="card">
                <div class="card-header">
                  <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#cmConventions">
                    Conventions &amp; meal plans
                  </button>
                </div>
                <div id="cmConventions" class="collapse" data-parent="#cmApiAccordion">
                  <div class="card-body">
                    <div class="table-responsive mb-3">
                      <table class="table table-sm table-bordered mb-0">
                        <thead><tr><th>Term</th><th>Description</th></tr></thead>
                        <tbody>
                          @foreach($cmDoc['overview']['conventions'] as $row)
                            <tr><td><strong>{{ $row['term'] }}</strong></td><td>{{ $row['description'] }}</td></tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered mb-0">
                        <thead><tr><th>Code</th><th>Name</th><th>Includes</th></tr></thead>
                        <tbody>
                          @foreach($cmDoc['overview']['meal_plans'] as $plan)
                            <tr><td>{{ $plan['code'] }}</td><td>{{ $plan['name'] }}</td><td>{{ $plan['includes'] }}</td></tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card">
                <div class="card-header">
                  <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#cmEndpoints">
                    All endpoints
                  </button>
                </div>
                <div id="cmEndpoints" class="collapse show" data-parent="#cmApiAccordion">
                  <div class="card-body p-0" id="cm-api-endpoints">
                    <div class="table-responsive">
                      <table class="table table-sm table-hover mb-0">
                        <thead>
                          <tr><th>Method</th><th>Endpoint</th><th>URL</th></tr>
                        </thead>
                        <tbody>
                          @foreach($cmEndpoints as $endpoint)
                            <tr>
                              <td><span class="badge badge-{{ $endpoint['method'] === 'GET' ? 'info' : 'primary' }}">{{ $endpoint['method'] }}</span></td>
                              <td>
                                {{ $endpoint['name'] }}
                                @if(!empty($endpoint['inbound']))
                                  <small class="text-muted d-block">Inbound webhook</small>
                                @endif
                              </td>
                              <td><code class="small text-break">{{ $endpoint['url'] }}</code></td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <h5 class="font-weight-bold mb-3"><i class="fa fa-globe"></i> Connected OTAs</h5>
            <div class="row">
              @foreach($otas as $ota)
                <div class="col-6 col-md-4 col-lg-3 mb-3">
                  <div class="integration-ota-chip">
                    <img src="{{ asset('panel-assets/img/otas/'.$ota['logo']) }}" alt="{{ $ota['name'] }}">
                    <span>{{ $ota['name'] }}</span>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
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
          }
        });
      });
    })();
  </script>
@endpush
