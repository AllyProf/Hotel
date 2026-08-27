@php
  $planCount = $hotel->ratePlans->count();
@endphp

<form action="{{ route('hotel.settings.update') }}" method="POST" id="rateplanSettingsForm">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="rateplan">

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="settings-section-title mb-0">Prices</h4>
    <button type="button" class="btn btn-primary btn-sm" id="addRateplanBtn" @disabled($hotel->rooms->isEmpty())>
      <i class="fa fa-plus"></i> Add Price
    </button>
  </div>

  @if($hotel->rooms->isEmpty())
    <div class="alert alert-warning py-2 small mb-3">
      Create a room type first under <a href="{{ route('hotel.rooms.index') }}">Rooms</a>.
    </div>
  @endif

  <div class="table-responsive">
    <table class="table table-bordered settings-table mb-0" id="rateplansTable">
      <thead>
        <tr>
          <th style="width:48px"></th>
          <th>Room</th>
          <th>Meals included</th>
          <th>Local price</th>
          <th>Local currency</th>
          <th>Intl price</th>
          <th>Intl currency</th>
        </tr>
      </thead>
      <tbody id="rateplansBody">
        @forelse($hotel->ratePlans as $index => $plan)
          @include('hotel.settings.partials._rateplan-row', [
            'index' => $index,
            'plan' => $plan,
            'rooms' => $hotel->rooms,
            'hotel' => $hotel,
          ])
        @empty
          <tr id="rateplansEmptyState">
            <td colspan="7" class="text-muted text-center py-4">No prices yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <template id="newRateplanRowTemplate">
    @include('hotel.settings.partials._rateplan-row', [
      'index' => '__INDEX__',
      'plan' => (object) [
        'hotel_room_id' => $hotel->rooms->first()?->id,
        'meal_plan' => 'EP',
        'base_rate' => 0,
        'local_base_rate' => null,
        'local_currency' => $hotel->currency ?? 'TZS',
        'international_currency' => 'USD',
      ],
      'rooms' => $hotel->rooms,
      'hotel' => $hotel,
    ])
  </template>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Prices</button>
  </div>
</form>

@include('partials._select2-init')

@push('styles')
  <style>
    .rateplan-row.is-deleted { display: none; }
    .rateplan-row--new td { background: #fff8f8; }
    #addRateplanBtn:disabled { cursor: not-allowed; opacity: 0.65; }
  </style>
@endpush

@push('scripts')
  <script>
    jQuery(function ($) {
      var nextIndex = {{ max($planCount, 0) }};

      function hideEmptyState() {
        $('#rateplansEmptyState').remove();
      }

      $('#addRateplanBtn').on('click', function () {
        hideEmptyState();
        var html = $('#newRateplanRowTemplate').html().replace(/__INDEX__/g, String(nextIndex++));
        $('#rateplansBody').append(html);
      });

      $(document).on('click', '.rateplan-remove-row', function () {
        var $row = $(this).closest('.rateplan-row');
        if ($row.hasClass('rateplan-row--new')) {
          $row.remove();
        } else {
          $row.addClass('is-deleted');
          $row.find('.rateplan-delete-flag').val('1');
          $row.find(':input:not(.rateplan-delete-flag)').prop('disabled', true);
        }

        if ($('.rateplan-row:not(.is-deleted)').length === 0 && $('#rateplansEmptyState').length === 0) {
          $('#rateplansBody').append('<tr id="rateplansEmptyState"><td colspan="7" class="text-muted text-center py-4">No prices yet.</td></tr>');
        }
      });
    });
  </script>
@endpush
