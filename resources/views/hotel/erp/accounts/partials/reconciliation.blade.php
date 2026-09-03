<form method="GET" action="{{ route('hotel.accounts.index') }}" class="ac-filters">
  <input type="hidden" name="tab" value="reconciliation">
  <input type="hidden" name="submitted" value="1">
  <div>
    <label>From Date:</label>
    <input class="form-control" type="date" name="from_date" value="{{ $filters['from_date'] }}" required>
  </div>
  <div>
    <label>To Date:</label>
    <input class="form-control" type="date" name="to_date" value="{{ $filters['to_date'] }}" required>
  </div>
  <div>
    <label>Payment Mode:</label>
    <select class="form-control" name="payment_mode">
      @foreach($paymentModes as $value => $label)
        <option value="{{ $value }}" {{ $filters['payment_mode'] === $value ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label>&nbsp;</label>
    <button type="submit" class="btn btn-ac btn-block">Submit</button>
  </div>
</form>

<div class="ac-content">
  @if($filters['reconciliation_submitted'])
    <div class="table-responsive">
      <table class="table table-bordered ac-table mb-0">
        <thead>
          <tr>
            @foreach($ui['reconciliation']['columns'] ?? [] as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse(($reconciliation['rows'] ?? collect()) as $row)
            <tr>
              <td>{{ $row['date'] }}</td>
              <td>{{ $row['pos_name'] }}</td>
              <td>{{ $row['invoice_no'] }}</td>
              <td>{{ $row['party'] }}</td>
              <td>{{ $row['comments'] }}</td>
              <td>
                @if($row['image_url'])
                  <a href="{{ $row['image_url'] }}" target="_blank" rel="noopener">View</a>
                @else
                  —
                @endif
              </td>
              <td>{{ $row['user'] }}</td>
              <td>{{ $row['paid_in'] > 0 ? number_format($row['paid_in'], 2) : '0.00' }}</td>
              <td>{{ $row['paid_out'] > 0 ? number_format($row['paid_out'], 2) : '0.00' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="ac-empty">No data found</td>
            </tr>
          @endforelse
        </tbody>
        @if($reconciliation)
          <tfoot>
            <tr>
              <td colspan="7">Total</td>
              <td>{{ number_format($reconciliation['totals']['paid_in'], 2) }}</td>
              <td>{{ number_format($reconciliation['totals']['paid_out'], 2) }}</td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  @endif
</div>
