<div class="ac-toolbar">
  <button type="button" class="btn btn-ac-outline" data-toggle="modal" data-target="#acAddCompanyModal">Add Company</button>
  <form method="GET" action="{{ route('hotel.accounts.index') }}" class="ac-search d-flex">
    <input type="hidden" name="tab" value="receivables">
    <input class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search...">
  </form>
</div>

<div class="ac-content">
  @if($receivables && $receivables->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-bordered table-hover ac-table mb-0">
        <thead>
          <tr>
            @foreach($ui['receivables']['columns'] ?? [] as $column)
              <th>{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($receivables as $company)
            @php $stats = $billingStats[$company->id] ?? null; @endphp
            <tr>
              <td>{{ $company->name }}</td>
              <td>{{ $company->contact_person ?: '—' }}</td>
              <td>{{ $company->gst_vat ?: '—' }}</td>
              <td>{{ $stats ? $companyData->moneyLabel($stats['billed'], $stats['currency']) : '—' }}</td>
              <td>{{ $stats ? $companyData->moneyLabel($stats['payments'], $stats['currency']) : '—' }}</td>
              <td>
                @if($stats && ($stats['outstanding'] ?? 0) > 0)
                  <strong>{{ $companyData->moneyLabel($stats['outstanding'], $stats['currency']) }}</strong>
                @elseif($stats)
                  {{ $companyData->moneyLabel(0, $stats['currency']) }}
                @else
                  —
                @endif
              </td>
              <td>
                @if($stats && ($stats['invoices'] ?? 0) > 0)
                  <a href="{{ route('hotel.reservations.index') }}?search={{ urlencode($company->name) }}">{{ $stats['invoices'] }} booking(s)</a>
                @else
                  —
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($receivables->hasPages())
      <div class="mt-3">{{ $receivables->links() }}</div>
    @endif
  @else
    <div class="ac-empty">No data found</div>
  @endif
</div>
