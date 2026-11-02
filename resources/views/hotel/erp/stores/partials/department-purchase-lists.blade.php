@php
  $lists = $storeDepartmentPurchaseLists ?? collect();
  $storeKeeperPurchaseOrders = $storeKeeperPurchaseOrders ?? collect();
  $storePoPermissions = $storePoPermissions ?? [];
  $poVendors = $poVendors ?? collect();
  $poSearch = $poSearch ?? '';
  $workflows = $storePlWorkflows ?? collect();
  $plDepartments = $storePlDepartments ?? collect();
  $plWorkflow = $plWorkflowFilter ?? '';
  $plDeptId = (int) ($plDepartmentId ?? 0);
  $batchDepts = $storePlBatchDepartments ?? collect();
  $quickSubmitTotal = (int) ($storePlQuickSubmitTotal ?? 0);

  $unifiedRows = collect();
  foreach ($lists as $list) {
    $unifiedRows->push([
      'kind' => 'dept_list',
      'sort_at' => $list->sent_at ?? $list->created_at,
      'list' => $list,
    ]);
  }
  foreach ($storeKeeperPurchaseOrders as $po) {
    $unifiedRows->push([
      'kind' => 'store_po',
      'sort_at' => $po->created_at,
      'po' => $po,
    ]);
  }
  $unifiedRows = $unifiedRows->sortByDesc(fn (array $row) => $row['sort_at']?->getTimestamp() ?? 0)->values();
@endphp

@if(! ($canAccessStoresDepartmentLists ?? false))
  <div class="alert alert-info mx-3 mt-3">
    Lists from Bar, HK, Laundry, and Reception appear here for the <strong>stores team</strong>.
    Assign the <strong>View stores &amp; purchase orders</strong> permission under Hotel → Roles.
  </div>
@else
  <div class="d-flex flex-wrap align-items-center justify-content-between px-3 pt-3 pb-2 gap-2">
    <div class="dropdown stores-dept-pl-batch-dropdown">
      <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
        @disabled($quickSubmitTotal === 0) title="{{ $quickSubmitTotal === 0 ? 'Enter unit costs on lists first' : '' }}">
        <i class="fa fa-paper-plane"></i> Submit to Accounts
      </button>
      <div class="dropdown-menu">
        <form method="POST" action="{{ route('hotel.stores.department-purchase-lists.batch-submit') }}" class="js-swal-confirm stores-dept-pl-dropdown-form"
          data-title="Submit all ready lists?"
          data-text="Send every list that has full pricing to Accounts."
          data-confirm="Submit all">
          @csrf
          <button type="submit" class="dropdown-item">All ready ({{ $quickSubmitTotal }})</button>
        </form>
        @if($batchDepts->isNotEmpty())
          <div class="dropdown-divider"></div>
          @foreach($batchDepts as $batchDept)
            <form method="POST" action="{{ route('hotel.stores.department-purchase-lists.batch-submit') }}" class="js-swal-confirm stores-dept-pl-dropdown-form"
              data-title="Submit {{ $batchDept['name'] }}?"
              data-text="Send {{ $batchDept['count'] }} ready list(s) from this department to Accounts."
              data-confirm="Submit">
              @csrf
              @if((int) $batchDept['id'] > 0)
                <input type="hidden" name="department_id" value="{{ $batchDept['id'] }}">
              @endif
              <button type="submit" class="dropdown-item">{{ $batchDept['name'] }} ({{ $batchDept['count'] }})</button>
            </form>
          @endforeach
        @endif
      </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="{{ route('hotel.roles.index') }}" class="btn btn-light btn-sm stores-po-access-link">
        <i class="fa fa-lock"></i> PO access (roles)
      </a>
      @if($storePoPermissions['create'] ?? false)
        <button type="button" class="btn btn-stores-po-create" data-toggle="modal" data-target="#storesCreatePoModal"
          @if($poVendors->isEmpty()) disabled title="Add vendors under Settings → Vendor first" @endif>
          Create PO
        </button>
      @endif
    </div>
  </div>

  @if($workflows->count() > 1 || $plDepartments->isNotEmpty())
    <form method="GET" action="{{ route('hotel.stores.index') }}" class="px-3 pb-3">
      <input type="hidden" name="tab" value="purchase-orders">
      @if($plWorkflow !== '' && $workflows->count() <= 1)
        <input type="hidden" name="pl_workflow" value="{{ $plWorkflow }}">
      @endif
      <div class="form-row align-items-end">
        @if($workflows->count() > 1)
          <div class="form-group col-md-3 mb-2 mb-md-0">
            <label class="control-label small mb-1">Module</label>
            <select class="form-control form-control-sm" name="pl_workflow" onchange="this.form.submit()">
              <option value="">All modules</option>
              @foreach($workflows as $wfKey => $wfLabel)
                <option value="{{ $wfKey }}" @selected($plWorkflow === $wfKey)>{{ $wfLabel }}</option>
              @endforeach
            </select>
          </div>
        @endif
        @if($plDepartments->count() > 1)
          <div class="form-group col-md-3 mb-0">
            <label class="control-label small mb-1">Requesting department</label>
            <select class="form-control form-control-sm" name="pl_department" onchange="this.form.submit()">
              <option value="">All departments</option>
              @foreach($plDepartments as $department)
                <option value="{{ $department->id }}" @selected($plDeptId === (int) $department->id)>{{ $department->name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        @if($plWorkflow !== '' || $plDeptId > 0)
          <div class="form-group col-auto mb-0">
            <a href="{{ route('hotel.stores.index', ['tab' => 'purchase-orders']) }}" class="btn btn-light border btn-sm">Clear filters</a>
          </div>
        @endif
      </div>
    </form>
  @endif

  <div class="table-responsive stores-dept-pl-table-wrap px-3 pb-3">
    <table class="table table-hover table-bordered mb-0 stores-po-list-table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Created by</th>
          <th>Department</th>
          <th>Sent</th>
          <th>Qty</th>
          <th>Price status</th>
          <th>Store review</th>
          <th>Accounts</th>
          <th style="width:52px"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($unifiedRows as $row)
          @if($row['kind'] === 'dept_list')
            @php
              $list = $row['list'];
              $procW = $list->workflowKey();
              $procRoute = fn (string $suffix, ...$params) => \App\Support\DepartmentProcurement::route($procW, $suffix, ...$params);
              $canEdit = $list->canStoreEditBudget();
              $canQuickSubmit = $list->canQuickSubmitBudgetToAccounts();
              $respondUrl = $procRoute('purchase-lists.respond', $list).'?return=stores';
              $priceStatus = $list->buyingPriceStatusLabel();
            @endphp
            <tr>
              <td><strong>{{ $list->reference_no }}</strong></td>
              <td>{{ $list->creator?->name ?? '—' }}</td>
              <td>{{ $list->department?->name ?? '—' }}</td>
              <td>{{ $list->sent_at?->format('d M Y H:i') ?? '—' }}</td>
              <td>
                {{ $list->totalRequestedQuantityLabel() }}
                @if($list->lines->count() > 1)
                  <span class="text-muted small d-block">{{ $list->lines->count() }} items</span>
                @endif
              </td>
              <td>
                @if($priceStatus === 'Submitted')
                  <span class="badge badge-secondary">Submitted</span>
                @elseif($priceStatus === 'Edited')
                  <span class="badge badge-success">Edited</span>
                @elseif(str_starts_with($priceStatus, 'Partial'))
                  <span class="badge badge-warning">{{ $priceStatus }}</span>
                @else
                  <span class="badge badge-light border text-muted">Not edited</span>
                @endif
              </td>
              <td>
                @if($list->isAwaitingPayablesPaymentForMarket())
                  <span class="badge badge-warning">Awaiting payment</span>
                @elseif($list->canStoreMarketReceive())
                  <span class="badge badge-success">Ready for market</span>
                @elseif($list->isMarketPendingPurchase())
                  <span class="badge badge-warning">Awaiting payment</span>
                @elseif($list->isAccountingRejected())
                  <span class="badge badge-danger">Revise budget</span>
                @elseif($list->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
                  <span class="badge badge-success">Budget submitted</span>
                @elseif($list->recipient_status === \App\Models\HotelBarPurchaseList::RECIPIENT_IN_PROGRESS)
                  <span class="badge badge-info">{{ $list->recipientStatusLabel() }}</span>
                @else
                  <span class="badge badge-warning">{{ $list->recipientStatusLabel() }}</span>
                @endif
              </td>
              <td>
                @if($list->recipient_status !== \App\Models\HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
                  <span class="text-muted small">After store</span>
                @elseif($list->isAwaitingPayablesPaymentForMarket())
                  <span class="badge badge-warning">Pay in Accounts</span>
                  @if($list->purchaseOrder)
                    <a href="{{ route('hotel.accounts.index', ['tab' => 'payables', 'search' => $list->purchaseOrder->po_number]) }}" class="small d-block">Open Payables</a>
                  @endif
                @elseif($list->effectiveAccountingStatus() === \App\Models\HotelBarPurchaseList::ACCOUNTING_APPROVED)
                  <span class="badge badge-success">{{ $list->accountingStatusLabel() }}</span>
                @elseif($list->effectiveAccountingStatus() === \App\Models\HotelBarPurchaseList::ACCOUNTING_REJECTED)
                  <span class="badge badge-danger">{{ $list->accountingStatusLabel() }}</span>
                @else
                  <span class="badge badge-warning">{{ $list->accountingStatusLabel() }}</span>
                @endif
              </td>
              <td class="text-center stores-dept-pl-actions-cell">
                <div class="dropdown stores-dept-pl-row-dropdown">
                  <button class="btn btn-sm stores-icon-btn text-secondary" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
                    <i class="fa fa-ellipsis-v"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-right stores-dept-pl-dropdown-menu">
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#storeDeptPlView{{ $list->id }}">View</button>
                    @if($list->canStoreMarketReceive())
                      <a class="dropdown-item" href="{{ $procRoute('purchase-lists.print', $list) }}" target="_blank" rel="noopener">Print list</a>
                      <a class="dropdown-item font-weight-bold text-success" href="{{ $procRoute('purchase-lists.market.form', $list) }}">Market receive</a>
                    @elseif($list->isAwaitingPayablesPaymentForMarket())
                      <a class="dropdown-item" href="{{ $procRoute('purchase-lists.print', $list) }}" target="_blank" rel="noopener">Print list</a>
                      <p class="dropdown-item-text stores-dept-pl-dropdown-hint mb-0">Pay {{ $list->purchaseOrder?->po_number ?? 'the PO' }} in <strong>Accounts → Payables</strong> to unlock market receive.</p>
                    @elseif($list->isMarketStockPending())
                      <a class="dropdown-item font-weight-bold text-success" href="{{ $procRoute('purchase-lists.market-stock-receive.form', $list) }}">Receive stock</a>
                    @endif
                    @if($canEdit)
                      <a class="dropdown-item" href="{{ $respondUrl }}">Edit &amp; add items</a>
                      <button type="button" class="dropdown-item js-store-pl-return"
                        data-action="{{ route('hotel.stores.department-purchase-lists.return', $list) }}">Return to department</button>
                    @endif
                    @if($canQuickSubmit)
                      <form method="POST" action="{{ route('hotel.stores.department-purchase-lists.submit-to-accounts', $list) }}" class="js-swal-confirm stores-dept-pl-dropdown-form"
                        data-title="Submit to Accounts?"
                        data-text="Send {{ $list->reference_no }} to the accountant queue."
                        data-confirm="Submit">
                        @csrf
                        <button type="submit" class="dropdown-item text-primary font-weight-bold">Submit to Accounts</button>
                      </form>
                    @elseif($canEdit)
                      <div class="dropdown-divider"></div>
                      <p class="dropdown-item-text stores-dept-pl-dropdown-hint mb-0">Enter all unit costs under <strong>Edit &amp; add items</strong>, then submit to Accounts.</p>
                    @endif
                  </div>
                </div>
              </td>
            </tr>
          @else
            @php
              $po = $row['po'];
              $po->loadMissing('items', 'vendor', 'storeDepartment', 'creator');
              $poLineCount = $po->items->count();
              $poPriceStatus = $po->storeDeskBuyingPriceStatusLabel();
            @endphp
            <tr>
              <td><strong>{{ $po->po_number }}</strong></td>
              <td>{{ $po->creator?->name ?? '—' }}</td>
              <td>{{ $po->storeDepartment?->name ?? '—' }}</td>
              <td>{{ $po->sentAtLabel() }}</td>
              <td>
                {{ $po->totalQuantityLabel() }}
                @if($poLineCount > 1)
                  <span class="text-muted small d-block">{{ $poLineCount }} items</span>
                @endif
              </td>
              <td>
                @if($poPriceStatus === 'Submitted')
                  <span class="badge badge-secondary">Submitted</span>
                @elseif($poPriceStatus === 'Edited')
                  <span class="badge badge-success">Edited</span>
                @elseif(str_starts_with($poPriceStatus, 'Partial'))
                  <span class="badge badge-warning">{{ $poPriceStatus }}</span>
                @elseif($poPriceStatus === 'Not edited')
                  <span class="badge badge-light border text-muted">Not edited</span>
                @else
                  <span class="text-muted small">{{ $poPriceStatus }}</span>
                @endif
              </td>
              <td>
                @if($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_REJECTED)
                  <span class="badge badge-danger">{{ $po->storeDeskReviewLabel() }}</span>
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_PENDING)
                  <span class="badge badge-success">{{ $po->storeDeskReviewLabel() }}</span>
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_APPROVED && ! $po->goods_received && ! $po->isPaid())
                  <span class="badge badge-warning">Awaiting payment</span>
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_APPROVED && ! $po->goods_received && $po->isPaid())
                  <span class="badge badge-success">Ready to receive</span>
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_APPROVED)
                  <span class="badge badge-success">{{ $po->storeDeskReviewLabel() }}</span>
                @elseif($po->canSubmitStoreDeskToAccounts())
                  <span class="badge badge-info">{{ $po->storeDeskReviewLabel() }}</span>
                @else
                  <span class="badge badge-warning">{{ $po->storeDeskReviewLabel() }}</span>
                @endif
              </td>
              <td>
                @if($po->isPaid())
                  <span class="badge badge-primary">{{ $po->storeDeskAccountsDisplayLabel() }}</span>
                  @if($po->paid_at)
                    <span class="text-muted small d-block">{{ $po->paid_at->format('d M Y') }}</span>
                  @endif
                  @if($po->hasBudgetReturn())
                    <span class="small text-success d-block">Return {{ number_format((float) $po->budget_return_amount, 2) }}</span>
                  @endif
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_APPROVED)
                  <span class="badge badge-success">{{ $po->storeDeskAccountsDisplayLabel() }}</span>
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_REJECTED)
                  <span class="badge badge-danger">{{ $po->storeDeskAccountsDisplayLabel() }}</span>
                @elseif($po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_PENDING)
                  <span class="badge badge-warning">{{ $po->storeDeskAccountsDisplayLabel() }}</span>
                @else
                  <span class="text-muted small">{{ $po->storeDeskAccountsDisplayLabel() }}</span>
                @endif
              </td>
              <td class="text-center stores-dept-pl-actions-cell">
                <div class="dropdown stores-dept-pl-row-dropdown">
                  <button class="btn btn-sm stores-icon-btn text-secondary" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
                    <i class="fa fa-ellipsis-v"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-right stores-dept-pl-dropdown-menu">
                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#storePoQueueView{{ $po->id }}">View</button>
                    @if($po->canPrintStoreDeskPurchaseList())
                      <a class="dropdown-item" href="{{ route('hotel.stores.purchase-orders.print', $po) }}" target="_blank" rel="noopener">Print list</a>
                    @endif
                    @if(($storePoPermissions['edit'] ?? false) && $po->canStoreDeskEditBuyingPrices())
                      <button type="button" class="dropdown-item js-stores-po-edit" data-id="{{ $po->id }}">Edit buying prices</button>
                    @endif
                    @if($po->canSubmitStoreDeskToAccounts())
                      <form method="POST" action="{{ route('hotel.stores.purchase-orders.submit-to-accounts', $po) }}" class="js-swal-confirm stores-dept-pl-dropdown-form"
                        data-title="Submit to Accounts?"
                        data-text="Send {{ $po->po_number }} with buying prices to the accountant queue."
                        data-confirm="Submit">
                        @csrf
                        <button type="submit" class="dropdown-item text-primary font-weight-bold">Submit to Accounts</button>
                      </form>
                    @elseif($po->canStoreDeskEditBuyingPrices())
                      <p class="dropdown-item-text stores-dept-pl-dropdown-hint mb-0">Enter a <strong>buying price</strong> on every line under Edit, then submit to Accounts.</p>
                    @endif
                    @if(($storePoPermissions['invoice'] ?? false) && $po->canReceiveStoreDeskGoods())
                      <a class="dropdown-item font-weight-bold text-success"
                        href="{{ route('hotel.stores.purchase-orders.receive', $po) }}">Receive goods</a>
                    @elseif(($storePoPermissions['invoice'] ?? false)
                      && $po->accounts_status === \App\Models\HotelPurchaseOrder::ACCOUNTS_APPROVED
                      && ! $po->goods_received
                      && ! $po->isPaid())
                      <p class="dropdown-item-text stores-dept-pl-dropdown-hint mb-0">Pay {{ $po->po_number }} in <strong>Accounts → Payables</strong> to unlock receive.</p>
                    @endif
                    @if(($storePoPermissions['delete'] ?? false) && ! $po->isPaid() && $po->canStoreDeskEditBuyingPrices())
                      <form method="POST" action="{{ route('hotel.stores.purchase-orders.destroy', $po) }}" class="js-swal-delete stores-dept-pl-dropdown-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">Delete</button>
                      </form>
                    @endif
                  </div>
                </div>
              </td>
            </tr>
          @endif
        @empty
          <tr>
            <td colspan="9" class="text-center text-muted py-4">
              No department lists or store POs yet. Use <strong>Create PO</strong> or wait for a department to send a list.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @include('hotel.erp.stores.partials.purchase-queue-view-modals')

  <div class="modal fade" id="storePlReturnModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form method="POST" id="storePlReturnForm" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Return to department</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-0">
            <label class="control-label">Message to department <span class="text-danger">*</span></label>
            <textarea class="form-control" name="return_notes" rows="4" maxlength="2000" required placeholder="What should they change or add?"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Return list</button>
        </div>
      </form>
    </div>
  </div>

  @push('scripts')
  <script>
    jQuery(function ($) {
      $('.js-store-pl-return').on('click', function () {
        $('#storePlReturnForm').attr('action', $(this).data('action'));
        $('#storePlReturnModal').modal('show');
      });
    });
  </script>
  @endpush
@endif
