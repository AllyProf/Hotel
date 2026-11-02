@php
  $posUi = config('hotel_pos.ui', []);
@endphp

<div class="modal fade pos-view-order-modal" id="posViewOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog pos-view-order-dialog" id="posViewOrderDialog" role="document">
    <div class="modal-content pos-view-order-content">

      {{-- Current (open) orders: full detail (matches billed invoice layout) --}}
      <div id="posViewReceiptLayout">
        <div class="modal-header pos-new-order-head">
          <div>
            <h5 class="modal-title mb-0" id="posViewReceiptTitle"></h5>
            <p class="pos-view-subtitle mb-0" id="posViewHeading"></p>
          </div>
          <div class="pos-new-order-head-actions">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        </div>
        <div class="modal-body pos-view-invoice-body">
          <div class="pos-modal-divider"></div>
          <div class="row pos-view-meta-cards mb-3">
            <div class="col-md-3 mb-2 mb-md-0">
              <div class="pos-view-meta-card">
                <span class="pos-view-meta-card__label">{{ $posUi['view_order_no'] ?? 'Order No' }}</span>
                <span class="pos-view-meta-card__value" id="posViewOrderNoValue"></span>
              </div>
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
              <div class="pos-view-meta-card">
                <span class="pos-view-meta-card__label">{{ $posUi['view_date'] ?? 'Date' }}</span>
                <span class="pos-view-meta-card__value" id="posViewDateValue"></span>
              </div>
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
              <div class="pos-view-meta-card">
                <span class="pos-view-meta-card__label">Guest</span>
                <span class="pos-view-meta-card__value" id="posViewGuestValue"></span>
              </div>
            </div>
            <div class="col-md-3">
              <div class="pos-view-meta-card">
                <span class="pos-view-meta-card__label">{{ $posUi['waiter'] ?? 'Waiter' }}</span>
                <span class="pos-view-meta-card__value" id="posViewWaiterValue">—</span>
              </div>
            </div>
          </div>
          <p class="text-muted small mb-3 d-none" id="posViewAddress"></p>
          <p class="text-muted small mb-3 d-none" id="posViewTax"></p>
          <div class="form-group mb-3" id="posViewDetailsWrap">
            <label class="control-label">{{ $posUi['details'] ?? 'Details' }}</label>
            <input type="text" class="form-control pos-readonly-field" id="posViewDetailsValue" readonly tabindex="-1" value="">
          </div>
          <div class="row pos-invoice-tables-row">
            <div class="col-lg-8 mb-3 mb-lg-0">
              <div class="table-responsive">
                <table class="table table-bordered pos-lines-table mb-0" id="posViewLinesTable">
                  <thead>
                    <tr>
                      <th>{{ $posUi['table_service_name'] ?? 'Item' }}</th>
                      <th class="text-right">{{ $posUi['table_rate'] ?? 'Rate' }}</th>
                      <th class="text-right">{{ $posUi['table_qty'] ?? 'Qty' }}</th>
                      <th class="text-right">{{ $posUi['table_amt'] ?? 'Amt' }}</th>
                      <th class="text-right">{{ $posUi['table_tax'] ?? 'Tax' }}</th>
                      <th class="text-right">{{ $posUi['table_total'] ?? 'Total' }}</th>
                    </tr>
                  </thead>
                  <tbody id="posViewLinesBody"></tbody>
                  <tfoot>
                    <tr>
                      <td>{{ $posUi['table_grand_total'] ?? 'Total :' }}</td>
                      <td></td>
                      <td class="text-right" id="posViewFootQty">0</td>
                      <td class="text-right" id="posViewFootAmt"></td>
                      <td class="text-right" id="posViewFootTax"></td>
                      <td class="text-right" id="posViewFootTotal"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <p class="pos-view-empty text-muted d-none mt-2 mb-0" id="posViewEmpty">{{ $posUi['view_no_items'] ?? 'No items on this order.' }}</p>
            </div>
            <div class="col-lg-4">
              <div class="table-responsive">
                <table class="table table-bordered pos-lines-table pos-invoice-payment-table mb-0">
                  <thead>
                    <tr>
                      <th>{{ $posUi['view_type_of_payment'] ?? 'Type of Payment' }}</th>
                      <th>{{ $posUi['view_payment_comments'] ?? 'Comments' }}</th>
                      <th class="text-right">{{ $posUi['view_payment_amount'] ?? 'Amount' }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td id="posViewPaymentType">—</td>
                      <td id="posViewPaymentComments">—</td>
                      <td class="text-right" id="posViewPaymentAmount">—</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer pos-modal-footer pos-invoice-footer">
          <div class="pos-invoice-footer-actions ml-auto">
            <button type="button" class="btn btn-pos-outline d-none js-pos-view-edit-order" id="posViewEditOrderBtn" data-order-id="">
              <i class="fa fa-pencil"></i> {{ $posUi['action_edit'] ?? 'Edit' }}
            </button>
            <a href="#" class="btn btn-secondary" id="posViewPrintLink" target="_blank" rel="noopener">
              <i class="fa fa-print"></i> {{ $posUi['view_print'] ?? 'Print' }}
            </a>
            <button type="button" class="btn btn-pos" data-dismiss="modal">{{ $posUi['view_close'] ?? 'Close' }}</button>
          </div>
        </div>
      </div>

      {{-- Billed orders: full invoice layout --}}
      <div id="posViewInvoiceLayout" class="d-none pos-view-invoice-wrap">
        <div class="modal-header pos-new-order-head">
          <h5 class="modal-title mb-0" id="posInvoiceModalTitle"></h5>
          <div class="pos-new-order-head-actions">
            <a href="#" class="btn btn-pos-outline btn-sm d-none" id="posInvoiceAddService" target="_blank" rel="noopener">
              {{ $posUi['add_new_service'] ?? 'Add New Service' }}
            </a>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        </div>
        <div class="modal-body pos-view-invoice-body">
          <div class="pos-modal-divider"></div>
          <div class="form-group mb-3">
            <label class="control-label">{{ $posUi['details'] ?? 'Details' }}</label>
            <input type="text" class="form-control pos-readonly-field" id="posInvoiceDetails" readonly tabindex="-1" value="">
          </div>
          <div class="row pos-invoice-tables-row">
            <div class="col-lg-8 mb-3 mb-lg-0">
              <div class="table-responsive">
                <table class="table table-bordered pos-lines-table mb-0" id="posInvoiceLinesTable">
                  <thead>
                    <tr>
                      <th>{{ $posUi['table_service_name'] ?? 'Service Name' }}</th>
                      <th class="text-right">{{ $posUi['table_rate'] ?? 'Rate' }}</th>
                      <th class="text-right">{{ $posUi['table_qty'] ?? 'Qty' }}</th>
                      <th class="text-right">{{ $posUi['table_amt'] ?? 'Amt' }}</th>
                      <th class="text-right">{{ $posUi['table_tax'] ?? 'Tax' }}</th>
                      <th class="text-right">{{ $posUi['table_total'] ?? 'Total' }}</th>
                    </tr>
                  </thead>
                  <tbody id="posInvoiceLinesBody"></tbody>
                  <tfoot>
                    <tr>
                      <td>{{ $posUi['table_grand_total'] ?? 'Total :' }}</td>
                      <td></td>
                      <td class="text-right" id="posInvoiceFootQty">0</td>
                      <td class="text-right" id="posInvoiceFootAmt"></td>
                      <td class="text-right" id="posInvoiceFootTax"></td>
                      <td class="text-right" id="posInvoiceFootTotal"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="table-responsive">
                <table class="table table-bordered pos-lines-table pos-invoice-payment-table mb-0">
                  <thead>
                    <tr>
                      <th>{{ $posUi['view_type_of_payment'] ?? 'Type of Payment' }}</th>
                      <th>{{ $posUi['view_payment_comments'] ?? 'Comments' }}</th>
                      <th class="text-right">{{ $posUi['view_payment_amount'] ?? 'Amount' }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td id="posInvoicePaymentType">—</td>
                      <td id="posInvoicePaymentComments">—</td>
                      <td class="text-right" id="posInvoicePaymentAmount">—</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer pos-modal-footer pos-invoice-footer">
          <select class="form-control pos-invoice-printer-select" id="posInvoicePrinter" aria-label="{{ $posUi['select_printer'] ?? 'Select printer' }}">
            <option value="">{{ $posUi['select_printer'] ?? 'Select printer' }}</option>
          </select>
          <div class="pos-invoice-footer-actions">
            <button type="button" class="btn btn-pos-outline" data-dismiss="modal">{{ $posUi['view_close'] ?? 'Close' }}</button>
            <button type="button" class="btn btn-secondary" id="posInvoicePrintBtn">{{ $posUi['view_print'] ?? 'Print' }}</button>
            <a href="#" class="btn btn-primary" id="posInvoiceViewBill" target="_blank" rel="noopener">{{ $posUi['view_bill'] ?? 'View Bill' }}</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
