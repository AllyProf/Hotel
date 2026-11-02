@inject('storeItemService', 'App\Services\Stores\StoreMasterItemService')
@php
  $reportData = $reportData ?? ['total_value' => 0, 'groups' => []];
  $poFrom = $poFrom ?? now()->subDays(7)->format('Y-m-d');
  $poTo = $poTo ?? now()->format('Y-m-d');
  $reportType = $reportType ?? 'indent';
  $reportSort = $reportSort ?? 'department';
  $downloadUrl = route('hotel.stores.reports.download', array_filter([
    'report_type' => $reportType,
    'report_sort' => $reportSort,
    'po_from' => $poFrom,
    'po_to' => $poTo,
  ]));
@endphp

<form method="GET" action="{{ route('hotel.stores.index') }}" class="stores-toolbar stores-po-filter-bar stores-report-toolbar">
  <input type="hidden" name="tab" value="reports">
  <div class="stores-po-filter-row">
    <div class="stores-filter-fields stores-report-filter-fields">
      <div class="stores-filter-field">
        <label class="control-label" for="storesReportFrom">Start Date :</label>
        <input type="date" name="po_from" id="storesReportFrom" class="form-control" value="{{ $poFrom }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field">
        <label class="control-label" for="storesReportTo">End Date :</label>
        <input type="date" name="po_to" id="storesReportTo" class="form-control" value="{{ $poTo }}" onchange="this.form.submit()">
      </div>
      <div class="stores-filter-field stores-filter-field--report-type">
        <label class="control-label" for="storesReportType">Report Type :</label>
        <select name="report_type" id="storesReportType" class="form-control" onchange="this.form.submit()">
          @foreach($reportTypes ?? [] as $key => $label)
            <option value="{{ $key }}" @selected($reportType === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="stores-filter-field stores-filter-field--report-sort">
        <label class="control-label" for="storesReportSort">Sort By :</label>
        <select name="report_sort" id="storesReportSort" class="form-control" onchange="this.form.submit()">
          @foreach($reportSortOptions ?? [] as $key => $label)
            <option value="{{ $key }}" @selected($reportSort === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="stores-report-head-actions">
      <div class="stores-total-value stores-report-total mb-0">
        <strong>{{ $ui['total_value'] ?? 'Total Value' }}:</strong>
        {{ $storeItemService->formatMoney((float) $reportData['total_value'], $hotel) }}
      </div>
      <a href="{{ $downloadUrl }}" class="btn btn-primary btn-sm stores-report-download">
        Download
      </a>
    </div>
  </div>
</form>

@if($reportType === 'indent')
  <p class="px-3 text-muted small mb-2">
    Based on <strong>received</strong> indents (issue date in range). Values use indent line rates.
  </p>
@else
  <p class="px-3 text-muted small mb-2">
    Based on consumption records in range. Values use current master item rates.
  </p>
@endif

<div class="stores-table-wrap table-responsive stores-po-table-wrap px-3 pb-3">
  <table class="table table-bordered table-hover stores-po-list-table stores-report-table mb-0">
    <thead>
      <tr>
        <th>Name</th>
        <th>{{ $ui['category'] ?? 'Category' }}</th>
        <th>{{ $ui['sub_category'] ?? 'Sub Category' }}</th>
        <th class="text-right">Quantity</th>
        <th class="text-right">Value</th>
        <th style="width:40px"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($reportData['groups'] as $gi => $group)
        <tr class="stores-report-row stores-report-row--parent" data-report-group="{{ $gi }}">
          <td class="font-weight-bold">{{ $group['name'] }}</td>
          <td>{{ $group['category'] ?: '' }}</td>
          <td>{{ $group['sub_category'] ?: '' }}</td>
          <td class="text-right text-nowrap">{{ $group['quantity_label'] ?? '' }}</td>
          <td class="text-right text-nowrap">{{ $storeItemService->formatMoney((float) ($group['value'] ?? 0), $hotel) }}</td>
          <td class="text-center">
            @if(! empty($group['children']))
              <button type="button" class="btn btn-link btn-sm p-0 stores-report-toggle" aria-expanded="false" aria-controls="stores-report-children-{{ $gi }}">
                <i class="fa fa-chevron-down stores-report-chevron"></i>
              </button>
            @endif
          </td>
        </tr>
        @foreach($group['children'] ?? [] as $child)
          <tr class="stores-report-row stores-report-row--child stores-report-child-{{ $gi }} d-none">
            <td class="pl-4">{{ $child['name'] }}</td>
            <td>{{ $child['category'] ?: '' }}</td>
            <td>{{ $child['sub_category'] ?: '' }}</td>
            <td class="text-right text-nowrap">{{ $child['quantity_label'] ?? '' }}</td>
            <td class="text-right text-nowrap">{{ $storeItemService->formatMoney((float) ($child['value'] ?? 0), $hotel) }}</td>
            <td></td>
          </tr>
        @endforeach
      @empty
        <tr>
          <td colspan="6" class="text-center text-muted py-4">
            No data for this period. Try another date range or report type.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@push('scripts')
<script>
jQuery(function ($) {
  $(document).on('click', '.stores-report-toggle', function () {
    var $btn = $(this);
    var $row = $btn.closest('tr');
    var group = $row.data('report-group');
    var $children = $('.stores-report-child-' + group);
    var open = $btn.attr('aria-expanded') === 'true';
    $children.toggleClass('d-none', open);
    $btn.attr('aria-expanded', open ? 'false' : 'true');
    $btn.find('.stores-report-chevron').toggleClass('is-open', !open);
  });
});
</script>
@endpush
