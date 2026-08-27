@php
  $roomCount = $hotel->rooms->count();
  $maxRooms = $hotel->maxRooms();
  $canAddRoom = $hotel->canAddRoom();
  $roomsLimitLabel = $hotel->plan?->roomsLimitLabel() ?? '—';
@endphp

<form action="{{ route('hotel.settings.update') }}" method="POST" id="roomsSettingsForm">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="rooms">

  @error('rooms')
    <div class="alert alert-danger py-2 small">{{ $message }}</div>
  @enderror

  <p class="text-muted small mb-3">
    Bulk edit. For quick add use <a href="{{ route('hotel.rooms.index') }}">Rooms</a>.
  </p>

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
      <h4 class="settings-section-title mb-1">Room Types</h4>
      <p class="text-muted small mb-0">
        {{ $roomCount }} room {{ $roomCount === 1 ? 'type' : 'types' }} configured
        @if($maxRooms > 0)
          · Plan limit: {{ $roomCount }} / {{ $maxRooms }}
        @else
          · {{ $roomsLimitLabel }}
        @endif
      </p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="addRoomBtn" @disabled(! $canAddRoom)>
      <i class="fa fa-plus"></i> Add Room
    </button>
  </div>

  @if(! $canAddRoom)
    <div class="alert alert-warning py-2 small">
      You have reached the maximum number of room types allowed on your plan ({{ $maxRooms }}).
    </div>
  @endif

  <div class="table-responsive">
    <table class="table table-bordered settings-table mb-0" id="roomsTable">
      <thead>
        <tr>
          <th>Enable</th>
          <th>Rank</th>
          <th>Name</th>
          <th>Description</th>
          <th>Rooms</th>
          <th>Occupancy</th>
          <th style="width:52px"></th>
        </tr>
      </thead>
      @forelse($hotel->rooms as $index => $room)
        @include('hotel.settings.partials._room-row', ['index' => $index, 'room' => $room])
      @empty
        <tbody id="roomsEmptyState">
          <tr>
            <td colspan="7" class="text-muted text-center py-4">No room types yet. Click <strong>Add Room</strong> to create one.</td>
          </tr>
        </tbody>
      @endforelse
    </table>
  </div>

  <template id="newRoomRowTemplate">
    @include('hotel.settings.partials._room-row', ['index' => '__INDEX__', 'room' => null, 'isNew' => true])
  </template>

  <div class="settings-save-bar">
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Rooms</button>
  </div>
</form>

@push('styles')
  <style>
    .room-type-group.is-deleted { display: none; }
    .room-type-row--new td { background: #f8fbff; }
    #addRoomBtn:disabled { cursor: not-allowed; opacity: 0.65; }
  </style>
@endpush

@push('scripts')
  <script>
    jQuery(function ($) {
      var nextIndex = {{ max($roomCount, 0) }};
      var maxRooms = {{ $maxRooms }};
      var currentCount = {{ $roomCount }};
      var pendingNew = 0;

      function visibleRoomGroups() {
        return $('#roomsTable .room-type-group:not(.is-deleted)').length;
      }

      function canAddMore() {
        if (maxRooms === 0) return true;
        return (currentCount + pendingNew) < maxRooms;
      }

      function updateAddButton() {
        $('#addRoomBtn').prop('disabled', !canAddMore());
      }

      function hideEmptyState() {
        $('#roomsEmptyState').remove();
      }

      $('#addRoomBtn').on('click', function () {
        if (!canAddMore()) return;

        hideEmptyState();

        var html = $('#newRoomRowTemplate').html().replace(/__INDEX__/g, String(nextIndex));
        $('#roomsTable').append(html);
        nextIndex++;
        pendingNew++;
        updateAddButton();

        var $lastGroup = $('#roomsTable .room-type-group').last();
        $lastGroup.find('.room-name-input').trigger('focus');
      });

      $(document).on('click', '.room-remove-btn', function () {
        var $group = $(this).closest('.room-type-group');
        var isNew = $group.find('.room-type-row--new').length > 0;

        if (isNew) {
          $group.remove();
          pendingNew = Math.max(0, pendingNew - 1);
        } else {
          $group.addClass('is-deleted');
          $group.find('.room-delete-flag').val('1');
          $group.find(':input:not(.room-delete-flag)').prop('disabled', true);
        }

        updateAddButton();

        if (visibleRoomGroups() === 0 && $('#roomsEmptyState').length === 0) {
          $('#roomsTable').append(
            '<tbody id="roomsEmptyState"><tr><td colspan="7" class="text-muted text-center py-4">No room types yet. Click <strong>Add Room</strong> to create one.</td></tr></tbody>'
          );
        }
      });

      $(document).on('input', '.room-name-input', function () {
        var $group = $(this).closest('.room-type-group');
        var $display = $group.find('.room-display-name-input');
        if (!$display.val()) {
          $display.attr('placeholder', $(this).val() || 'Shown to guests');
        }
      });

      updateAddButton();
    });
  </script>
@endpush
