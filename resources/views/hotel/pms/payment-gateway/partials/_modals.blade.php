<div class="modal fade" id="pgAutoLinksModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header pg-modal__header">
        <h5 class="modal-title">Send Payment Links Automatically</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pg-modal__body">
        <p class="mb-0">
          All Postpaid bookings will be sent a Payment Link automatically on their email ID.
          When the payment is made, the details will be recorded in the system automatically.
        </p>
        @if($autoSendEnabled ?? false)
          <p class="text-success mt-3 mb-0"><strong>This feature is currently enabled.</strong></p>
        @endif
      </div>
      <div class="modal-footer">
        <form method="POST" action="{{ route('hotel.payment-gateway.auto-links') }}" class="d-inline">
          @csrf
          <input type="hidden" name="from_date" value="{{ $filters['from_date'] ?? '' }}">
          <input type="hidden" name="to_date" value="{{ $filters['to_date'] ?? '' }}">
          @if($filters['submitted'] ?? false)
            <input type="hidden" name="submitted" value="1">
          @endif
          <button type="submit" class="btn btn-pg-modal-primary">Ok</button>
        </form>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pgSendLinkModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.payment-gateway.send-link') }}" id="pgSendLinkForm">
        @csrf
        <input type="hidden" name="from_date" value="{{ $filters['from_date'] ?? '' }}">
        <input type="hidden" name="to_date" value="{{ $filters['to_date'] ?? '' }}">
        @if($filters['submitted'] ?? false)
          <input type="hidden" name="submitted" value="1">
        @endif

        <div class="modal-header pg-modal__header-plain">
          <div>
            <h5 class="modal-title mb-1">Send Payment Link</h5>
            <p class="pg-modal__subtitle mb-0">Please specify details to send the Payment Link.</p>
          </div>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="pg-modal__divider"></div>

          <div class="form-group">
            <label class="control-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="email" placeholder="Email" required>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Phone No. <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="phone" placeholder="Phone" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Amount <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" placeholder="Amount" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-0">
                <label class="control-label">Guest Name</label>
                <input type="text" class="form-control" name="guest_name" placeholder="Name">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group mb-0">
                <label class="control-label">Invoice Id</label>
                <input type="text" class="form-control" name="invoice_id" placeholder="Invoice">
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-pg-modal-primary">Send Link</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="pgBankDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.payment-gateway.bank-details') }}" id="pgBankDetailsForm">
        @csrf
        <input type="hidden" name="from_date" value="{{ $filters['from_date'] ?? '' }}">
        <input type="hidden" name="to_date" value="{{ $filters['to_date'] ?? '' }}">
        @if($filters['submitted'] ?? false)
          <input type="hidden" name="submitted" value="1">
        @endif

        <div class="modal-header pg-modal__header-plain">
          <div>
            <h5 class="modal-title mb-1">Bank Details</h5>
            <p class="pg-modal__subtitle mb-0">Settlement account details for payment gateway transfers.</p>
          </div>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="pg-modal__divider"></div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Bank Name</label>
                <input type="text" class="form-control" name="bank_name"
                  value="{{ old('bank_name', $bankDetails['bank_name'] ?? '') }}" placeholder="Bank Name">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Account Name</label>
                <input type="text" class="form-control" name="bank_account_name"
                  value="{{ old('bank_account_name', $bankDetails['bank_account_name'] ?? '') }}" placeholder="Account Name">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-md-0">
                <label class="control-label">Account No.</label>
                <input type="text" class="form-control" name="bank_account_no"
                  value="{{ old('bank_account_no', $bankDetails['bank_account_no'] ?? '') }}" placeholder="Account No.">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group mb-0">
                <label class="control-label">IFSC Code</label>
                <input type="text" class="form-control" name="bank_ifsc"
                  value="{{ old('bank_ifsc', $bankDetails['bank_ifsc'] ?? '') }}" placeholder="IFSC Code">
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-pg-modal-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
