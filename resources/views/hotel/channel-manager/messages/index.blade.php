@extends('layouts.app')

@section('title', 'Messages')

@push('styles')
  <style>
    .cm-side-panel {
      border-top: 3px solid #940000;
      padding: 20px;
      background: #fafafa;
      height: 100%;
    }
    .cm-side-panel .control-label {
      font-weight: 700;
      margin-bottom: 6px;
    }
    .cm-channel-list label {
      display: block;
      font-weight: 500;
      margin-bottom: 6px;
    }
    .cm-msg-table thead th {
      background: #5a5a5a;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      border-color: #4a4a4a;
      white-space: nowrap;
    }
    .cm-msg-table tbody td {
      font-size: 13px;
      vertical-align: top;
    }
    .cm-msg-preview {
      max-width: 420px;
      white-space: normal;
    }
    .cm-direction-inbound { color: #2563eb; font-weight: 600; }
    .cm-direction-outbound { color: #940000; font-weight: 600; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-envelope-o"></i> OTAs <small class="text-muted">Messages</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">OTAs</a></li>
      <li class="breadcrumb-item">Messages</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-lg-3 col-md-4">
      <div class="tile cm-side-panel mb-4">
        <form method="POST" action="{{ route('hotel.channel-manager.messages.sync') }}" id="cmSyncMessagesForm">
          @csrf
          <button type="submit" class="btn btn-primary btn-block mb-3">Sync Messages</button>

          <div class="form-group">
            <label class="control-label">From Month:</label>
            <input class="form-control" type="date" name="start_date" value="{{ $filters['start_date'] }}" required>
          </div>

          <div class="form-group">
            <label class="control-label">To Month:</label>
            <input class="form-control" type="date" name="end_date" value="{{ $filters['end_date'] }}" required>
          </div>

          <label class="control-label d-block">Channel:</label>
          <div class="cm-channel-list">
            @forelse($channels as $channel)
              <label>
                <input type="checkbox" name="channels[]" value="{{ $channel['value'] }}"
                  {{ in_array($channel['value'], $filters['channels'], true) ? 'checked' : '' }}>
                {{ $channel['label'] }}
              </label>
            @empty
              <p class="text-muted small mb-0">
                No channels configured.
                <a href="{{ route('hotel.channel-manager.ota-mapping') }}">Set up OTAs in Mapping Setup</a>
                or connect WhatsApp in Settings.
              </p>
            @endforelse
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-9 col-md-8">
      <div class="tile">
        <form method="GET" action="{{ route('hotel.channel-manager.messages.index') }}" class="row align-items-end mb-3">
          @foreach($filters['channels'] as $channel)
            <input type="hidden" name="channels[]" value="{{ $channel }}">
          @endforeach
          <input type="hidden" name="start_date" value="{{ $filters['start_date'] }}">
          <input type="hidden" name="end_date" value="{{ $filters['end_date'] }}">
          <div class="col-md-8">
            <label class="control-label">Search</label>
            <input class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Guest, booking ID, message...">
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-secondary btn-block">Filter</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-hover table-bordered cm-msg-table mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Channel</th>
                <th>Booking ID</th>
                <th>Guest</th>
                <th>Direction</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              @forelse($messages as $message)
                <tr>
                  <td>{{ $message->sentAtLabel() }}</td>
                  <td>{{ $message->channelLabel() }}</td>
                  <td>{{ $message->booking_id ?: '—' }}</td>
                  <td>{{ $message->guest_name ?: '—' }}</td>
                  <td>
                    <span class="{{ $message->direction === 'outbound' ? 'cm-direction-outbound' : 'cm-direction-inbound' }}">
                      {{ ucfirst($message->direction) }}
                    </span>
                  </td>
                  <td class="cm-msg-preview">
                    @if($message->subject)
                      <strong>{{ $message->subject }}</strong><br>
                    @endif
                    {{ $message->preview() }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-4 font-weight-bold">No messages yet. Use Sync Messages to pull from Channel Manager.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($messages->hasPages())
          <div class="mt-3">{{ $messages->links() }}</div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('cmSyncMessagesForm');
      if (!form) return;
      form.addEventListener('submit', function (e) {
        var start = form.querySelector('[name=start_date]').value;
        var end = form.querySelector('[name=end_date]').value;
        if (start && end && start > end) {
          e.preventDefault();
          if (typeof swal === 'function') {
            swal('Invalid dates', 'Start date cannot be after end date.', 'warning');
          }
        }
      });
    })();
  </script>
@endpush
