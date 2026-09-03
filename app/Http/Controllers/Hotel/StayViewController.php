<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CmReservationService;
use App\Services\HotelLogService;
use App\Services\StayViewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StayViewController extends Controller
{
    public function __construct(
        private StayViewService $stayView,
        private ChannelManagerCodeResolver $codes,
        private CmReservationService $reservations,
        private HotelLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $start = $request->filled('start')
            ? Carbon::parse($request->input('start'))->startOfDay()
            : now()->startOfDay();

        $grid = $this->stayView->build($hotel, $start);

        return view('hotel.pms.stay-view', [
            'hotel' => $hotel,
            'grid' => $grid,
            'start' => $grid['start'],
            'prevStart' => $grid['start']->copy()->subDays(StayViewService::WINDOW_DAYS),
            'nextStart' => $grid['start']->copy()->addDays(StayViewService::WINDOW_DAYS),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $start = $request->filled('start')
            ? Carbon::parse($request->input('start'))->startOfDay()
            : now()->startOfDay();

        $end = $start->copy()->addDays(StayViewService::WINDOW_DAYS - 1);
        $hotelCode = $this->codes->hotelCode($hotel);

        $result = $this->reservations->syncFromChannelManager(
            $hotelCode,
            $hotel->id,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        $fromLabel = $start->format('Y-m-d').' 00:00:00';
        $toLabel = $end->copy()->addDay()->format('Y-m-d').' 00:00:00';

        $this->logs->record($hotel, [
            'action_type' => 'Stayview Inventory Sync',
            'details' => "Updated Inventory from {$fromLabel} to {$toLabel}",
        ]);

        $this->logs->record($hotel, [
            'action_type' => 'updateInventoryFromPMS ran',
            'details' => $result['message'] ?? 'Inventory sync completed.',
        ]);

        return redirect()
            ->route('hotel.stay-view', ['start' => $start->format('Y-m-d')])
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }
}
