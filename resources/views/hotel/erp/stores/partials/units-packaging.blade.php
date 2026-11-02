@php
  $storePackages = $storePackages ?? collect();
@endphp

<div class="stores-toolbar">
  <div class="stores-toolbar-row">
    <button type="button" class="btn btn-stores-outline btn-sm" data-toggle="modal" data-target="#storesPackageModal">
      <i class="fa fa-plus"></i> Add unit
    </button>
  </div>
  <p class="text-muted small mb-0 mt-2">Receiving units for stock (same idea as <strong>Bar → Register items → Unit &amp; Packaging</strong>).</p>
</div>

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        <th>Unit name</th>
        <th>Abbreviation</th>
        <th style="width:72px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($storePackages as $package)
        <tr>
          <td>{{ $package->name }}</td>
          <td>{{ $package->abbreviation ?: '—' }}</td>
          <td class="text-nowrap">
            <button type="button" class="stores-icon-btn text-primary js-stores-edit-package" title="Edit"
              data-id="{{ $package->id }}"
              data-name="{{ $package->name }}"
              data-abbreviation="{{ $package->abbreviation }}">
              <i class="fa fa-pencil"></i>
            </button>
            <form method="POST" action="{{ route('hotel.stores.packages.destroy', $package) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="stores-icon-btn text-danger" title="Delete"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="text-muted text-center py-4">No units yet.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="modal fade" id="storesPackageModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="{{ route('hotel.stores.packages.store') }}" id="storesPackageForm" data-no-loader>
      @csrf
      <input type="hidden" name="_method" id="storesPackageMethod" value="">
      <div class="modal-content">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesPackageModalTitle">Add unit</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0">
          <div class="stores-modal-divider"></div>
          <div class="form-group">
            <label class="control-label">Unit name</label>
            <input type="text" name="name" id="storesPackageName" class="form-control" required maxlength="80" placeholder="e.g. Carton">
          </div>
          <div class="form-group mb-0">
            <label class="control-label">Abbreviation (optional)</label>
            <input type="text" name="abbreviation" id="storesPackageAbbr" class="form-control" maxlength="20" placeholder="e.g. CTN">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-stores-outline">{{ $ui['submit'] ?? 'Submit' }}</button>
        </div>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
jQuery(function ($) {
  var storeUrl = @json(route('hotel.stores.packages.store'));
  var updateUrlTemplate = @json(url('/hotel/stores/packages/__ID__'));

  $('#storesPackageModal').on('show.bs.modal', function (e) {
    if ($(e.relatedTarget).length) {
      $('#storesPackageForm').attr('action', storeUrl);
      $('#storesPackageMethod').val('');
      $('#storesPackageModalTitle').text('Add unit');
      $('#storesPackageName').val('');
      $('#storesPackageAbbr').val('');
    }
  });

  $('.js-stores-edit-package').on('click', function () {
    var $btn = $(this);
    $('#storesPackageForm').attr('action', updateUrlTemplate.replace('__ID__', $btn.data('id')));
    $('#storesPackageMethod').val('PUT');
    $('#storesPackageModalTitle').text('Edit unit');
    $('#storesPackageName').val($btn.data('name') || '');
    $('#storesPackageAbbr').val($btn.data('abbreviation') || '');
    $('#storesPackageModal').modal('show');
  });
});
</script>
@endpush
