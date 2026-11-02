@extends('layouts.app')

@section('title', $ui['title'] ?? 'Laundry')

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

    .sv-header h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .ld-tabs .nav-link {
      color: #666;
      border: none;
      border-bottom: 2px solid transparent;
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 600;
    }

    .ld-tabs .nav-link.active {
      color: var(--sv-brand);
      border-bottom-color: var(--sv-brand);
      background: transparent;
    }

    .ld-status {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      padding: 3px 8px;
      border-radius: 3px;
    }

    .ld-status--received,
    .ld-status--in_progress,
    .ld-status--ready { background: #fef3c7; color: #92400e; }

    .ld-status--delivered { background: #dcfce7; color: #15803d; }

    .ld-status--cancelled { background: #fee2e2; color: #991b1b; }

    .ld-table thead th {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: #666;
      background: #fafafa;
    }

    .ld-money {
      font-weight: 700;
      color: #333;
      white-space: nowrap;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-shirtsinbulk"></i> {{ $ui['title'] ?? 'Laundry' }}</h1>
      <p>Tickets and desk — currency from laundry service settings</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Laundry' }}</li>
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
          <h3>{{ $ui['title'] ?? 'Laundry' }}</h3>
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('hotel.services.show', $service) }}" class="btn btn-sv-edit" style="width:auto;padding:6px 14px;">Manage prices</a>
            <a href="{{ route('hotel.laundry.create') }}" class="btn btn-sv" style="width:auto;padding:8px 18px;">
              <i class="fa fa-plus"></i> New ticket
            </a>
          </div>
        </div>

        <div class="sv-section">
          <ul class="nav nav-tabs ld-tabs border-0 mb-3 flex-wrap">
            @foreach([
              'progressing' => 'Progressing',
              'done' => 'Done',
              'all' => 'All',
              'purchase-lists' => 'Purchase lists',
              'received' => 'Received',
              'stock-receive' => 'Receive stock',
            ] as $key => $label)
              <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('hotel.laundry.index', ['tab' => $key]) }}">
                  {{ $label }}
                  @if($key === 'stock-receive' && ($stockReceivePendingCount ?? 0) > 0)
                    <span class="badge badge-warning ml-1">{{ $stockReceivePendingCount }}</span>
                  @endif
                </a>
              </li>
            @endforeach
          </ul>

          @if($isProcurementTab ?? false)
            @include('hotel.erp.procurement.partials._module-shell')
          @else
          <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 ld-table">
              <thead>
                <tr>
                  <th>Ticket</th>
                  <th>Room</th>
                  <th>Guest</th>
                  <th>Status</th>
                  <th>Express</th>
                  <th>Total</th>
                  <th>Received</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($orders as $order)
                  <tr>
                    <td><strong>{{ $order->ticket_no }}</strong></td>
                    <td>{{ $order->room_no ?? '—' }}</td>
                    <td>{{ $order->guest_name ?? '—' }}</td>
                    <td><span class="ld-status ld-status--{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                    <td>{{ $order->is_express ? 'Yes' : '—' }}</td>
                    <td class="ld-money">{{ $order->formattedTotal($hotel) }}</td>
                    <td>{{ $order->received_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-nowrap">
                      @if($order->isOpen())
                        <form method="POST" action="{{ route('hotel.laundry.update-status', $order) }}" class="d-inline">
                          @csrf
                          <input type="hidden" name="status" value="delivered">
                          <button type="submit" class="btn btn-sm btn-sv" style="width:auto;padding:4px 10px;">Done</button>
                        </form>
                      @endif
                      <a href="{{ route('hotel.laundry.print', $order) }}" class="btn btn-sm btn-light border" style="width:auto;padding:4px 8px;" target="_blank" rel="noopener" title="Print bill"><i class="fa fa-print"></i></a>
                      <a href="{{ route('hotel.laundry.show', $order) }}" class="btn btn-sm btn-sv-edit" style="width:auto;padding:4px 10px;">View</a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-muted text-center py-4">No laundry tickets in this view.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
