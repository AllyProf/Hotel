<?php

namespace App\Http\Controllers;

use App\Services\HotelDashboardService;
use Illuminate\View\View;

class HotelDashboardController extends Controller
{
    public function __construct(
        private HotelDashboardService $dashboard,
    ) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with('plan')->first();
        $snapshot = $hotel ? $this->dashboard->snapshot($hotel) : null;

        return view('hotel.dashboard', [
            'hotel' => $hotel,
            'snapshot' => $snapshot,
        ]);
    }
}
