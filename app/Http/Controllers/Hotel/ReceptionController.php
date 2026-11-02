<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\DepartmentProcurementIndexData;
use App\Services\Hr\HotelHrService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceptionController extends Controller
{
    public function __construct(
        private DepartmentProcurementIndexData $procurementIndex,
        private HotelHrService $hr,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $tab = $request->query('tab', 'purchase-lists');
        if ($tab === 'market') {
            $tab = 'purchase-lists';
        }
        if (! in_array($tab, ['purchase-lists', 'received', 'stock-receive'], true)) {
            $tab = 'purchase-lists';
        }

        $this->hr->ensureDefaults($hotel);
        $departmentId = is_numeric($request->query('department')) ? (int) $request->query('department') : null;

        return view('hotel.erp.reception.index', array_merge(
            [
                'hotel' => $hotel,
                'tab' => $tab,
                'receivedDepartmentId' => $departmentId,
            ],
            $this->procurementIndex->payload($hotel, $user, 'reception', $departmentId),
        ));
    }
}
