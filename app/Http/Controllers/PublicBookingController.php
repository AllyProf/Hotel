<?php

namespace App\Http\Controllers;

use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\BookingEngineService;
use App\Services\CreateReservationService;
use App\Services\HotelAmenityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(
        private BookingEngineService $bookingEngine,
        private CreateReservationService $reservations,
        private HotelAmenityService $amenities,
    ) {}

    public function show(Request $request, string $slug): View
    {
        $resolved = $this->bookingEngine->resolvePublicHotel($slug);

        abort_unless($resolved !== null, 404, 'This booking page is not available.');

        $hotel = $resolved['hotel'];
        $checkin = Carbon::parse($request->input('checkin', now()->addDay()->format('Y-m-d')))->startOfDay();
        $checkout = Carbon::parse($request->input('checkout', now()->addDays(2)->format('Y-m-d')))->startOfDay();

        if ($checkout->lte($checkin)) {
            $checkout = $checkin->copy()->addDay();
        }

        $nights = max(1, $checkin->diffInDays($checkout));
        $offers = $this->bookingEngine->roomOffers($hotel, $checkin, $checkout);
        $amenityCatalog = $this->amenities->allForHotel($hotel);
        $mealPlans = collect(config('channel_manager_integration.overview.meal_plans', []))
            ->keyBy('code');

        foreach ($offers as $index => $offer) {
            $room = $hotel->rooms->firstWhere('id', $offer['room_id']);
            $offers[$index]['amenity_labels'] = collect($room?->amenities ?? [])
                ->take(6)
                ->map(fn (string $key) => $amenityCatalog[$key]['label'] ?? null)
                ->filter()
                ->values()
                ->all();
            $code = strtoupper((string) ($offer['meal_plan'] ?? ''));
            $planMeta = $mealPlans->get($code);
            $offers[$index]['meal_plan_label'] = $planMeta['name'] ?? $planMeta['label'] ?? ($code !== '' ? $code : 'Room only');
            $offers[$index]['meal_plan'] = $code;
        }

        $searchRoomCount = max(1, (int) $request->input('room_count', 1));
        $searchGuestCount = max(1, (int) $request->input('guest_count', 2));

        return view('book.show', [
            'hotel' => $hotel,
            'bookingEngine' => $resolved['booking_engine'],
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'checkinLabel' => $checkin->format('D, d M Y'),
            'checkoutLabel' => $checkout->format('D, d M Y'),
            'nights' => $nights,
            'searchRoomCount' => $searchRoomCount,
            'searchGuestCount' => $searchGuestCount,
            'offers' => $offers,
            'beSettings' => $hotel->settings?->be ?? [],
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $resolved = $this->bookingEngine->resolvePublicHotel($slug);

        abort_unless($resolved !== null, 404, 'This booking page is not available.');

        $hotel = $resolved['hotel'];

        $validated = $request->validate([
            'checkin' => ['required', 'date', 'after_or_equal:today'],
            'checkout' => ['required', 'date', 'after:checkin'],
            'hotel_room_id' => ['required', 'integer'],
            'hotel_rate_plan_id' => ['required', 'integer'],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'room_count' => ['required', 'integer', 'min:1', 'max:50'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'special_request' => ['nullable', 'string', 'max:500'],
        ]);

        $room = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_enabled', true)
            ->findOrFail($validated['hotel_room_id']);

        $plan = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->where('hotel_room_id', $room->id)
            ->findOrFail($validated['hotel_rate_plan_id']);

        $checkin = Carbon::parse($validated['checkin'])->startOfDay();
        $checkout = Carbon::parse($validated['checkout'])->startOfDay();
        $offers = collect($this->bookingEngine->roomOffers($hotel, $checkin, $checkout));
        $offer = $offers->first(fn (array $row) => (int) $row['room_id'] === (int) $room->id
            && (int) $row['rate_plan_id'] === (int) $plan->id);

        abort_unless($offer !== null, 422);

        $roomCount = max(1, (int) $validated['room_count']);
        $guestCount = max(1, (int) $validated['guest_count']);
        $maxRooms = (int) ($offer['max_rooms'] ?? 1);
        $maxOccupancy = (int) ($offer['max_occupancy'] ?? 2);

        if ($roomCount > $maxRooms) {
            return back()->withErrors([
                'room_count' => "You can book up to {$maxRooms} room(s) for this type.",
            ])->withInput();
        }

        if ($guestCount > $maxOccupancy * $roomCount) {
            $maxGuestsAllowed = $maxOccupancy * $roomCount;

            return back()->withErrors([
                'guest_count' => "Maximum {$maxOccupancy} guests per room ({$maxGuestsAllowed} for {$roomCount} room(s)).",
            ])->withInput();
        }

        $nights = max(1, $checkin->diffInDays($checkout));
        $dailyRate = (float) $offer['daily_rate'];
        $total = round($dailyRate * $nights * $roomCount, 2);
        $coupon = null;

        if (! empty($validated['coupon_code'])) {
            $coupon = $this->bookingEngine->validateCoupon($hotel, $validated['coupon_code'], $checkin, $checkout);
            if ($coupon) {
                $total = $this->bookingEngine->applyCouponDiscount($total, $coupon);
            }
        }

        $adjustedDaily = $roomCount > 0 && $nights > 0
            ? round($total / ($nights * $roomCount), 2)
            : $dailyRate;

        $booking = $this->reservations->store($hotel, [
            'checkin' => $checkin->format('Y-m-d'),
            'checkout' => $checkout->format('Y-m-d'),
            'hotel_room_id' => $room->id,
            'hotel_rate_plan_id' => $plan->id,
            'guest_count' => $guestCount,
            'room_count' => $roomCount,
            'guest_name' => $validated['guest_name'],
            'guest_email' => $validated['guest_email'] ?? null,
            'guest_phone' => $validated['guest_phone'] ?? null,
            'daily_rate' => $adjustedDaily,
            'tax_inclusive' => true,
            'segment' => 'Direct Web',
            'payment_mode' => 'Prepaid',
            'booked_by' => 'Booking Engine',
            'special_request' => $validated['special_request'] ?? null,
        ]);

        if ($coupon) {
            $payload = is_array($booking->payload) ? $booking->payload : [];
            $payload['booking_engine'] = [
                'coupon_code' => strtoupper(trim($validated['coupon_code'])),
                'coupon_discount_type' => $coupon['discount_type'] ?? null,
                'coupon_discount_value' => $coupon['discount_value'] ?? null,
            ];
            $booking->update([
                'payload' => $payload,
                'amount_after_tax' => $total,
            ]);
        }

        return redirect()
            ->route('book.confirmation', [
                'slug' => $slug,
                'booking' => $booking->booking_id,
            ])
            ->with('success', 'Booking confirmed.');
    }

    public function confirmation(string $slug, string $booking): View
    {
        $resolved = $this->bookingEngine->resolvePublicHotel($slug);

        abort_unless($resolved !== null, 404, 'This booking page is not available.');

        $hotel = $resolved['hotel'];
        $reservation = $hotel->cmReservations()->where('booking_id', $booking)->firstOrFail();

        return view('book.confirmation', [
            'hotel' => $hotel,
            'bookingEngine' => $resolved['booking_engine'],
            'reservation' => $reservation,
            'beSettings' => $hotel->settings?->be ?? [],
        ]);
    }
}
