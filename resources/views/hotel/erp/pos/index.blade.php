@extends('layouts.app')

@section('title', 'POS')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
  @include('hotel.erp.pos.partials._styles')
@endpush

@section('content')
  @inject('posOrderService', 'App\Services\Pos\PosOrderService')
  <div class="app-title">
    <div><h1><i class="fa fa-money"></i> POS</h1></div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">ERP</a></li>
      <li class="breadcrumb-item">POS</li>
    </ul>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @php
    $posUi = config('hotel_pos.ui', []);
  @endphp

  <div class="tile pb-0 pos-page">
    <form method="GET" action="{{ route('hotel.pos.index') }}" class="pos-filters" id="posFilterForm">
      <div class="pos-filters-fields">
        <div class="form-group mb-0">
          <label>{{ $posUi['pos_name'] ?? 'POS Name' }}</label>
          <select name="department" class="form-control pos-filter-outlet" onchange="this.form.submit()">
            @foreach($posDepartments as $posDept)
              <option value="{{ $posDept->id }}" @selected($department->id === $posDept->id)>{{ $posDept->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group mb-0">
          <label>{{ $posUi['from_date'] ?? 'From Date' }}</label>
          <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}" onchange="this.form.submit()">
        </div>
        <div class="form-group mb-0">
          <label>{{ $posUi['to_date'] ?? 'To Date' }}</label>
          <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}" onchange="this.form.submit()">
        </div>
      </div>
      <div class="pos-filters-report">
        <div class="form-group mb-0">
          <label>{{ $posUi['report_type'] ?? 'Report' }}</label>
          <select name="report_type" id="posReportType" class="form-control">
            @foreach($reportTypes as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group mb-0">
          <label class="invisible d-none d-sm-block" aria-hidden="true">&nbsp;</label>
          <button type="button" class="btn btn-pos btn-pos-report" id="posRunReportBtn">
            <i class="fa fa-bar-chart"></i> {{ $posUi['run_report'] ?? 'Report' }}
          </button>
        </div>
      </div>
    </form>

    <div class="row pos-kpi-row">
      @foreach($kpiCards as $card)
        <div class="col-lg-3 col-md-6 col-sm-6 pos-kpi-col">
          <div class="alert alert-{{ $card['alert'] }} mb-0 pos-stat-box">
            <strong>{{ $card['label'] }}</strong>
            <div class="pos-stat-value">{{ $card['value'] }}</div>
            <div class="pos-stat-sub" @if($card['subtitle'] === '') aria-hidden="true" @endif>
              @if($card['subtitle'] !== '')
                {{ $card['subtitle'] }}
              @else
                &nbsp;
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="pos-section-head">
      <h4>{{ $posUi['current_orders'] ?? 'Current Orders' }}</h4>
      <div class="pos-section-tools">
        <button type="button" class="btn btn-pos" data-toggle="modal" data-target="#posNewOrderModal" data-complimentary="0">
          <i class="fa fa-plus"></i> {{ $posUi['create_order'] ?? 'Create' }}
        </button>
        <button type="button" class="btn btn-pos-complimentary" data-toggle="modal" data-target="#posNewOrderModal" data-complimentary="1">
          {{ $posUi['complimentary_order'] ?? 'Complimentary' }}
        </button>
        <input type="search" class="form-control pos-section-search" name="search"
          placeholder="{{ $posUi['search_placeholder'] ?? 'Search…' }}"
          value="{{ $search }}" form="posSearchForm">
      </div>
    </div>
    <form method="GET" id="posSearchForm" class="d-none">
      <input type="hidden" name="department" value="{{ $department->id }}">
      <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
      <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
    </form>

    <div class="table-responsive px-3 pb-3">
      <table class="table table-bordered table-hover pos-table mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>{{ $posUi['table_items'] ?? 'Items' }}</th>
            <th>Guest / Details</th>
            <th>{{ $posUi['table_waiter'] ?? 'Waiter' }}</th>
            <th class="text-right">Order Amount</th>
            <th class="text-right">Balance</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($currentOrders as $order)
            @php
              $orderBalance = (float) $order->balance;
              $orderPaidInFull = $posOrderService->isPaidInFull($order, $currency);
            @endphp
            <tr>
              <td>
                <span class="d-block">{{ $order->ordered_at?->format('d M, g:i A') }}</span>
                <span class="text-muted small">{{ $order->order_no }}</span>
              </td>
              <td>{{ $posOrderService->orderItemsLabel($order) }}</td>
              <td>{{ $posOrderService->orderGuestSummary($order) }}</td>
              <td>{{ $posOrderService->orderWaiterSummary($order) ?? '—' }}</td>
              <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $order->total, $currency) }}</td>
              <td class="text-right">
                @if($orderPaidInFull)
                  <span class="text-muted">—</span>
                @else
                  {{ $posOrderService->formatDisplayAmount($orderBalance, $currency) }}
                @endif
              </td>
              <td>
                @if($orderPaidInFull)
                  <span class="badge badge-success pos-status-paid">{{ $posUi['status_paid'] ?? 'Paid' }}</span>
                @else
                  <span class="badge badge-secondary pos-status-unpaid">{{ $posUi['status_unpaid'] ?? 'Unpaid' }}</span>
                @endif
              </td>
              <td class="pos-order-actions-cell">
                <div class="pos-order-actions">
                  <button type="button" class="btn btn-light border btn-sm js-pos-view-order" data-order-id="{{ $order->id }}"
                    title="{{ $posUi['action_view'] ?? 'View' }}">
                    <i class="fa fa-eye"></i> {{ $posUi['action_view'] ?? 'View' }}
                  </button>
                  <button type="button" class="btn btn-light border btn-sm js-pos-edit-order" data-order-id="{{ $order->id }}"
                    title="{{ $posUi['action_edit'] ?? 'Edit' }}">
                    <i class="fa fa-pencil"></i> {{ $posUi['action_edit'] ?? 'Edit' }}
                  </button>
                  <form method="POST" action="{{ route('hotel.pos.orders.destroy', $order) }}" class="d-inline js-swal-delete js-pos-delete-order-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-light border btn-sm text-danger js-pos-delete-order-btn"
                      title="{{ $posUi['action_delete'] ?? 'Delete' }}" aria-label="Delete order">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                  @if($orderPaidInFull)
                    <span class="badge badge-success pos-status-paid align-self-center">{{ $posUi['status_paid'] ?? 'Paid' }}</span>
                  @else
                    <button type="button" class="btn btn-pos btn-sm js-pos-pay"
                      data-toggle="modal" data-target="#posPaymentModal"
                      data-order-id="{{ $order->id }}"
                      data-order-no="{{ $order->order_no }}"
                      data-balance="{{ $posOrderService->roundForCurrency((float) $order->balance, $currency) }}"
                      data-currency="{{ $currency }}">
                      {{ $posUi['action_payment'] ?? 'Payment' }}
                    </button>
                  @endif
                  @if($orderPaidInFull)
                    <form method="POST" action="{{ route('hotel.pos.orders.bill', $order) }}" class="d-inline js-swal-confirm"
                      data-title="Bill this order?"
                      data-text="{{ $posUi['bill_moves_to_billed'] ?? 'This order will move to Billed Orders.' }}">
                      @csrf
                      <button type="submit" class="btn btn-pos btn-sm">{{ $posUi['action_bill'] ?? 'Bill' }}</button>
                    </form>
                  @else
                    <button type="button" class="btn btn-pos-outline btn-sm" disabled
                      title="{{ $posUi['pay_before_bill_hint'] ?? 'Pay the full amount before billing.' }}">
                      {{ $posUi['action_bill'] ?? 'Bill' }}
                    </button>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No current orders.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="pos-section-head">
      <h4>{{ $posUi['billed_orders'] ?? 'Billed Orders' }}</h4>
      <div class="pos-section-tools">
        <input type="search" class="form-control pos-section-search" name="search"
          placeholder="{{ $posUi['search_placeholder'] ?? 'Search…' }}"
          value="{{ $search }}" form="posSearchForm" aria-label="Search billed orders">
      </div>
    </div>
    <div class="table-responsive px-3 pb-4">
      <table class="table table-bordered table-hover pos-table mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Invoice No.</th>
            <th>{{ $posUi['table_items'] ?? 'Items' }}</th>
            <th>Guest / Details</th>
            <th>{{ $posUi['table_waiter'] ?? 'Waiter' }}</th>
            <th class="text-right">Billed Amount</th>
            <th class="text-right">Balance</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($billedOrders as $order)
            @php
              $billedPaidInFull = $posOrderService->isPaidInFull($order, $currency);
              $billedBalance = (float) $order->balance;
            @endphp
            <tr>
              <td>{{ $order->billed_at?->format('d M, g:i A') ?? $order->ordered_at?->format('d M, g:i A') }}</td>
              <td><strong>{{ $order->invoice_no }}</strong></td>
              <td>{{ $posOrderService->orderItemsLabel($order) }}</td>
              <td>
                <span class="d-block text-muted small">{{ $order->order_no }}</span>
                {{ $posOrderService->orderGuestSummary($order) }}
              </td>
              <td>{{ $posOrderService->orderWaiterSummary($order) ?? '—' }}</td>
              <td class="text-right">{{ $posOrderService->formatDisplayAmount((float) $order->total, $currency) }}</td>
              <td class="text-right">
                @if($billedPaidInFull)
                  <span class="text-muted">—</span>
                @else
                  {{ $posOrderService->formatDisplayAmount($billedBalance, $currency) }}
                @endif
              </td>
              <td>
                @if($billedPaidInFull)
                  <span class="badge badge-success pos-status-paid">{{ $posUi['status_paid'] ?? 'Paid' }}</span>
                @else
                  <span class="badge badge-warning pos-status-unpaid">{{ $posUi['status_unpaid'] ?? 'Unpaid' }}</span>
                @endif
              </td>
              <td class="pos-order-actions-cell">
                <div class="pos-order-actions">
                  <button type="button" class="btn btn-light border btn-sm js-pos-view-order" data-order-id="{{ $order->id }}">
                    <i class="fa fa-eye"></i> {{ $posUi['action_view'] ?? 'View' }}
                  </button>
                @if(! $billedPaidInFull)
                  <button type="button" class="btn btn-pos btn-sm js-pos-pay"
                    data-toggle="modal" data-target="#posPaymentModal"
                    data-order-id="{{ $order->id }}"
                    data-order-no="{{ $order->order_no }}"
                    data-balance="{{ $posOrderService->roundForCurrency($billedBalance, $currency) }}"
                    data-currency="{{ $currency }}">{{ $posUi['action_payment'] ?? 'Payment' }}</button>
                @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No billed orders in this range.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @include('hotel.erp.pos.partials.new-order-modal')
  @include('hotel.erp.pos.partials.view-order-modal')
  @include('hotel.erp.pos.partials.payment-modal')
  @include('hotel.erp.pos.partials.report-modal')
@endsection

@push('scripts')
  @include('hotel.erp.pos.partials.new-order-scripts')
@endpush
