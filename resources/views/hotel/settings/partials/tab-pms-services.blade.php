<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="pms-services">

  <h4 class="settings-section-title">PMS Services</h4>
  <div class="table-responsive">
    <table class="table table-bordered settings-table">
      <thead>
        <tr>
          <th>Sr.</th><th>Service Name</th><th>Amount</th><th>Tax Category</th><th>HSN Code</th>
          <th>Tax Inclusive</th><th>Visible on BE</th><th>Amount Editable</th><th>Comments</th>
        </tr>
      </thead>
      <tbody>
        @foreach($hotel->pmsServices as $index => $svc)
          <tr>
            <td>{{ $index + 1 }}<input type="hidden" name="services[{{ $index }}][id]" value="{{ $svc->id }}"></td>
            <td><input class="form-control form-control-sm" name="services[{{ $index }}][name]" value="{{ $svc->name }}"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" name="services[{{ $index }}][amount]" value="{{ $svc->amount }}"></td>
            <td><input class="form-control form-control-sm" name="services[{{ $index }}][tax_category]" value="{{ $svc->tax_category }}"></td>
            <td><input class="form-control form-control-sm" name="services[{{ $index }}][hsn_code]" value="{{ $svc->hsn_code }}"></td>
            <td><input type="checkbox" name="services[{{ $index }}][tax_inclusive]" value="1" {{ $svc->tax_inclusive ? 'checked' : '' }}></td>
            <td><input type="checkbox" name="services[{{ $index }}][visible_on_be]" value="1" {{ $svc->visible_on_be ? 'checked' : '' }}></td>
            <td><input type="checkbox" name="services[{{ $index }}][amount_editable]" value="1" {{ $svc->amount_editable ? 'checked' : '' }}></td>
            <td><input class="form-control form-control-sm" name="services[{{ $index }}][comments]" value="{{ $svc->comments }}"></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save PMS Services</button>
  </div>
</form>
