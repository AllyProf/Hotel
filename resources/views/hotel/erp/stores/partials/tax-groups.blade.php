@php
  $storeTaxGroups = $storeTaxGroups ?? collect();
@endphp

<div class="stores-toolbar">
  <div class="stores-toolbar-row">
    <button type="button" class="btn btn-stores-outline btn-sm" data-toggle="modal" data-target="#storesTaxGroupModal">
      <i class="fa fa-plus"></i> Add tax group
    </button>
  </div>
  <p class="text-muted small mb-0 mt-2">Tax groups used on <strong>Master Items</strong>. Add VAT/GST labels and optional rate %.</p>
</div>

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        <th>Tax group</th>
        <th class="text-right">Rate %</th>
        <th style="width:72px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($storeTaxGroups as $group)
        <tr>
          <td>{{ $group->name }}</td>
          <td class="text-right">{{ $group->rate_percent !== null ? number_format((float) $group->rate_percent, 2) : '—' }}</td>
          <td class="text-nowrap">
            <button type="button" class="stores-icon-btn text-primary js-stores-edit-tax-group" title="Edit"
              data-id="{{ $group->id }}"
              data-name="{{ $group->name }}"
              data-rate="{{ $group->rate_percent !== null ? (float) $group->rate_percent : '' }}">
              <i class="fa fa-pencil"></i>
            </button>
            <form method="POST" action="{{ route('hotel.stores.tax-groups.destroy', $group) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="stores-icon-btn text-danger" title="Delete"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="text-muted text-center py-4">No tax groups yet.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="modal fade" id="storesTaxGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="{{ route('hotel.stores.tax-groups.store') }}" id="storesTaxGroupForm" data-no-loader>
      @csrf
      <input type="hidden" name="_method" id="storesTaxGroupMethod" value="">
      <div class="modal-content">
        <div class="modal-header stores-modal-header">
          <h5 class="modal-title" id="storesTaxGroupModalTitle">Add tax group</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-0">
          <div class="stores-modal-divider"></div>
          <div class="form-group">
            <label class="control-label">Name</label>
            <input type="text" name="name" id="storesTaxGroupName" class="form-control" required maxlength="80" placeholder="e.g. VAT 18%">
          </div>
          <div class="form-group mb-0">
            <label class="control-label">Rate % (optional)</label>
            <input type="number" step="0.01" min="0" max="100" name="rate_percent" id="storesTaxGroupRate" class="form-control" placeholder="18">
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
  var storeUrl = @json(route('hotel.stores.tax-groups.store'));
  var updateUrlTemplate = @json(url('/hotel/stores/tax-groups/__ID__'));

  $('#storesTaxGroupModal').on('show.bs.modal', function (e) {
    if ($(e.relatedTarget).length) {
      $('#storesTaxGroupForm').attr('action', storeUrl);
      $('#storesTaxGroupMethod').val('');
      $('#storesTaxGroupModalTitle').text('Add tax group');
      $('#storesTaxGroupName').val('');
      $('#storesTaxGroupRate').val('');
    }
  });

  $('.js-stores-edit-tax-group').on('click', function () {
    var $btn = $(this);
    $('#storesTaxGroupForm').attr('action', updateUrlTemplate.replace('__ID__', $btn.data('id')));
    $('#storesTaxGroupMethod').val('PUT');
    $('#storesTaxGroupModalTitle').text('Edit tax group');
    $('#storesTaxGroupName').val($btn.data('name') || '');
    $('#storesTaxGroupRate').val($btn.data('rate') !== '' ? $btn.data('rate') : '');
    $('#storesTaxGroupModal').modal('show');
  });
});
</script>
@endpush
