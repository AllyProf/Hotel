@extends('layouts.booking-public')

@section('title', 'Book your stay')

@push('styles')
  <style>
    .book-page .app-title p {
      margin-bottom: 0;
    }

    .book-search label.control-label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .book-room__title {
      margin: 0 0 8px;
      font-size: 18px;
      font-weight: 600;
    }

    .book-room__price {
      font-size: 20px;
      font-weight: 700;
      color: #940000;
    }

    .book-room__actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 12px;
    }

    .book-room__photo {
      max-height: 200px;
      width: 100%;
      object-fit: cover;
      border-radius: 4px;
    }

    .book-room__photo-empty {
      min-height: 160px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f4f4f4;
      border: 1px dashed #ddd;
      border-radius: 4px;
      color: #888;
      font-size: 13px;
    }
  </style>
@endpush

@section('content')
  @php
    $location = trim(($hotel->city ?? '').($hotel->country ? ', '.$hotel->country : ''), ' ,');
  @endphp

  <div class="book-page">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-bed"></i> Book your stay</h1>
        @if($location !== '')
          <p>{{ $location }}</p>
        @endif
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="tile">
          <h3 class="tile-title">Search availability</h3>
          <div class="tile-body book-search">
            <form method="GET" action="{{ route('book.show', $bookingEngine['public_slug']) }}">
              <div class="row align-items-end">
                <div class="col-sm-6 col-md-3">
                  <div class="form-group mb-md-0">
                    <label class="control-label">Check-in</label>
                    <input type="date" name="checkin" class="form-control" value="{{ $checkin }}" required min="{{ now()->format('Y-m-d') }}">
                  </div>
                </div>
                <div class="col-sm-6 col-md-3">
                  <div class="form-group mb-md-0">
                    <label class="control-label">Check-out</label>
                    <input type="date" name="checkout" class="form-control" value="{{ $checkout }}" required>
                  </div>
                </div>
                <div class="col-sm-6 col-md-2">
                  <div class="form-group mb-md-0">
                    <label class="control-label">Rooms</label>
                    <input type="number" name="room_count" class="form-control" min="1" max="20" value="{{ $searchRoomCount }}" required>
                  </div>
                </div>
                <div class="col-sm-6 col-md-2">
                  <div class="form-group mb-md-0">
                    <label class="control-label">Guests</label>
                    <input type="number" name="guest_count" class="form-control" min="1" max="50" value="{{ $searchGuestCount }}" required>
                  </div>
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa fa-search"></i> Search
                  </button>
                </div>
              </div>
            </form>
            <p class="text-muted small mb-0 mt-3">
              {{ $checkinLabel }} to {{ $checkoutLabel }}
              · {{ $nights }} {{ Str::plural('night', $nights) }}
              · {{ $searchRoomCount }} {{ Str::plural('room', $searchRoomCount) }}
              · {{ $searchGuestCount }} {{ Str::plural('guest', $searchGuestCount) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    @if($offers === [])
      <div class="row">
        <div class="col-md-12">
          <div class="tile">
            <div class="tile-body">
              <p class="text-muted mb-0">No rooms available for the selected dates. Please try other dates.</p>
            </div>
          </div>
        </div>
      </div>
    @endif

    @foreach($offers as $index => $offer)
      @php
        $desc = trim((string) ($offer['description'] ?? ''));
        $showDesc = $desc !== '' && strcasecmp($desc, $offer['name']) !== 0;
        $quoteRooms = min($searchRoomCount, $offer['max_rooms']);
        $quoteTotal = round($offer['daily_rate'] * $nights * $quoteRooms, 0);
        $defaultGuests = min($searchGuestCount, $offer['max_occupancy'] * $quoteRooms);
        $defaultGuests = max(1, $defaultGuests);
      @endphp
      <div class="row">
        <div class="col-md-12">
          <div class="tile">
            <div class="tile-body">
              <div class="row">
                <div class="col-md-3 mb-3 mb-md-0">
                  @if($offer['photo_url'])
                    <img src="{{ $offer['photo_url'] }}" alt="{{ $offer['name'] }}" class="book-room__photo">
                  @else
                    <div class="book-room__photo-empty">No photo</div>
                  @endif
                </div>
                <div class="col-md-9">
                  <h4 class="book-room__title">{{ $offer['name'] }}</h4>
                  @if($showDesc)
                    <p class="text-muted mb-2">{{ $desc }}</p>
                  @endif
                  <p class="small mb-2">
                    <span class="badge badge-secondary">{{ $offer['meal_plan_label'] }}</span>
                    <span class="text-muted ml-2">
                      Max {{ $offer['max_occupancy'] }} guests per room · Up to {{ $offer['max_rooms'] }} {{ Str::plural('room', $offer['max_rooms']) }}
                    </span>
                  </p>
                  @if(! empty($offer['amenity_labels']))
                    <p class="small text-muted mb-0">{{ implode(' · ', $offer['amenity_labels']) }}</p>
                  @endif

                  <div class="book-room__actions">
                    <div>
                      <span class="book-room__price">{{ number_format($offer['daily_rate'], 0) }} {{ $offer['currency'] }}</span>
                      <span class="text-muted small"> / room / night</span>
                      <div class="text-muted small">
                        {{ number_format($quoteTotal, 0) }} {{ $offer['currency'] }}
                        for {{ $quoteRooms }} {{ Str::plural('room', $quoteRooms) }}, {{ $nights }} {{ Str::plural('night', $nights) }}
                      </div>
                    </div>
                    <button type="button" class="btn btn-primary js-toggle-book-form" data-target="book-form-{{ $index }}">
                      <i class="fa fa-calendar-check-o"></i> Book this room
                    </button>
                  </div>

                  <div id="book-form-{{ $index }}" class="mt-4 pt-3 border-top" style="display:none;">
                    <h5 class="mb-3">Guest details</h5>
                    <form method="POST" action="{{ route('book.store', $bookingEngine['public_slug']) }}">
                      @csrf
                      <input type="hidden" name="checkin" value="{{ $checkin }}">
                      <input type="hidden" name="checkout" value="{{ $checkout }}">
                      <input type="hidden" name="hotel_room_id" value="{{ $offer['room_id'] }}">
                      <input type="hidden" name="hotel_rate_plan_id" value="{{ $offer['rate_plan_id'] }}">

                      <div class="row">
                        <div class="col-md-3">
                          <div class="form-group">
                            <label class="control-label">Number of rooms *</label>
                            <input type="number" name="room_count" class="form-control" min="1" max="{{ $offer['max_rooms'] }}"
                              value="{{ old('room_count', $quoteRooms) }}" required>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label class="control-label">Number of guests *</label>
                            <input type="number" name="guest_count" class="form-control" min="1"
                              max="{{ $offer['max_occupancy'] * $offer['max_rooms'] }}"
                              value="{{ old('guest_count', $defaultGuests) }}" required>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <label class="control-label">Guest name *</label>
                            <input type="text" name="guest_name" class="form-control" required maxlength="160" value="{{ old('guest_name') }}">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <label class="control-label">Email</label>
                            <input type="email" name="guest_email" class="form-control" value="{{ old('guest_email') }}">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <label class="control-label">Phone</label>
                            <input type="text" name="guest_phone" class="form-control" value="{{ old('guest_phone') }}">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <label class="control-label">Promo code</label>
                            <input type="text" name="coupon_code" class="form-control" value="{{ old('coupon_code') }}" style="text-transform:uppercase;">
                          </div>
                        </div>
                        <div class="col-md-12">
                          <div class="form-group mb-0">
                            <label class="control-label">Special request</label>
                            <input type="text" name="special_request" class="form-control" value="{{ old('special_request') }}">
                          </div>
                        </div>
                      </div>
                      <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Confirm booking</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach

    @if(! empty($beSettings['cancellation_policy']))
      <div class="row">
        <div class="col-md-12">
          <div class="tile">
            <h3 class="tile-title">Cancellation policy</h3>
            <div class="tile-body">
              <p class="mb-0">{{ $beSettings['cancellation_policy'] }}</p>
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>
@endsection

@push('scripts')
  <script>
    jQuery(function ($) {
      $('.js-toggle-book-form').on('click', function () {
        var $panel = $('#' + $(this).data('target'));
        $('[id^="book-form-"]').not($panel).slideUp(150);
        $panel.slideToggle(150);
      });
    });
  </script>
@endpush
