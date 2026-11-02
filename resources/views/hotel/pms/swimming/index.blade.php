@extends('layouts.app')

@section('title', $ui['title'] ?? 'Swimming Pool')

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sv-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 18px 20px;
      border-bottom: 3px solid var(--sv-brand);
    }

    .sv-header h3 { margin: 0; font-size: 22px; font-weight: 400; color: #333; }

    .sw-tabs .nav-link {
      color: #666;
      border: none;
      border-bottom: 2px solid transparent;
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 600;
    }

    .sw-tabs .nav-link.active {
      color: var(--sv-brand);
      border-bottom-color: var(--sv-brand);
      background: transparent;
    }

    .sw-status {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      padding: 3px 8px;
      border-radius: 3px;
    }

    .sw-status--scheduled { background: #fef3c7; color: #92400e; }
    .sw-status--in_pool { background: #dbeafe; color: #1d4ed8; }
    .sw-status--completed { background: #dcfce7; color: #15803d; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-tint"></i> {{ $ui['title'] ?? 'Swimming Pool' }}</h1>
      <p>Pool entries — {{ $service->displayCurrency($hotel) }} · {{ $service->pricingUnitLabel() }}</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Swimming' }}</li>
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

        <div class="sv-header">
          <h3>Pool desk</h3>
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('hotel.services.show', $service) }}" class="btn btn-sv-edit" style="width:auto;padding:6px 14px;">Manage service</a>
            <a href="{{ route('hotel.swimming.create') }}" class="btn btn-sv" style="width:auto;padding:8px 18px;">
              <i class="fa fa-plus"></i> Register entry
            </a>
          </div>
        </div>

        <div class="sv-section">
          <ul class="nav nav-tabs sw-tabs border-0 mb-3">
            @foreach(['today' => 'Today', 'scheduled' => 'Scheduled', 'in_pool' => 'In pool', 'completed' => 'Completed', 'all' => 'All'] as $key => $label)
              <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('hotel.swimming.index', ['tab' => $key]) }}">{{ $label }}</a>
              </li>
            @endforeach
          </ul>

          <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0">
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Rate plan</th>
                  <th>Guest</th>
                  <th>Room</th>
                  <th>Ad / Ch</th>
                  <th>Status</th>
                  <th>Total</th>
                  <th>Pool date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($visits as $visit)
                  <tr>
                    <td><strong>{{ $visit->visit_no }}</strong></td>
                    <td>{{ $visit->rate_label ?? $visit->category?->name ?? '—' }}</td>
                    <td>{{ $visit->guest_name ?? '—' }}</td>
                    <td>{{ $visit->room_no ?? '—' }}</td>
                    <td>{{ $visit->adults }} / {{ $visit->children }}</td>
                    <td><span class="sw-status sw-status--{{ $visit->status }}">{{ $visit->statusLabel() }}</span></td>
                    <td>{{ $visit->formattedTotal($hotel) }}</td>
                    <td>
                      {{ $visit->scheduled_date?->format('d/m/Y') ?? '—' }}
                      @if($visit->entered_at)
                        <br><small class="text-muted">In {{ $visit->entered_at->format('H:i') }}</small>
                      @endif
                    </td>
                    <td class="text-nowrap">
                      @if($visit->status === 'scheduled')
                        <form method="POST" action="{{ route('hotel.swimming.check-in', $visit) }}" class="d-inline">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-sv" style="width:auto;padding:4px 10px;">Check in</button>
                        </form>
                      @endif
                      @if($visit->status === 'in_pool')
                        <form method="POST" action="{{ route('hotel.swimming.complete', $visit) }}" class="d-inline">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-sv" style="width:auto;padding:4px 10px;">Left pool</button>
                        </form>
                      @endif
                      <a href="{{ route('hotel.swimming.print', $visit) }}" class="btn btn-sm btn-light border" target="_blank" rel="noopener" title="Print"><i class="fa fa-print"></i></a>
                      <a href="{{ route('hotel.swimming.show', $visit) }}" class="btn btn-sm btn-sv-edit" style="width:auto;padding:4px 10px;">View</a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="9" class="text-muted text-center py-4">No pool entries in this view.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
