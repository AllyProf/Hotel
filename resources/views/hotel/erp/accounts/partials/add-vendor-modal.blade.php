<div class="modal fade" id="acAddVendorModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('hotel.accounts.vendors.store') }}">
        @csrf
        <div class="modal-header ac-modal-header">
          <h5 class="modal-title">Add Vendor</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="ac-modal-divider"></div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Vendor Name</label>
                <input type="text" class="form-control" name="name" required>
              </div>
              <div class="form-group">
                <label class="control-label">GST Num</label>
                <input type="text" class="form-control" name="gst_num">
              </div>
              <div class="form-group">
                <label class="control-label">Address</label>
                <textarea class="form-control" name="address" rows="3"></textarea>
              </div>
              <div class="form-group">
                <label class="control-label">Email</label>
                <input type="email" class="form-control" name="email">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person">
              </div>
              <div class="form-group">
                <label class="control-label">Phone</label>
                <input type="text" class="form-control" name="phone">
              </div>
              <div class="form-group">
                <label class="control-label">State</label>
                <input type="text" class="form-control" name="state">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" style="background:#940000;border-color:#940000;">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
