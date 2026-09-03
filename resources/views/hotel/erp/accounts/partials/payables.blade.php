<div class="ac-toolbar">
  <a href="{{ route('hotel.accounts.purchase-orders.create') }}" class="btn btn-ac-outline">Create Purchase Order</a>
  <button type="button" class="btn btn-ac-outline" data-toggle="modal" data-target="#acAddVendorModal">Add Vendor</button>
  <form method="GET" action="{{ route('hotel.accounts.index') }}" class="ac-search d-flex">
    <input type="hidden" name="tab" value="payables">
    <input class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search...">
  </form>
</div>

<div class="ac-content">
  @if($payables && $payables->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-bordered table-hover ac-table mb-0">
        <thead>
          <tr>
            @foreach($ui['payables']['columns'] ?? [] as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($payables as $po)
            <tr>
              <td>{{ $po->po_number }}</td>
              <td>{{ $po->vendor?->name ?: '—' }}</td>
              <td>{{ $po->dateLabel() }}</td>
              <td>{{ number_format((float) $po->pre_tax, 2) }}</td>
              <td>{{ number_format((float) $po->tax, 2) }}</td>
              <td>{{ number_format((float) $po->total, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($payables->hasPages())
      <div class="mt-3">{{ $payables->links() }}</div>
    @endif
  @else
    <div class="ac-empty">No data found</div>
  @endif

  @if($vendors && $vendors->isNotEmpty())
    <h5 class="mt-4 mb-3">Vendors</h5>
    <div class="table-responsive">
      <table class="table table-bordered table-hover ac-table mb-0">
        <thead>
          <tr>
            @foreach($ui['payables']['vendor_columns'] ?? [] as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($vendors as $vendor)
            <tr>
              <td>{{ $vendor->name }}</td>
              <td>{{ $vendor->contact_person ?: '—' }}</td>
              <td>{{ $vendor->gst_num ?: '—' }}</td>
              <td>{{ $vendor->phone ?: '—' }}</td>
              <td>{{ $vendor->state ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
