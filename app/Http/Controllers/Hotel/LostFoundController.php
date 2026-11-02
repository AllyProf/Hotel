<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\CmReservation;
use App\Models\HotelLostFoundItem;
use App\Services\Erp\HousekeepingMaintenanceService;
use App\Services\LostFoundGuestMatchService;
use App\Services\LostFoundNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LostFoundController extends Controller
{
    public function __construct(
        private HousekeepingMaintenanceService $erp,
        private LostFoundGuestMatchService $guestMatch,
        private LostFoundNotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filter = in_array($request->input('filter'), ['open', 'done', 'all'], true)
            ? (string) $request->input('filter')
            : 'open';

        $ui = $this->erp->uiConfig();
        $items = $this->erp->lostFoundItems($hotel, $filter);

        return view('hotel.pms.lost-found.index', [
            'hotel' => $hotel,
            'ui' => $ui,
            'filter' => $filter,
            'items' => $items,
            'roomUnits' => $this->erp->roomUnitOptions($hotel),
            'openCount' => $this->erp->lostFoundOpenCount($hotel),
            'guestHints' => $this->guestMatch->hintsByItems($hotel, $items),
            'notificationByItem' => $this->notifications->latestByItems($items),
        ]);
    }

    public function notifyGuest(Request $request, HotelLostFoundItem $item): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'reservation_id' => ['required', 'integer'],
            'filter' => ['nullable', 'string', 'in:open,done,all'],
        ]);

        $reservation = CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->findOrFail((int) $data['reservation_id']);

        try {
            $this->notifications->sendGuestEmail($hotel, $item, $reservation);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.lost-found.index', ['filter' => $data['filter'] ?? 'open'])
            ->with('success', 'Notification email sent to '.$reservation->guestName().'.');
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'entry_type' => ['sometimes', 'string', 'in:found'],
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:32'],
            'hotel_room_unit_id' => ['nullable', 'integer'],
            'room_no' => ['nullable', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:128'],
            'storage_location' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'return_to' => ['nullable', 'string', 'in:maintenance_hk'],
        ]);

        $data['entry_type'] = HotelLostFoundItem::TYPE_FOUND;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('lost-found/'.$hotel->id, 'public');
        }

        $this->erp->createLostFoundItem($hotel, $data);

        if ($request->input('return_to') === 'maintenance_hk') {
            return redirect()
                ->route('hotel.maintenance-hk.index', ['tab' => 'lost_found'])
                ->with('success', 'Found item logged.');
        }

        return redirect()
            ->route('hotel.lost-found.index', ['filter' => 'open'])
            ->with('success', 'Found item logged.');
    }

    public function update(Request $request, HotelLostFoundItem $item): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'status' => ['required', 'string', 'max:32'],
            'claimed_by_name' => ['nullable', 'string', 'max:255'],
            'closure_notes' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'in:maintenance_hk'],
        ]);

        try {
            $this->erp->updateLostFoundStatus($hotel, $item, $data['status'], $data);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        $message = 'Found item updated.';

        if ($request->input('return_to') === 'maintenance_hk') {
            return back()->with('success', $message);
        }

        return redirect()
            ->route('hotel.lost-found.index', ['filter' => $request->input('filter', 'open')])
            ->with('success', $message);
    }
}
