@php
  $isPlan = ($mode ?? 'plan') === 'plan';
  $checkGroup = $isPlan ? 'plan' : 'room';
  $namePrefix = $isPlan ? 'plan' : 'room';
  $firstCol = $isPlan ? 'Enable Rateplan' : 'Enable Room';
  $secondCol = $isPlan ? 'Rateplans' : 'Room';
  $fieldPrefix = $isPlan ? 'selected_plans' : 'selected_rooms';
@endphp

<div class="bulk-table-wrap">
  <table class="bulk-table bulk-restr-table">
    <thead>
      <tr>
        <th class="col-check"><input type="checkbox" class="js-bulk-check-all" data-target="{{ $checkGroup }}"></th>
        <th>{{ $secondCol }}</th>
        <th>Stop Sell <span class="text-muted">NO | YES</span></th>
        <th>Close On Arrival</th>
        <th>Close On Departure</th>
        <th>Minimum Stay</th>
        <th>Minimum Stay Arrival</th>
        <th>Maximum Stay</th>
        <th>Maximum Stay Arrival</th>
        <th>Exact Stay Arrival</th>
        <th>Minimum Advance Reservation</th>
        <th>Maximum Advance Reservation</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $item)
        @php $id = is_array($item) ? $item['id'] : $item->id; $label = is_array($item) ? $item['label'] : $item->name; @endphp
        <tr>
          <td class="col-check">
            <input type="checkbox" name="{{ $fieldPrefix }}[]" value="{{ $id }}" class="js-bulk-row-check" data-group="{{ $checkGroup }}">
          </td>
          <td>{{ $label }}</td>
          <td>
            <div class="bulk-stop-toggle">
              <span>NO</span>
              <label class="bulk-stop-switch mb-0">
                <input type="checkbox" name="restrictions[{{ $namePrefix }}_{{ $id }}][stop_sell]" value="1">
                <span class="bulk-stop-track"></span>
              </label>
              <span>YES</span>
            </div>
          </td>
          <td><input type="checkbox" name="restrictions[{{ $namePrefix }}_{{ $id }}][close_on_arrival]" value="1"></td>
          <td><input type="checkbox" name="restrictions[{{ $namePrefix }}_{{ $id }}][close_on_departure]" value="1"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][min_stay]" value="1"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][min_stay_arrival]"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][max_stay]"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][max_stay_arrival]"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][exact_stay_arrival]"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][min_advance]"></td>
          <td><input type="number" min="0" name="restrictions[{{ $namePrefix }}_{{ $id }}][max_advance]"></td>
        </tr>
      @empty
        <tr><td colspan="12">No items configured.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
