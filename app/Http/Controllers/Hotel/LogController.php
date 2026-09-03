<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\HotelLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function __construct(
        private HotelLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->logs->filtersFromRequest($request);
        $filters['submitted'] = true;

        $logList = $this->logs->query($hotel->id, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hotel.pms.logs.index', [
            'hotel' => $hotel,
            'logs' => $logList,
            'filters' => $filters,
            'options' => $this->logs->filterOptions($hotel),
            'ui' => $this->logs->uiConfig(),
        ]);
    }
}
