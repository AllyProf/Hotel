<div class="modal fade" id="exAddExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.expenses.store') }}" id="exAddExpenseForm" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
        <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
        <input type="hidden" name="filter_payment_type" value="{{ $filters['payment_type'] }}">
        <input type="hidden" name="filter_paid_type" value="{{ $filters['paid_type'] }}">

        <div class="modal-header ex-modal__header">
          <h5 class="modal-title">Add Expense</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="ex-modal__divider"></div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Type of Payment</label>
                <select class="form-control" name="payment_type" required>
                  <option value="">Select option</option>
                  @foreach($options['payment_types'] ?? [] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" name="amount" value="0" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Select Category</label>
                <select class="form-control" name="category" required>
                  <option value="">Select option</option>
                  @foreach($options['categories'] ?? [] as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Date</label>
                <input type="date" class="form-control" name="expense_date" value="{{ now()->format('Y-m-d') }}" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Invoice No.</label>
                <input type="text" class="form-control" name="invoice_no">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="control-label">Vendor</label>
                <input type="text" class="form-control" name="vendor">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="form-group mb-md-0">
                <label class="control-label">Comments</label>
                <textarea class="form-control" name="comments" rows="4"></textarea>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group mb-0">
                <label class="control-label">Details</label>
                <div class="ex-file-input">
                  <input type="text" class="form-control js-ex-file-label" readonly placeholder="">
                  <label class="ex-file-browse">
                    BROWSE
                    <input type="file" class="d-none js-ex-file-input" name="details">
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-secondary js-ex-expense-submit" disabled>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
