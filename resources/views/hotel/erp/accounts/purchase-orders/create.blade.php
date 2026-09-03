@extends('layouts.app')

@section('title', 'Create PO')

@push('styles')
  <style>
    :root { --po-brand: #940000; }
    .po-page { background: #fff; padding: 20px; }
    .po-title { font-size: 22px; font-weight: 400; margin-bottom: 20px; }
    .po-entry-row {
      display: grid;
      grid-template-columns: 2fr 0.7fr 0.9fr 0.9fr 0.8fr 0.9fr auto;
      gap: 10px;
      align-items: end;
      margin-bottom: 14px;
    }
    .po-entry-row label { font-size: 12px; font-weight: 700; margin-bottom: 4px; display: block; }
    .po-entry-row .form-control { font-size: 13px; min-height: 36px; }
    .po-calc { background: #f3f4f6; }
    .po-table thead th {
      background: #2f3640;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 10px;
      white-space: nowrap;
    }
    .po-table tbody td, .po-table tfoot td {
      font-size: 13px;
      padding: 10px;
      border-color: #dee2e6;
    }
    .po-table tfoot td { background: #6c757d; color: #fff; font-weight: 700; }
    .btn-po { background: var(--po-brand) !important; border-color: var(--po-brand) !important; color: #fff !important; }
    .btn-po[disabled] { opacity: 0.5; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-calculator"></i> Accounts <small class="text-muted">Create PO</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('hotel.accounts.index', ['tab' => 'payables']) }}">Accounts</a></li>
      <li class="breadcrumb-item">Create PO</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile po-page">
        <h3 class="po-title">Create PO</h3>

        <form method="POST" action="{{ route('hotel.accounts.purchase-orders.store') }}" enctype="multipart/form-data" id="poForm">
          @csrf

          <div class="row mb-4">
            <div class="col-md-4">
              <label class="control-label">Vendor</label>
              <select class="form-control select2-vendor" name="hotel_vendor_id" required>
                <option value="">Select vendor</option>
                @foreach($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
              @if($vendors->isEmpty())
                <small class="text-muted">Add a vendor first from Accounts → payables.</small>
              @endif
            </div>
            <div class="col-md-4">
              <label class="control-label">Image</label>
              <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.pdf">
            </div>
          </div>

          <div class="po-entry-row">
            <div>
              <label>Select Item</label>
              <input type="text" class="form-control js-po-name" placeholder="Item name">
            </div>
            <div>
              <label>Quantity</label>
              <input type="number" step="0.01" min="0" class="form-control js-po-qty" value="0">
            </div>
            <div>
              <label>Rate</label>
              <input type="number" step="0.01" min="0" class="form-control js-po-rate" value="">
            </div>
            <div>
              <label>Pre tax</label>
              <input type="text" class="form-control po-calc js-po-pretax" readonly value="0.00">
            </div>
            <div>
              <label>Tax</label>
              <input type="number" step="0.01" min="0" class="form-control js-po-tax" value="0">
            </div>
            <div>
              <label>Total</label>
              <input type="text" class="form-control po-calc js-po-total" readonly value="0.00">
            </div>
            <div>
              <label>&nbsp;</label>
              <button type="button" class="btn btn-outline-primary btn-block js-po-add">Add item</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered po-table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Qty</th>
                  <th>Rate</th>
                  <th>Pre tax</th>
                  <th>Tax</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="poItemsBody"></tbody>
              <tfoot>
                <tr>
                  <td>Total</td>
                  <td id="poSumQty">0</td>
                  <td></td>
                  <td id="poSumPreTax">0.00</td>
                  <td id="poSumTax">0.00</td>
                  <td id="poSumTotal">0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="text-right mt-4">
            <a href="{{ route('hotel.accounts.index', ['tab' => 'payables']) }}" class="btn btn-outline-danger">Cancel</a>
            <button type="submit" class="btn btn-po" id="poSubmit" disabled>Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
  <script>
    (function () {
      if (window.jQuery && jQuery.fn.select2) {
        jQuery('.select2-vendor').select2({ width: '100%', placeholder: 'Select vendor' });
      }

      var items = [];
      var idx = 0;

      function money(v) { return (Math.round(v * 100) / 100).toFixed(2); }

      function recalcEntry() {
        var qty = parseFloat(document.querySelector('.js-po-qty').value) || 0;
        var rate = parseFloat(document.querySelector('.js-po-rate').value) || 0;
        var tax = parseFloat(document.querySelector('.js-po-tax').value) || 0;
        var pre = qty * rate;
        document.querySelector('.js-po-pretax').value = money(pre);
        document.querySelector('.js-po-total').value = money(pre + tax);
      }

      function render() {
        var body = document.getElementById('poItemsBody');
        body.innerHTML = '';
        var sumQty = 0, sumPre = 0, sumTax = 0, sumTotal = 0;

        items.forEach(function (item) {
          sumQty += item.quantity;
          sumPre += item.pre_tax;
          sumTax += item.tax;
          sumTotal += item.total;

          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td>' + item.name + '<input type="hidden" name="items[' + item.i + '][name]" value="' + item.name + '"></td>' +
            '<td>' + item.quantity + '<input type="hidden" name="items[' + item.i + '][quantity]" value="' + item.quantity + '"></td>' +
            '<td>' + money(item.rate) + '<input type="hidden" name="items[' + item.i + '][rate]" value="' + item.rate + '"></td>' +
            '<td>' + money(item.pre_tax) + '<input type="hidden" name="items[' + item.i + '][pre_tax]" value="' + item.pre_tax + '"></td>' +
            '<td>' + money(item.tax) + '<input type="hidden" name="items[' + item.i + '][tax]" value="' + item.tax + '"></td>' +
            '<td>' + money(item.total) + '<input type="hidden" name="items[' + item.i + '][total]" value="' + item.total + '"></td>' +
            '<td><button type="button" class="btn btn-sm btn-link text-danger js-po-remove" data-i="' + item.i + '">Remove</button></td>';
          body.appendChild(tr);
        });

        document.getElementById('poSumQty').textContent = sumQty;
        document.getElementById('poSumPreTax').textContent = money(sumPre);
        document.getElementById('poSumTax').textContent = money(sumTax);
        document.getElementById('poSumTotal').textContent = money(sumTotal);
        document.getElementById('poSubmit').disabled = items.length === 0;
      }

      document.querySelector('.js-po-qty').addEventListener('input', recalcEntry);
      document.querySelector('.js-po-rate').addEventListener('input', recalcEntry);
      document.querySelector('.js-po-tax').addEventListener('input', recalcEntry);

      document.querySelector('.js-po-add').addEventListener('click', function () {
        var name = document.querySelector('.js-po-name').value.trim();
        var qty = parseFloat(document.querySelector('.js-po-qty').value) || 0;
        var rate = parseFloat(document.querySelector('.js-po-rate').value) || 0;
        var tax = parseFloat(document.querySelector('.js-po-tax').value) || 0;
        if (!name || qty <= 0) return;
        var pre = qty * rate;
        items.push({ i: idx++, name: name, quantity: qty, rate: rate, pre_tax: pre, tax: tax, total: pre + tax });
        document.querySelector('.js-po-name').value = '';
        document.querySelector('.js-po-qty').value = '0';
        document.querySelector('.js-po-rate').value = '';
        document.querySelector('.js-po-tax').value = '0';
        recalcEntry();
        render();
      });

      document.getElementById('poItemsBody').addEventListener('click', function (e) {
        var btn = e.target.closest('.js-po-remove');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-i'), 10);
        items = items.filter(function (item) { return item.i !== id; });
        render();
      });
    })();
  </script>
@endpush
