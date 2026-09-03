<form method="GET" action="{{ route('hotel.accounts.index') }}" class="ac-filters">
  <input type="hidden" name="tab" value="taxes">
  <input type="hidden" name="tax_generated" value="1">
  <div>
    <label>From Date:</label>
    <input class="form-control" type="date" name="from_date" value="{{ $filters['from_date'] }}" required>
  </div>
  <div>
    <label>To Date:</label>
    <input class="form-control" type="date" name="to_date" value="{{ $filters['to_date'] }}" required>
  </div>
  <div>
    <label>Type:</label>
    <select class="form-control" name="tax_type">
      @foreach($ui['taxes']['types'] ?? [] as $value => $label)
        <option value="{{ $value }}" {{ $filters['tax_type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label>&nbsp;</label>
    <button type="submit" class="btn btn-ac btn-block">Generate</button>
  </div>
  <div class="ml-auto">
    <label>&nbsp;</label>
    <button type="button" class="btn btn-ac btn-block js-ac-gstr1">GSTR1</button>
  </div>
</form>

<div class="ac-content">
  @if($filters['tax_generated'])
    <div class="table-responsive">
      <table class="table table-bordered ac-table mb-0">
        <thead>
          <tr>
            <th>Tax</th>
            <th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($taxRows as $row)
            <tr>
              <td>{{ $row['tax'] }}</td>
              <td class="text-right">{{ number_format($row['total'], 2) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="2" class="ac-empty">No data found</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endif
</div>

@push('scripts')
  <script>
    (function () {
      var btn = document.querySelector('.js-ac-gstr1');
      if (!btn || typeof swal !== 'function') return;
      btn.addEventListener('click', function () {
        swal('GSTR1', 'Generate the tax report first, then export GSTR1 from your accountant workflow.', 'info');
      });
    })();
  </script>
@endpush
