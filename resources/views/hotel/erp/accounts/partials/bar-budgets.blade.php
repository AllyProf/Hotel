@inject('accountsData', 'App\Services\AccountsDataService')

<div class="ac-content ac-bar-budgets">
  @if(! $isBarAccountant)
    <div class="alert alert-info">
      Assign accountant approvers under
      <a href="{{ route('hotel.settings.index', ['tab' => 'bar']) }}">Settings → Bar</a>,
      or sign in as <strong>hotel admin</strong> (full access).
    </div>
  @endif

  <div class="table-responsive {{ $barBudgetsRejected->isNotEmpty() ? 'mb-3' : 'mb-0' }}">
    <table class="table table-bordered table-hover ac-table mb-0">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Module</th>
          <th>Department</th>
          <th>Budget</th>
          <th>Prepared by</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($barBudgetsPending as $list)
          @php
            $listCurrency = $list->resolvedBudgetCurrency();
            $budgetLabel = $list->budget_total !== null
              ? $accountsData->formatAccountsTableAmount((float) $list->budget_total, $listCurrency)
              : '—';
          @endphp
          <tr>
            <td><strong>{{ $list->reference_no }}</strong></td>
            <td>{{ \App\Support\DepartmentProcurement::label($list->workflowKey()) }}</td>
            <td>{{ $list->department?->name ?? '—' }}</td>
            <td>{{ $budgetLabel }}</td>
            <td>{{ $list->budget_prepared_by_name ?? '—' }}</td>
            <td>{{ $list->budget_prepared_at?->format('d M Y H:i') ?? '—' }}</td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-light border" data-toggle="modal" data-target="#barBudgetView{{ $list->id }}">
                View
              </button>
              @if($isBarAccountant)
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#barBudgetApprove{{ $list->id }}">
                  Approve
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#barBudgetReject{{ $list->id }}">
                  Reject
                </button>
              @endif
            </td>
          </tr>
        @endforeach
        @foreach($storeDeskPosPending ?? [] as $order)
          @php
            $poCurrency = $accountsData->storeDeskCurrency($hotel);
            $budgetLabel = $accountsData->formatAccountsTableAmount((float) $order->total, $poCurrency);
          @endphp
          <tr>
            <td><strong>{{ $order->po_number }}</strong></td>
            <td>Store PO</td>
            <td>{{ $order->storeDepartment?->name ?? '—' }}</td>
            <td>{{ $budgetLabel }}</td>
            <td>{{ $order->creator?->name ?? '—' }}</td>
            <td>{{ $order->accounts_submitted_at?->format('d M Y H:i') ?? $order->created_at?->format('d M Y H:i') ?? '—' }}</td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-light border" data-toggle="modal" data-target="#storePoBudgetView{{ $order->id }}">
                View
              </button>
              @if($isBarAccountant)
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#storePoApprove{{ $order->id }}">Approve</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#storePoReject{{ $order->id }}">Reject</button>
              @endif
            </td>
          </tr>
        @endforeach
        @if(($barBudgetsPending ?? collect())->isEmpty() && ($storeDeskPosPending ?? collect())->isEmpty())
          <tr>
            <td colspan="7" class="text-center text-muted py-4">Nothing waiting for approval.</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>

  @if($barBudgetsRejected->isNotEmpty())
    <div class="table-responsive mb-0 mt-3 pt-3 border-top">
      <table class="table table-bordered table-hover ac-table mb-0">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Module</th>
            <th>Department</th>
            <th>Budget</th>
            <th>Reason</th>
            <th>Rejected</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($barBudgetsRejected as $list)
            @php
              $listCurrency = $list->resolvedBudgetCurrency();
              $budgetLabel = $list->budget_total !== null
                ? $accountsData->formatAccountsTableAmount((float) $list->budget_total, $listCurrency)
                : '—';
            @endphp
            <tr>
              <td><strong>{{ $list->reference_no }}</strong></td>
              <td>{{ \App\Support\DepartmentProcurement::label($list->workflowKey()) }}</td>
              <td>{{ $list->department?->name ?? '—' }}</td>
              <td>{{ $budgetLabel }}</td>
              <td class="small">{{ \Illuminate\Support\Str::limit($list->accounting_notes ?? '—', 120) }}</td>
              <td class="text-nowrap small">
                {{ $list->accounting_reviewed_at?->format('d M Y H:i') ?? '—' }}
                @if($list->accounting_reviewed_by_name)
                  <span class="text-muted d-block">{{ $list->accounting_reviewed_by_name }}</span>
                @endif
              </td>
              <td class="text-nowrap">
                <button type="button" class="btn btn-sm btn-light border" data-toggle="modal" data-target="#barBudgetView{{ $list->id }}">
                  View
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('hotel.erp.accounts.partials.bar-budget-view-modals')

  @if($isBarAccountant)
    @foreach($barBudgetsPending as $list)
      @php
        $budgetLabelModal = $list->budget_total !== null
          ? $accountsData->formatAccountsBudgetLabel((float) $list->budget_total, $list->resolvedBudgetCurrency())
          : '—';
        $approveVendorId = (int) ($list->hotel_vendor_id ?: ($defaultBarVendorId ?? 0));
      @endphp
      <div class="modal fade" id="barBudgetApprove{{ $list->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST" action="{{ route('hotel.accounts.bar-purchase-lists.approve', $list) }}" class="modal-content js-swal-confirm">
            @csrf
            <div class="modal-header ac-modal-header text-white">
              <h5 class="modal-title">Approve {{ $list->reference_no }}</h5>
              <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
              <p class="mb-3">
                <strong>{{ $list->department?->name ?? '—' }}</strong>
                · {{ $budgetLabelModal }}
              </p>
              <div class="form-group">
                <label class="control-label">Payables vendor</label>
                <select class="form-control" name="hotel_vendor_id" required>
                  @forelse($payablesVendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected($approveVendorId === (int) $vendor->id)>
                      {{ $vendor->name }}
                    </option>
                  @empty
                    <option value="">Add a vendor under Payables first</option>
                  @endforelse
                </select>
              </div>
              <div class="form-group mb-0">
                <label class="control-label">Notes <span class="text-muted font-weight-normal">(optional)</span></label>
                <textarea class="form-control" name="accounting_notes" rows="2" maxlength="2000" placeholder="Optional"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary js-swal-confirm-btn"
                data-title="Approve {{ $list->reference_no }}?"
                data-text="Creates one open PO in Payables ({{ $budgetLabelModal }})."
                data-confirm="Approve">
                Approve
              </button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal fade" id="barBudgetReject{{ $list->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST" action="{{ route('hotel.accounts.bar-purchase-lists.reject', $list) }}" class="modal-content js-bar-budget-reject-form">
            @csrf
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title">Reject {{ $list->reference_no }}</h5>
              <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-0">
                <label class="control-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control js-bar-budget-reject-reason" name="accounting_notes" rows="4" maxlength="2000"
                  required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Reject</button>
            </div>
          </form>
        </div>
      </div>
    @endforeach
    @foreach($storeDeskPosPending ?? [] as $order)
      <div class="modal fade" id="storePoApprove{{ $order->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST" action="{{ route('hotel.accounts.store-purchase-orders.approve', $order) }}" class="modal-content">
            @csrf
            <div class="modal-header ac-modal-header text-white">
              <h5 class="modal-title">Approve {{ $order->po_number }}</h5>
              <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
              <p class="mb-2">Vendor: <strong>{{ $order->vendor?->name ?? '—' }}</strong>. Total: {{ number_format((float) $order->total, 2) }}.</p>
              <div class="form-group mb-0">
                <label class="control-label">Notes (optional)</label>
                <textarea class="form-control" name="accounting_notes" rows="2" maxlength="2000"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Approve for invoicing</button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal fade" id="storePoReject{{ $order->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST" action="{{ route('hotel.accounts.store-purchase-orders.reject', $order) }}" class="modal-content js-bar-budget-reject-form">
            @csrf
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title">Reject {{ $order->po_number }}</h5>
              <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-0">
                <label class="control-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control js-bar-budget-reject-reason" name="accounting_notes" rows="4" maxlength="2000" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Reject</button>
            </div>
          </form>
        </div>
      </div>
    @endforeach
  @endif
</div>

@push('scripts')
  <script>
    (function ($) {
      $(document).on('submit', '.js-bar-budget-reject-form', function (e) {
        var $form = $(this);
        var reason = $.trim($form.find('.js-bar-budget-reject-reason').val() || '');
        if (reason === '') {
          e.preventDefault();
          if (typeof AppAlerts !== 'undefined') {
            AppAlerts.error('Reason required', 'Enter a rejection reason before submitting.');
          }
          return false;
        }
      });
    })(jQuery);
  </script>
@endpush
