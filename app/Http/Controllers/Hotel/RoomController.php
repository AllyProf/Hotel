<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Models\HotelRatePlan;
use App\Services\BranchContextService;
use App\Services\ChannelManager\ChannelManagerPushService;
use App\Services\HotelAmenityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function __construct(
        private BranchContextService $branchContext,
        private HotelAmenityService $amenityService,
        private ChannelManagerPushService $cmPush,
    ) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with('plan')->firstOrFail();
        $rooms = $hotel->rooms()
            ->withCount(['units', 'photos'])
            ->with([
                'units' => fn ($q) => $q->whereNotNull('room_number')->orderBy('room_number'),
                'ratePlans' => fn ($q) => $q->where('is_master', true),
            ])
            ->orderBy('rank')
            ->orderBy('name')
            ->paginate(10);

        return view('hotel.rooms.index', [
            'hotel' => $hotel,
            'rooms' => $rooms,
            'canAddRoom' => $hotel->canAddRoom(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with(['plan', 'settings'])->firstOrFail();

        if (! $hotel->canAddRoom()) {
            return redirect()
                ->route('hotel.rooms.index')
                ->with('error', 'You have reached the maximum number of room types allowed on your plan.');
        }

        return view('hotel.rooms.create', [
            'hotel' => $hotel,
            'amenities' => $this->amenityService->allForHotel($hotel),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('plan')->firstOrFail();

        if (! $hotel->canAddRoom()) {
            return redirect()
                ->route('hotel.rooms.index')
                ->with('error', 'You have reached the maximum number of room types allowed on your plan.');
        }

        $validated = $this->validateRoom($request);
        $nextRank = (int) $hotel->rooms()->max('rank') + 1;

        $room = $hotel->rooms()->create([
            'is_enabled' => true,
            'rank' => $nextRank,
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'room_count' => (int) $validated['room_count'],
            'min_occupancy' => 1,
            'max_occupancy' => (int) $validated['max_occupancy'],
            'amenities' => $this->normalizeAmenities($request),
        ]);

        $this->syncRoomUnits($room, $request->input('units', []), $hotel->id);
        $this->ensureDefaultRatePlan($hotel, $room);
        $this->syncPhotos($room, $request);

        $flash = $this->cmPush->flashForSaveResult(
            'Room "'.$room->name.'" created successfully. Set prices under Settings → Prices.',
            $this->cmPush->pushAfterInventorySave($hotel->fresh(), now()->startOfDay())
        );

        return redirect()
            ->route('hotel.rooms.index')
            ->with($flash['flash_key'], $flash['message']);
    }

    public function edit(HotelRoom $room): View
    {
        $this->authorizeRoom($room);
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $room->load([
            'units' => fn ($q) => $q->orderBy('room_number'),
            'photos',
        ]);

        return view('hotel.rooms.edit', [
            'hotel' => $hotel,
            'room' => $room,
            'amenities' => $this->amenityService->allForHotel($hotel),
        ]);
    }

    public function update(Request $request, HotelRoom $room): RedirectResponse
    {
        $this->authorizeRoom($room);
        $validated = $this->validateRoom($request, $room);

        $room->update([
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'room_count' => (int) $validated['room_count'],
            'max_occupancy' => (int) $validated['max_occupancy'],
            'is_enabled' => $request->boolean('is_enabled'),
            'amenities' => $this->normalizeAmenities($request),
        ]);

        $this->syncRoomUnits($room, $request->input('units', []), $room->hotel_id);
        $this->syncMasterRatePlanMeta($room);
        $this->syncPhotos($room, $request);

        $flash = $this->cmPush->flashForSaveResult(
            'Room updated successfully.',
            $this->cmPush->pushAfterInventorySave($room->hotel()->first(), now()->startOfDay())
        );

        return redirect()
            ->route('hotel.rooms.index')
            ->with($flash['flash_key'], $flash['message']);
    }

    public function destroy(HotelRoom $room): RedirectResponse
    {
        $this->authorizeRoom($room);
        $name = $room->name;
        $room->delete();

        return redirect()
            ->route('hotel.rooms.index')
            ->with('success', 'Room "'.$name.'" deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function validateRoom(Request $request, ?HotelRoom $room = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'room_count' => ['required', 'integer', 'min:1', 'max:999'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:20'],
            'is_enabled' => ['nullable', 'boolean'],
            'units' => ['nullable', 'array', 'max:999'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.room_number' => ['nullable', 'string', 'max:20'],
            'units.*.label' => ['nullable', 'string', 'max:100'],
            'amenities' => ['nullable', 'array', 'max:9'],
            'amenities.*' => ['string', 'max:80'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'delete_photos' => ['nullable', 'array'],
            'delete_photos.*' => ['integer'],
        ], [], [
            'units.*.room_number' => 'room number',
        ]);
    }

    /** @param array<int, array<string, mixed>> $unitsInput */
    private function syncRoomUnits(HotelRoom $room, array $unitsInput, int $hotelId): void
    {
        $branchId = $this->branchContext->activeBranch()?->id;
        $keptIds = [];
        $usedNumbers = [];

        foreach ($unitsInput as $unitData) {
            $roomNumber = trim((string) ($unitData['room_number'] ?? ''));

            if ($roomNumber === '') {
                continue;
            }

            if (in_array($roomNumber, $usedNumbers, true)) {
                continue;
            }

            $usedNumbers[] = $roomNumber;

            $attrs = [
                'hotel_id' => $hotelId,
                'branch_id' => $branchId,
                'room_number' => $roomNumber,
                'label' => trim((string) ($unitData['label'] ?? '')) ?: null,
                'status' => HotelRoomUnit::STATUS_AVAILABLE,
            ];

            if (! empty($unitData['id'])) {
                $unit = $room->units()->where('id', $unitData['id'])->first();

                if ($unit) {
                    $unit->update($attrs);
                    $keptIds[] = $unit->id;
                }
            } else {
                $existing = HotelRoomUnit::query()
                    ->where('hotel_id', $hotelId)
                    ->where('room_number', $roomNumber)
                    ->first();

                if ($existing && $existing->hotel_room_id !== $room->id) {
                    continue;
                }

                $unit = $room->units()->updateOrCreate(
                    ['hotel_id' => $hotelId, 'room_number' => $roomNumber],
                    $attrs
                );
                $keptIds[] = $unit->id;
            }
        }

        $room->units()->whereNotIn('id', $keptIds)->delete();
    }

    private function syncPhotos(HotelRoom $room, Request $request): void
    {
        foreach ($request->input('delete_photos', []) as $photoId) {
            $room->photos()->where('id', $photoId)->first()?->delete();
        }

        if (! $request->hasFile('photos')) {
            return;
        }

        $existing = $room->photos()->count();
        $maxPhotos = 8;

        foreach ($request->file('photos', []) as $file) {
            if ($existing >= $maxPhotos || ! $file?->isValid()) {
                break;
            }

            $path = $file->store('hotel-rooms/'.$room->hotel_id.'/'.$room->id, 'public');

            $room->photos()->create([
                'hotel_id' => $room->hotel_id,
                'path' => $path,
                'sort_order' => $existing,
                'is_primary' => $existing === 0 && ! $room->photos()->where('is_primary', true)->exists(),
            ]);

            $existing++;
        }
    }

    private function ensureDefaultRatePlan($hotel, HotelRoom $room): void
    {
        if ($room->ratePlans()->exists()) {
            return;
        }

        $hotel->ratePlans()->create([
            'hotel_room_id' => $room->id,
            'code' => 'R'.$room->id,
            'occupancy' => 'Standard',
            'meal_plan' => 'EP',
            'colour_code' => '#940000',
            'meals' => 0,
            'is_master' => true,
            'pricing_mode' => HotelRatePlan::PRICING_BOTH,
            'local_base_rate' => null,
            'local_currency' => $hotel->currency ?? 'TZS',
            'base_rate' => 0,
            'international_currency' => 'USD',
            'ratio' => 1,
            'be_ratio' => 0.85,
            'description' => $room->name,
        ]);
    }

    private function syncMasterRatePlanMeta(HotelRoom $room): void
    {
        $plan = $room->ratePlans()->where('is_master', true)->first() ?? $room->ratePlans()->first();

        if (! $plan) {
            $this->ensureDefaultRatePlan($room->hotel, $room);

            return;
        }

        if ($plan->description !== $room->name) {
            $plan->update(['description' => $room->name]);
        }
    }

    private function authorizeRoom(HotelRoom $room): void
    {
        if ($room->hotel_id !== auth()->user()->hotel_id) {
            abort(403);
        }
    }

    /** @return list<string> */
    private function normalizeAmenities(Request $request): array
    {
        $hotel = auth()->user()->hotel()->with('settings')->first();
        $allowed = $this->amenityService->allowedKeys($hotel);

        return array_values(array_slice(array_intersect(
            $request->input('amenities', []),
            $allowed
        ), 0, 9));
    }
}
