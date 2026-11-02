@php
  $storesTemplate = $storesTemplate ?? false;
@endphp
<div class="{{ $storesTemplate ? 'stores-table-wrap table-responsive' : 'table-responsive' }}">
  <table class="table table-bordered mb-0 {{ $storesTemplate ? 'stores-po-list-table table-hover' : '' }}">
    <thead>
      <tr>
        <th>#</th>
        <th>Item</th>
        <th class="text-right">Qty</th>
        <th>Unit</th>
        <th class="text-right">Expected price</th>
        <th class="text-right">Line total</th>
        <th>Notes</th>
      </tr>
    </thead>
    <tbody>
      @foreach($purchaseList->lines as $index => $line)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td>{{ $line->item_name }}</td>
          <td class="text-right">{{ (int) max(1, round((float) $line->quantity)) }}</td>
          <td>{{ $line->unit ?: '—' }}</td>
          <td class="text-right">
            @if($line->unit_cost !== null)
              {{ number_format((float) $line->unit_cost, 2) }}
            @else
              —
            @endif
          </td>
          <td class="text-right">
            @if($line->line_budget !== null)
              {{ number_format((float) $line->line_budget, 2) }}
            @else
              —
            @endif
          </td>
          <td>{{ $line->line_notes ?: '—' }}</td>
        </tr>
      @endforeach
    </tbody>
    @if($purchaseList->budget_total !== null)
      <tfoot>
        <tr>
          <th colspan="5" class="text-right">Budget total ({{ $currency }})</th>
          <th class="text-right">{{ number_format((float) $purchaseList->budget_total, 2) }}</th>
          <th></th>
        </tr>
      </tfoot>
    @endif
  </table>
</div>
