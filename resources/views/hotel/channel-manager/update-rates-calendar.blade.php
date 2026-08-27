@extends('layouts.app')

@section('title', 'Update Rates — Calendar')

@push('styles')
  <style>
    .rates-cal-tile {
      padding: 0;
      overflow: hidden;
      background: #fff;
    }

    .rates-cal-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      padding: 12px 16px;
      border-bottom: 1px solid #dde1e6;
      background: #fff;
    }

    .rates-cal-toolbar-right {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .rates-cal-pills {
      display: flex;
      gap: 0;
      border-radius: 4px;
      overflow: hidden;
    }

    .rates-cal-pill {
      display: inline-block;
      padding: 6px 16px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none !important;
      color: #fff !important;
      border: none;
    }

    .rates-cal-pill.is-overall {
      background: #1877f2;
    }

    .rates-cal-pill.is-room {
      background: #5cb85c;
    }

    .rates-cal-pill.is-inactive {
      background: #e8eaed;
      color: #555 !important;
    }

    .rates-cal-nav-btn {
      background: #fff;
      border: 1px solid #c5ccd3;
      color: #333;
      font-weight: 600;
      font-size: 13px;
      padding: 5px 12px;
      border-radius: 3px;
      text-decoration: none !important;
    }

    .rates-cal-nav-btn:hover {
      background: #f5f6f7;
      color: #222 !important;
    }

    .rates-cal-nav-arrow {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border: 1px solid #c5ccd3;
      background: #fff;
      color: #444;
      text-decoration: none !important;
      font-weight: 700;
      border-radius: 3px;
    }

    .rates-cal-nav-arrow:hover {
      background: #f3f4f6;
      color: #111 !important;
    }

    .rates-cal-body {
      padding: 16px 20px 24px;
    }

    .rates-cal-month-title {
      font-size: 22px;
      font-weight: 400;
      color: #333;
      margin: 0 0 12px;
    }

    .rates-cal-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      table-layout: fixed;
    }

    .rates-cal-table thead th {
      background: #4f5862;
      color: #fff;
      font-weight: 700;
      font-size: 13px;
      text-align: center;
      padding: 10px 6px;
      border: 1px solid #434b54;
    }

    .rates-cal-table tbody td {
      border: 1px solid #d8dde3;
      vertical-align: top;
      height: 88px;
      padding: 6px 8px;
      background: #fff;
      cursor: pointer;
      position: relative;
    }

    .rates-cal-table tbody td.is-outside {
      background: #fafbfc;
      color: #bbb;
    }

    .rates-cal-table tbody td.is-today {
      box-shadow: inset 0 0 0 2px #1877f2;
    }

    .rates-cal-table tbody td.is-selected {
      background: #e8eaed;
    }

    .rates-cal-day-num {
      position: absolute;
      top: 6px;
      right: 8px;
      font-size: 12px;
      color: #999;
      font-weight: 600;
    }

    .rates-cal-cell-body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      padding-top: 14px;
    }

    .rates-cal-price {
      font-size: 15px;
      font-weight: 700;
      color: #222;
      line-height: 1.2;
    }

    .rates-cal-avail {
      font-size: 12px;
      color: #777;
      margin-top: 4px;
    }

    .btn-rates-secondary {
      background: #fff;
      border: 1px solid #c5ccd3;
      color: #333;
      font-weight: 600;
      font-size: 13px;
      padding: 6px 14px;
      border-radius: 3px;
    }

    .btn-rates-secondary:hover {
      background: #f5f6f7;
      color: #222;
    }

    .rates-cal-room-select {
      max-width: 180px;
      font-size: 13px;
    }
  </style>
@endpush

@section('content')
  @php
    $selectedRoomId = $calendar['selectedRoomId'] ?? null;
    $prevMonth = \Carbon\Carbon::parse($month)->subMonth()->format('Y-m');
    $nextMonth = \Carbon\Carbon::parse($month)->addMonth()->format('Y-m');
    $currency = $hotel->currency ?? 'USD';
    $currencySymbol = match ($currency) {
      'USD' => '$',
      'EUR' => '€',
      'GBP' => '£',
      default => $currency.' ',
    };
  @endphp

  <div class="app-title">
    <div>
      <h1><i class="fa fa-calendar"></i> Channel Manager <small class="text-muted">Calendar View</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="#">Channel Manager</a></li>
      <li class="breadcrumb-item">Update Rates</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile rates-cal-tile">
        <div class="rates-cal-toolbar">
          <div class="d-flex align-items-center flex-wrap" style="gap:10px;">
            <div class="rates-cal-pills">
              <a href="{{ route('hotel.channel-manager.update-rates', ['view' => 'calendar', 'month' => $month]) }}"
                 class="rates-cal-pill {{ $selectedRoomId ? 'is-inactive' : 'is-overall' }}">Overall</a>
              @if(count($calendar['rooms']))
                <a href="{{ route('hotel.channel-manager.update-rates', ['view' => 'calendar', 'month' => $month, 'room_id' => $selectedRoomId ?? $calendar['rooms'][0]['id']]) }}"
                   class="rates-cal-pill {{ $selectedRoomId ? 'is-room' : 'is-inactive' }}">Single room</a>
              @endif
            </div>
            @if($selectedRoomId && count($calendar['rooms']) > 1)
              <select class="form-control form-control-sm rates-cal-room-select" id="calRoomSelect">
                @foreach($calendar['rooms'] as $room)
                  <option value="{{ $room['id'] }}" {{ (int) $selectedRoomId === (int) $room['id'] ? 'selected' : '' }}>
                    {{ $room['name'] }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>
          <div class="rates-cal-toolbar-right">
            <a href="{{ route('hotel.channel-manager.update-rates', ['start_date' => now()->format('Y-m-d')]) }}"
               class="btn btn-rates-secondary btn-sm">Grid View</a>
            <a href="{{ route('hotel.channel-manager.update-rates', ['view' => 'calendar', 'month' => now()->format('Y-m'), 'room_id' => $selectedRoomId]) }}"
               class="rates-cal-nav-btn">today</a>
            <a href="{{ route('hotel.channel-manager.update-rates', ['view' => 'calendar', 'month' => $prevMonth, 'room_id' => $selectedRoomId]) }}"
               class="rates-cal-nav-arrow">&lt;</a>
            <a href="{{ route('hotel.channel-manager.update-rates', ['view' => 'calendar', 'month' => $nextMonth, 'room_id' => $selectedRoomId]) }}"
               class="rates-cal-nav-arrow">&gt;</a>
          </div>
        </div>

        <div class="rates-cal-body">
          <h2 class="rates-cal-month-title">{{ $calendar['monthLabel'] }}</h2>

          <table class="rates-cal-table">
            <thead>
              <tr>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                  <th>{{ $dow }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($calendar['weeks'] as $week)
                <tr>
                  @foreach($week as $day)
                    <td class="js-cal-day {{ !$day['inMonth'] ? 'is-outside' : '' }} {{ $day['isToday'] ? 'is-today' : '' }}"
                        data-date="{{ $day['date'] }}"
                        data-label="{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}">
                      <span class="rates-cal-day-num">{{ $day['day'] }}</span>
                      @if($day['inMonth'])
                        <div class="rates-cal-cell-body">
                          @if($day['price'] !== null)
                            <span class="rates-cal-price">{{ $currencySymbol }}{{ number_format($day['price'], 0) }}</span>
                          @endif
                          <span class="rates-cal-avail">{{ $day['available'] }} ({{ number_format($day['occupancy'], 0) }}%)</span>
                        </div>
                      @endif
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @include('hotel.channel-manager.partials.calendar-day-modal')
@endsection

@push('scripts')
  <script>
    (function () {
      var roomSelect = document.getElementById('calRoomSelect');
      if (roomSelect) {
        roomSelect.addEventListener('change', function () {
          var url = new URL(window.location.href);
          url.searchParams.set('room_id', this.value);
          window.location.href = url.toString();
        });
      }

      document.querySelectorAll('.js-cal-day').forEach(function (cell) {
        cell.addEventListener('click', function () {
          if (cell.classList.contains('is-outside')) return;

          document.querySelectorAll('.js-cal-day.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
          });
          cell.classList.add('is-selected');

          if (typeof window.openCalendarDayModal === 'function') {
            window.openCalendarDayModal(cell.getAttribute('data-date'));
          }
        });
      });
    })();
  </script>
@endpush
