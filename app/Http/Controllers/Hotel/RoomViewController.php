<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CmReservationService;
use App\Services\HotelLogService;
use App\Services\RoomViewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomViewController extends Controller
{
    public function __construct(
        private RoomViewService $roomView,
        private ChannelManagerCodeResolver $codes,
        private CmReservationService $reservations,
        private HotelLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();

        $grid = $this->roomView->build($hotel, $date);

        return view('hotel.pms.room-view', [
            'hotel' => $hotel,
            'grid' => $grid,
            'date' => $grid['date'],
            'prevDate' => $grid['date']->copy()->subDay(),
            'nextDate' => $grid['date']->copy()->addDay(),
            'ui' => $this->roomView->uiConfig(),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();

        $hotelCode = $this->codes->hotelCode($hotel);

        $result = $this->reservations->syncFromChannelManager(
            $hotelCode,
            $hotel->id,
            $date->copy()->subDays(7)->format('Y-m-d'),
            $date->copy()->addDays(30)->format('Y-m-d')
        );

        $this->logs->record($hotel, [
            'action_type' => 'Rooms View Inventory Sync',
            'details' => $result['message'] ?? 'Room view inventory sync completed.',
        ]);

        return redirect()
            ->route('hotel.room-view', ['date' => $date->format('Y-m-d')])
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }
}
