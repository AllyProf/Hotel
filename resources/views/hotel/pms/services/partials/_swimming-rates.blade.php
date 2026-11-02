@php
  $defaultCurrency = $service->displayCurrency($hotel);
  if (! in_array($defaultCurrency, ['TZS', 'USD'], true)) {
      $defaultCurrency = 'TZS';
  }
  $swimCurrencies = array_keys(config('hotel_pms_service_templates.swimming_currencies', ['TZS' => '', 'USD' => '']));
  $swimPricingUnits = config('hotel_pms_service_templates.swimming_pricing_units', []);
  $swimAudiences = config('hotel_pms_service_templates.swimming_audiences', []);
  $categories = $service->serviceCategories->where('is_active', true)->values();
@endphp

<div class="sv-section border-top" id="swimming-rates">
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
    <h4 class="sv-section-title mb-0"><i class="fa fa-ticket"></i> Pool rate plans</h4>
    <a href="{{ route('hotel.swimming.index') }}" class="btn btn-sv" style="width:auto;padding:6px 16px;">
      <i class="fa fa-tint"></i> Pool Desk
    </a>
  </div>
  <p class="text-muted small mb-3">
    Build any combination: <strong>Day pass / membership</strong>, <strong>adult vs child</strong>, priced
    <strong>per person, hour, day</strong>, or flat. Currency: <strong>TZS</strong> or <strong>USD</strong> only.
  </p>

  @if($categories->isEmpty())
    <p class="text-muted">Import pass categories below (Day Pass, Membership, …), then add rate rows here.</p>
  @else
    <form method="POST" action="{{ route('hotel.services.swimming-rates.update', $service) }}" id="svSwimmingRatesForm">
      @csrf
      @method('PUT')

      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-light border js-sv-swim-add-rate">
          <i class="fa fa-plus"></i> Add rate row
        </button>
        <span class="text-muted small">Example: Day Pass · Child · Per person · 15,000 TZS</span>
      </div>

      <div class="table-responsive sv-swim-rates-table">
        <table class="table table-sm table-bordered mb-0">
          <thead>
            <tr>
              <th style="min-width:140px;">Pass type</th>
              <th style="min-width:120px;">Guest type</th>
              <th style="min-width:130px;">Charge by</th>
              <th style="min-width:140px;">Label (optional)</th>
              <th style="width:72px;">Curr.</th>
              <th style="width:110px;">Amount</th>
              <th style="width:52px;">On</th>
              <th style="width:44px;"></th>
            </tr>
          </thead>
          <tbody class="js-sv-swim-rates-body">
            @forelse($swimmingRateRows as $index => $rate)
              @include('hotel.pms.services.partials._swimming-rate-row', [
                'index' => $index,
                'rate' => $rate,
                'categories' => $categories,
                'swimCurrencies' => $swimCurrencies,
                'swimPricingUnits' => $swimPricingUnits,
                'swimAudiences' => $swimAudiences,
                'defaultCurrency' => $defaultCurrency,
              ])
            @empty
              <tr class="js-sv-swim-empty">
                <td colspan="8" class="text-muted text-center py-3">No rates yet — click Add rate row.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="text-right mt-3">
        <button type="submit" class="btn btn-sv" style="width:auto;padding:8px 22px;">
          <i class="fa fa-save"></i> Save rate plans
        </button>
      </div>
    </form>
  @endif
</div>

<template id="svSwimRateRowTemplate">
  @include('hotel.pms.services.partials._swimming-rate-row', [
    'isTemplate' => true,
    'index' => '__INDEX__',
    'rate' => null,
    'categories' => $categories,
    'swimCurrencies' => $swimCurrencies,
    'swimPricingUnits' => $swimPricingUnits,
    'swimAudiences' => $swimAudiences,
    'defaultCurrency' => $defaultCurrency,
  ])
</template>

@push('styles')
  <style>
    .sv-swim-rates-table .form-control-sm {
      font-size: 12px;
      padding: 4px 8px;
      height: auto;
      min-height: 32px;
    }

    .sv-swim-rates-table select.form-control-sm {
      padding-right: 24px;
    }

    .sv-swim-rates-table thead th {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      background: #fafafa;
      white-space: nowrap;
    }
  </style>
@endpush

@push('scripts')
  <script>
    (function () {
      var tbody = document.querySelector('.js-sv-swim-rates-body');
      var template = document.getElementById('svSwimRateRowTemplate');
      var addBtn = document.querySelector('.js-sv-swim-add-rate');
      if (!tbody || !template || !addBtn) return;

      function reindexRows() {
        var rows = tbody.querySelectorAll('.js-sv-swim-rate-row');
        rows.forEach(function (row, index) {
          row.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/rates\[[^\]]+\]/, 'rates[' + index + ']');
          });
        });
        var empty = tbody.querySelector('.js-sv-swim-empty');
        if (empty) empty.style.display = rows.length ? 'none' : '';
      }

      addBtn.addEventListener('click', function () {
        var empty = tbody.querySelector('.js-sv-swim-empty');
        if (empty) empty.remove();
        var html = template.innerHTML.replace(/__INDEX__/g, String(tbody.querySelectorAll('.js-sv-swim-rate-row').length));
        tbody.insertAdjacentHTML('beforeend', html);
        reindexRows();
      });

      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-sv-swim-remove-rate');
        if (!btn) return;
        btn.closest('.js-sv-swim-rate-row')?.remove();
        if (!tbody.querySelector('.js-sv-swim-rate-row')) {
          tbody.innerHTML = '<tr class="js-sv-swim-empty"><td colspan="8" class="text-muted text-center py-3">No rates yet — click Add rate row.</td></tr>';
        }
        reindexRows();
      });

      reindexRows();
    })();
  </script>
@endpush
