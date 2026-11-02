<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelDepartment;
use App\Models\User;
use App\Services\Hr\HotelHrService;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function __construct(
        private HotelHrService $hr,
        private PosOrderService $pos,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->hr->ensureDefaults($hotel);

        $filters = $this->hr->filtersFromRequest($request);
        $ui = $this->hr->uiConfig();
        $tab = $filters['tab'];

        $payload = match ($tab) {
            'departments' => [
                'departments' => $this->hr->departments($hotel),
                'posCatalogServices' => $this->pos->catalogServicesForPosLink($hotel),
            ],
            'roles' => [
                'roles' => $hotel->roles()->withCount('users')->orderBy('name')->get(),
            ],
            'attendance' => [
                'attendance' => $this->hr->attendanceSheet($hotel, $filters['work_date']),
            ],
            default => [
                'staff' => $this->hr->staffQuery($hotel, $filters)->paginate(15)->withQueryString(),
            ],
        };

        return view('hotel.erp.hr.index', [
            'hotel' => $hotel,
            'ui' => $ui,
            'filters' => $filters,
            'stats' => $this->hr->teamStats($hotel),
            'payload' => $payload,
            'attendanceStatuses' => $ui['attendance_statuses'] ?? [],
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        try {
            $this->hr->createDepartment($hotel, $data['name']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.hr.index', ['tab' => 'departments'])
            ->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, HotelDepartment $department): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'show_in_pos' => ['nullable', 'boolean'],
            'hotel_pms_service_id' => ['nullable', 'integer'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_pos'] = $request->boolean('show_in_pos');
        $data['hotel_pms_service_id'] = $request->input('hotel_pms_service_id');

        try {
            $this->hr->updateDepartment($hotel, $department, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.hr.index', ['tab' => 'departments'])
            ->with('success', 'Department updated.');
    }

    public function destroyDepartment(HotelDepartment $department): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->hr->deleteDepartment($hotel, $department);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.hr.index', ['tab' => 'departments'])
            ->with('success', 'Department removed.');
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'user_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->hr->saveAttendance(
                $hotel,
                (int) $data['user_id'],
                $data['work_date'],
                $data['status'],
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('hotel.hr.index', [
                'tab' => 'attendance',
                'work_date' => $data['work_date'],
            ])
            ->with('success', 'Attendance saved.');
    }
}
