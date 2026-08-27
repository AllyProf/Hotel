<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtaCommissionController extends Controller
{
    /** @var array<string, string> */
    public const FILTER_TYPES = [
        'stay_date' => 'Stay Date',
        'booking_date' => 'Booking Date',
        'checkin_date' => 'Checkin Date',
        'checkout_date' => 'Check out Date',
    ];

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel;
        $submitted = $request->boolean('submitted');

        $filters = [
            'start_date' => $request->input('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'filter_type' => $request->input('filter_type', 'checkin_date'),
        ];

        if (! array_key_exists($filters['filter_type'], self::FILTER_TYPES)) {
            $filters['filter_type'] = 'checkin_date';
        }

        $commissions = $submitted ? collect() : null;

        return view('hotel.channel-manager.ota-commission', [
            'hotel' => $hotel,
            'filters' => $filters,
            'filterTypes' => self::FILTER_TYPES,
            'submitted' => $submitted,
            'commissions' => $commissions,
        ]);
    }
}
