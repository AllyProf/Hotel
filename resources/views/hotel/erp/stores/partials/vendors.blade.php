@inject('accountsService', 'App\Services\AccountsDataService')

<div class="stores-toolbar">
  <form method="GET" action="{{ route('hotel.stores.index') }}" class="stores-toolbar-form" data-no-loader>
    <input type="hidden" name="tab" value="settings">
    <input type="hidden" name="settings" value="vendor">

    <div class="stores-toolbar-row">
      <div class="stores-toolbar-actions">
        <button type="button" class="btn btn-stores-outline btn-stores-add-item js-stores-vendor-add" data-toggle="modal" data-target="#storesAddVendorModal">
          {{ $ui['add_vendor'] ?? 'Add Vendor' }}
        </button>
        <div class="stores-search-field">
          <label class="control-label stores-search-label" for="storesVendorSearch">{{ $ui['vendor_search_placeholder'] ?? 'Search vendor…' }}</label>
          <div class="input-group stores-search-input">
            <input type="search" name="vendor_search" id="storesVendorSearch" class="form-control"
              placeholder="{{ $ui['vendor_search_placeholder'] ?? 'Search vendor…' }}" value="{{ $vendorSearch }}">
            <div class="input-group-append">
              <button type="submit" class="btn btn-light border" aria-label="Search"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="stores-table-wrap table-responsive">
  <table class="table table-bordered table-hover stores-master-table mb-0">
    <thead>
      <tr>
        @foreach($vendorColumns as $col)
          <th>{{ $col['label'] }}</th>
        @endforeach
        <th style="width:72px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($vendors as $vendor)
        @php
          $phoneParts = $accountsService->splitVendorPhone($vendor->phone, $hotel->phone_country_code ?? '+255');
        @endphp
        <tr>
          @foreach($vendorColumns as $col)
            <td>{{ $vendor->{$col['key']} ?: '—' }}</td>
          @endforeach
          <td class="text-nowrap">
            <button type="button" class="stores-icon-btn text-primary js-stores-edit-vendor" title="Edit"
              data-id="{{ $vendor->id }}"
              data-name="{{ $vendor->name }}"
              data-gst-num="{{ $vendor->gst_num }}"
              data-address="{{ $vendor->address }}"
              data-email="{{ $vendor->email }}"
              data-account-no="{{ $vendor->account_no }}"
              data-contact-person="{{ $vendor->contact_person }}"
              data-phone-code="{{ $phoneParts['code'] }}"
              data-phone-local="{{ $phoneParts['local'] }}"
              data-state="{{ $vendor->state }}"
              data-bank-name="{{ $vendor->bank_name }}">
              <i class="fa fa-pencil"></i>
            </button>
            <form method="POST" action="{{ route('hotel.stores.vendors.destroy', $vendor) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="stores-icon-btn text-danger" title="Delete"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="{{ max(count($vendorColumns) + 1, 1) }}" class="text-muted text-center py-4">
            No vendors yet. Use <strong>Add Vendor</strong> to create one.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('hotel.erp.stores.partials.add-vendor-modal')

@push('scripts')
<script>
jQuery(function ($) {
  var storeUrl = @json(route('hotel.stores.vendors.store'));
  var updateUrlTemplate = @json(url('/hotel/stores/vendors/__ID__'));
  var addTitle = @json($ui['add_vendor'] ?? 'Add Vendor');
  var editTitle = @json($ui['edit_vendor'] ?? 'Edit Vendor');
  var defaultPhoneCode = @json($hotel->phone_country_code ?? '+255');
  var vendorModalMode = 'add';

  function setVendorFormAddMode() {
    $('#storesVendorForm').attr('action', storeUrl);
    $('#storesVendorMethod').val('');
    $('#storesVendorEditId').val('');
    $('#storesVendorModalTitle').text(addTitle);
  }

  function fillVendorForm(data) {
    $('#storesVendorForm').attr('action', updateUrlTemplate.replace('__ID__', data.id));
    $('#storesVendorMethod').val('PUT');
    $('#storesVendorEditId').val(String(data.id));
    $('#storesVendorModalTitle').text(editTitle);
    $('#storesVendorForm').find('[name="name"]').val(data.name || '');
    $('#storesVendorForm').find('[name="gst_num"]').val(data.gstNum || '');
    $('#storesVendorForm').find('[name="address"]').val(data.address || '');
    $('#storesVendorForm').find('[name="email"]').val(data.email || '');
    $('#storesVendorForm').find('[name="account_no"]').val(data.accountNo || '');
    $('#storesVendorForm').find('[name="contact_person"]').val(data.contactPerson || '');
    $('#storesVendorPhoneCode').val(data.phoneCode || defaultPhoneCode).trigger('change');
    $('#storesVendorPhone').val(data.phoneLocal || '');
    $('#storesVendorForm').find('[name="state"]').val(data.state || '');
    $('#storesVendorForm').find('[name="bank_name"]').val(data.bankName || '');
  }

  function resetVendorFormFields() {
    var form = document.getElementById('storesVendorForm');
    if (form) {
      form.reset();
    }
    $('#storesVendorPhoneCode').val(defaultPhoneCode).trigger('change');
  }

  function readVendorFromButton($btn) {
    return {
      id: $btn.data('id'),
      name: $btn.data('name') || '',
      gstNum: $btn.data('gst-num') || '',
      address: $btn.data('address') || '',
      email: $btn.data('email') || '',
      accountNo: $btn.data('account-no') || '',
      contactPerson: $btn.data('contact-person') || '',
      phoneCode: $btn.data('phone-code') || defaultPhoneCode,
      phoneLocal: $btn.data('phone-local') || '',
      state: $btn.data('state') || '',
      bankName: $btn.data('bank-name') || '',
    };
  }

  $('#storesAddVendorModal').on('show.bs.modal', function () {
    if (vendorModalMode === 'edit') {
      return;
    }
    setVendorFormAddMode();
    resetVendorFormFields();
  });

  $('#storesAddVendorModal').on('hidden.bs.modal', function () {
    vendorModalMode = 'add';
  });

  $('.js-stores-vendor-add').on('click', function () {
    vendorModalMode = 'add';
  });

  $(document).on('click', '.js-stores-edit-vendor', function (e) {
    e.preventDefault();
    var $btn = $(this).closest('.js-stores-edit-vendor');
    if (!$btn.data('id')) {
      return;
    }
    vendorModalMode = 'edit';
    fillVendorForm(readVendorFromButton($btn));
    $('#storesAddVendorModal').modal('show');
  });

  @if($errors->any())
    vendorModalMode = @json(old('stores_vendor_edit_id') ? 'edit' : 'add');
    $('#storesAddVendorModal').modal('show');
    @if(old('stores_vendor_edit_id'))
      fillVendorForm({
        id: @json(old('stores_vendor_edit_id')),
        name: @json(old('name')),
        gstNum: @json(old('gst_num')),
        address: @json(old('address')),
        email: @json(old('email')),
        accountNo: @json(old('account_no')),
        contactPerson: @json(old('contact_person')),
        phoneCode: @json(old('phone_country_code', $hotel->phone_country_code ?? '+255')),
        phoneLocal: @json(old('phone')),
        state: @json(old('state')),
        bankName: @json(old('bank_name')),
      });
    @else
      setVendorFormAddMode();
    @endif
  @endif
});
</script>
@endpush
