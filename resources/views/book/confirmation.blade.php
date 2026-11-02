@extends('layouts.booking-public')

@section('title', 'Booking confirmed')

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-check-circle text-success"></i> Booking confirmed</h1>
      <p>Confirmation {{ $reservation->booking_id }}</p>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="tile">
        <h3 class="tile-title">Reservation details</h3>
        <div class="tile-body">
          <table class="table table-bordered mb-0">
            <tbody>
              <tr>
                <th style="width:35%">Guest</th>
                <td>{{ $reservation->guestName() }}</td>
              </tr>
              <tr>
                <th>Check-in</th>
                <td>{{ $reservation->checkin?->format('d/m/Y') }}</td>
              </tr>
              <tr>
                <th>Check-out</th>
                <td>{{ $reservation->checkout?->format('d/m/Y') }}</td>
              </tr>
              <tr>
                <th>Room type</th>
                <td>{{ $reservation->roomLabel() !== '—' ? $reservation->roomLabel() : '—' }}</td>
              </tr>
              <tr>
                <th>Rooms / guests</th>
                <td>
                  @php
                    $rooms = $reservation->rooms[0]['numberOfRooms'] ?? 1;
                    $adults = $reservation->rooms[0]['occupancy']['adults'] ?? '—';
                  @endphp
                  {{ $rooms }} {{ Str::plural('room', (int) $rooms) }}, {{ $adults }} {{ Str::plural('guest', (int) $adults) }}
                </td>
              </tr>
              <tr>
                <th>Total</th>
                <td><strong>{{ number_format((float) $reservation->amount_after_tax, 2) }} {{ $reservation->currency }}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="tile-footer">
          <a href="{{ route('book.show', $bookingEngine['public_slug']) }}" class="btn btn-primary">
            <i class="fa fa-bed"></i> Book another stay
          </a>
        </div>
      </div>
    </div>
  </div>
@endsection
