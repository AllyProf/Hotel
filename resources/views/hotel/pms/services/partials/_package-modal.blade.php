<div class="modal fade" id="svPackageModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="#" id="svPackageForm">
        @csrf
        @method('PUT')

        <div class="modal-header">
          <h5 class="modal-title">Edit Package</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label class="control-label">Package Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" required maxlength="120">
          </div>
          <div class="form-group">
            <label class="control-label">Abbreviation</label>
            <input type="text" class="form-control" name="abbreviation" maxlength="20" placeholder="e.g. kg, box">
          </div>
          <label class="animated-checkbox mb-0">
            <input type="checkbox" name="is_active" value="1" checked>
            <span class="label-text">Active</span>
          </label>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sv" style="width:auto;padding:7px 18px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
