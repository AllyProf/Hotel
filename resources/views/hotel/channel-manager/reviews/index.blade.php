@extends('layouts.app')

@section('title', 'Reviews')

@push('styles')
  <style>
    .cm-side-panel {
      border-top: 3px solid #940000;
      padding: 20px;
      background: #fafafa;
    }
    .cm-side-panel .control-label { font-weight: 700; margin-bottom: 6px; }
    .cm-channel-list label { display: block; font-weight: 500; margin-bottom: 6px; }
    .cm-review-table thead th {
      background: #5a5a5a;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      border-color: #4a4a4a;
      white-space: nowrap;
    }
    .cm-review-stars { color: #f59e0b; letter-spacing: 1px; }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-star"></i> OTAs <small class="text-muted">Reviews</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">OTAs</a></li>
      <li class="breadcrumb-item">Reviews</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-lg-3 col-md-4">
      <div class="tile cm-side-panel mb-4">
        <form method="POST" action="{{ route('hotel.channel-manager.reviews.sync') }}" id="cmSyncReviewsForm">
          @csrf
          <button type="submit" class="btn btn-primary btn-block mb-3">Sync Reviews</button>

          <div class="form-group">
            <label class="control-label">From:</label>
            <input class="form-control" type="date" name="start_date" value="{{ $filters['start_date'] }}" required>
          </div>

          <div class="form-group">
            <label class="control-label">To:</label>
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
        <form method="GET" action="{{ route('hotel.channel-manager.reviews.index') }}" class="row align-items-end mb-3">
          @foreach($filters['channels'] as $channel)
            <input type="hidden" name="channels[]" value="{{ $channel }}">
          @endforeach
          <input type="hidden" name="start_date" value="{{ $filters['start_date'] }}">
          <input type="hidden" name="end_date" value="{{ $filters['end_date'] }}">
          <div class="col-md-8">
            <label class="control-label">Search</label>
            <input class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Guest, booking ID, review text...">
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-secondary btn-block">Filter</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-hover table-bordered cm-review-table mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Channel</th>
                <th>Rating</th>
                <th>Guest</th>
                <th>Booking ID</th>
                <th>Review</th>
                <th>Response</th>
              </tr>
            </thead>
            <tbody>
              @forelse($reviews as $review)
                <tr>
                  <td>{{ $review->reviewDateLabel() }}</td>
                  <td>{{ $review->channelLabel() }}</td>
                  <td><span class="cm-review-stars">{{ $review->ratingStars() }}</span></td>
                  <td>{{ $review->guest_name ?: '—' }}</td>
                  <td>{{ $review->booking_id ?: '—' }}</td>
                  <td>
                    @if($review->title)
                      <strong>{{ $review->title }}</strong><br>
                    @endif
                    {{ $review->body ?: '—' }}
                  </td>
                  <td>{{ $review->response ?: '—' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 font-weight-bold">No reviews yet. Use Sync Reviews to pull from Channel Manager.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($reviews->hasPages())
          <div class="mt-3">{{ $reviews->links() }}</div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('cmSyncReviewsForm');
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
