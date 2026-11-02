@php
  $defaultCurrency = $service->displayCurrency($hotel);
  if (! in_array($defaultCurrency, ['TZS', 'USD'], true)) {
      $defaultCurrency = 'TZS';
  }
  $confCurrencies = array_keys(config('hotel_pms_service_templates.conference_currencies', ['TZS' => '', 'USD' => '']));
  $confPricingUnits = config('hotel_pms_service_templates.conference_pricing_units', []);
  $categories = $service->serviceCategories->where('is_active', true)->values();
@endphp

<div class="sv-section border-top" id="conference-rates">
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
    <h4 class="sv-section-title mb-0"><i class="fa fa-building"></i> Conference rates &amp; packages</h4>
    <a href="{{ route('hotel.conference.index') }}" class="btn btn-sv" style="width:auto;padding:6px 16px;">
      <i class="fa fa-users"></i> Events Desk
    </a>
  </div>
  <p class="text-muted small mb-3">
    Define meeting rooms, catering, and AV packages. Price by <strong>day</strong>, <strong>half-day</strong>, <strong>delegate</strong>, or <strong>fixed</strong> (TZS / USD).
  </p>

  @if($categories->isEmpty())
    <p class="text-muted">Import categories below (Room Hire, Catering, AV Equipment), then add rate rows here.</p>
  @else
    <form method="POST" action="{{ route('hotel.services.conference-rates.update', $service) }}" id="svConferenceRatesForm">
      @csrf
      @method('PUT')

      <div class="mb-3">
        <button type="button" class="btn btn-sm btn-light border js-sv-conf-add-rate">
          <i class="fa fa-plus"></i> Add rate row
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead>
            <tr>
              <th>Category</th>
              <th style="min-width:160px;">Name / room</th>
              <th style="width:90px;">Capacity</th>
              <th style="min-width:130px;">Charge by</th>
              <th style="width:72px;">Curr.</th>
              <th style="width:110px;">Amount</th>
              <th style="width:52px;">On</th>
              <th style="width:44px;"></th>
            </tr>
          </thead>
          <tbody class="js-sv-conf-rates-body">
            @forelse($conferenceRateRows as $index => $rate)
              @include('hotel.pms.services.partials._conference-rate-row', [
                'index' => $index,
                'rate' => $rate,
                'categories' => $categories,
                'confCurrencies' => $confCurrencies,
                'confPricingUnits' => $confPricingUnits,
                'defaultCurrency' => $defaultCurrency,
              ])
            @empty
              <tr class="js-sv-conf-empty">
                <td colspan="8" class="text-muted text-center py-3">No rates yet — click Add rate row.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="text-right mt-3">
        <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;">
          <i class="fa fa-save"></i> Save conference rates
        </button>
      </div>
    </form>
  @endif
</div>

<template id="svConfRateRowTemplate">
  @include('hotel.pms.services.partials._conference-rate-row', [
    'isTemplate' => true,
    'index' => '__INDEX__',
    'rate' => null,
    'categories' => $categories,
    'confCurrencies' => $confCurrencies,
    'confPricingUnits' => $confPricingUnits,
    'defaultCurrency' => $defaultCurrency,
  ])
</template>

@push('scripts')
  <script>
    (function () {
      var tbody = document.querySelector('.js-sv-conf-rates-body');
      var template = document.getElementById('svConfRateRowTemplate');
      var addBtn = document.querySelector('.js-sv-conf-add-rate');
      var index = {{ max(1, ($conferenceRateRows ?? collect())->count()) }};

      function applyNames(row, i) {
        row.querySelectorAll('[name*="rates["]').forEach(function (el) {
          el.name = el.name.replace(/rates\[\d+\]/, 'rates[' + i + ']').replace('rates[__INDEX__]', 'rates[' + i + ']');
        });
      }

      if (addBtn && template && tbody) {
        addBtn.addEventListener('click', function () {
          var empty = tbody.querySelector('.js-sv-conf-empty');
          if (empty) empty.remove();
          var clone = template.content.cloneNode(true);
          var row = clone.querySelector('tr');
          applyNames(row, index++);
          tbody.appendChild(clone);
        });

        tbody.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-sv-conf-remove-rate');
          if (!btn) return;
          var rows = tbody.querySelectorAll('.js-sv-conf-rate-row');
          if (rows.length <= 1) return;
          btn.closest('tr').remove();
        });
      }
    })();
  </script>
@endpush
