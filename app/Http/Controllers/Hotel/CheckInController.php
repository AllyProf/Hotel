<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\CmReservation;
use App\Services\CheckInService;
use App\Services\HotelLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CheckInController extends Controller
{
    public function __construct(
        private CheckInService $checkIn,
        private HotelLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $date = Carbon::parse($request->input('date', now()->format('Y-m-d')))->startOfDay();
        $data = $this->checkIn->listForHotel($hotel, $date, 'arrivals');

        $assignmentPlans = [];

        foreach ($data['bookings'] as $booking) {
            $assignmentPlans[$booking->id] = $this->checkIn->assignmentPlanForReservation($hotel, $booking);
        }

        return view('hotel.pms.check-in.index', [
            'hotel' => $hotel,
            'date' => $data['date'],
            'bookings' => $data['bookings'],
            'assignmentPlans' => $assignmentPlans,
            'ui' => $this->checkIn->uiConfig(),
        ]);
    }

    public function availableUnits(CmReservation $reservation): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel->id);

        return response()->json([
            'units' => $this->checkIn->availableUnitsForReservation($hotel, $reservation),
        ]);
    }

    public function assign(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel->id);

        $validated = $request->validate([
            'room_unit_id' => ['nullable', 'integer', 'exists:hotel_room_units,id'],
            'room_unit_ids' => ['nullable', 'array'],
            'room_unit_ids.*' => ['integer', 'exists:hotel_room_units,id'],
            'allow_other_types' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
        ]);

        $unitIds = collect($validated['room_unit_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($unitIds === [] && ! empty($validated['room_unit_id'])) {
            $unitIds = [(int) $validated['room_unit_id']];
        }

        if ($unitIds === []) {
            return $this->redirectBack($request, 'Select at least one room.', 'error');
        }

        try {
            $updated = $this->checkIn->assignRooms(
                $hotel,
                $reservation,
                $unitIds,
                allowOtherTypes: ! empty($validated['allow_other_types']),
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectBack($request, $e->getMessage(), 'error');
        }

        $this->logs->record($hotel, [
            'action_type' => 'Room Assigned',
            'details' => "Assigned rooms {$updated->assignedRoomLabel()} to booking {$reservation->booking_id} ({$reservation->guestName()}).",
        ]);

        return $this->redirectBack($request, 'Room assigned successfully.');
    }

    public function checkIn(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->assertReservationBelongsToHotel($reservation, $hotel->id);

        $validated = $request->validate([
            'room_unit_id' => ['nullable', 'integer', 'exists:hotel_room_units,id'],
            'room_unit_ids' => ['nullable', 'array'],
            'room_unit_ids.*' => ['integer', 'exists:hotel_room_units,id'],
            'allow_other_types' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
        ]);

        $unitIds = collect($validated['room_unit_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($unitIds === [] && ! empty($validated['room_unit_id'])) {
            $unitIds = [(int) $validated['room_unit_id']];
        }

        try {
            if ($unitIds !== []) {
                $this->checkIn->assignRooms(
                    $hotel,
                    $reservation,
                    $unitIds,
                    allowOtherTypes: ! empty($validated['allow_other_types']),
                );
                $reservation = $reservation->fresh(['roomUnit']);
            }

            $updated = $this->checkIn->checkIn($hotel, $reservation);
        } catch (InvalidArgumentException $e) {
            return $this->redirectBack($request, $e->getMessage(), 'error');
        }

        $this->logs->record($hotel, [
            'action_type' => 'Guest Check-in',
            'details' => "Checked in {$updated->guestName()} — booking {$updated->booking_id}, room {$updated->assignedRoomLabel()}.",
        ]);

        return $this->redirectBack($request, 'Guest checked in successfully.');
    }

    private function assertReservationBelongsToHotel(CmReservation $reservation, int $hotelId): void
    {
        abort_if((int) $reservation->hotel_id !== $hotelId, 404);
    }

    private function redirectBack(Request $request, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect()
            ->route('hotel.check-in.index', array_filter([
                'date' => $request->input('date'),
            ]))
            ->with($type, $message);
    }
}
