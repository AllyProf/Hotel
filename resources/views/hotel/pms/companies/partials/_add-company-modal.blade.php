<div class="modal fade" id="coAddCompanyModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.companies.store') }}" id="coAddCompanyForm">
        @csrf
        <div class="modal-header co-add-modal__header">
          <h5 class="modal-title">Add Company</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="co-add-modal__divider"></div>

          <div class="alert alert-danger d-none js-co-add-error mb-3"></div>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Company Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Email</label>
                <input type="email" class="form-control" name="email">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Phone</label>
                <input type="text" class="form-control" name="phone">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-9">
              <div class="form-group">
                <label class="control-label">Address</label>
                <input type="text" class="form-control" name="address">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">GST/ VAT</label>
                <input type="text" class="form-control" name="gst_vat">
              </div>
            </div>
          </div>

          <div class="co-add-modal__section">Contracted Rate :</div>
          <div class="row">
            @forelse($options['contracted_rate_fields'] ?? [] as $field)
              <div class="col-md-3">
                <div class="form-group">
                  <label class="control-label">{{ $field['label'] }}</label>
                  <input type="number" step="0.01" min="0" class="form-control"
                    name="contracted_rates[{{ $field['key'] }}]">
                </div>
              </div>
            @empty
              <div class="col-md-12">
                <p class="text-muted mb-0">No rate plans configured yet. Add rooms and rate plans in Settings first.</p>
              </div>
            @endforelse
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary js-co-add-submit" style="background:#940000;border-color:#940000;">
            Submit
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
