<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelHousekeepingTask;
use App\Models\HotelMaintenanceOrder;
use App\Models\HotelStoreConsumption;
use App\Models\HotelStoreIndent;
use App\Services\DepartmentProcurementIndexData;
use App\Services\Erp\HkDepartmentStoresIndexData;
use App\Services\Erp\HkStoreRequestIndexData;
use App\Services\Erp\HousekeepingMaintenanceService;
use App\Services\Stores\StoreConsumptionService;
use App\Services\Stores\StoreIndentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class MaintenanceHkController extends Controller
{
    public function __construct(
        private HousekeepingMaintenanceService $erp,
        private DepartmentProcurementIndexData $procurementIndex,
        private HkStoreRequestIndexData $hkStoreRequests,
        private HkDepartmentStoresIndexData $hkDepartmentStores,
        private StoreIndentService $storeIndents,
        private StoreConsumptionService $storeConsumption,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $tab = (string) $request->input('tab', 'housekeeping');
        if ($tab === 'market') {
            $tab = 'purchase-lists';
        }
        $tab = $this->resolveTab($tab);
        $filter = in_array($request->input('filter'), ['open', 'done', 'all'], true)
            ? (string) $request->input('filter')
            : 'open';

        $procTabs = ['purchase-lists', 'received', 'stock-receive'];
        $departmentId = is_numeric($request->query('department')) ? (int) $request->query('department') : null;

        $payload = match ($tab) {
            'maintenance' => $this->erp->maintenanceDashboard($hotel, $filter),
            'lost_found' => ['items' => $this->erp->lostFoundItems($hotel, $filter)],
            'rooms' => $this->erp->roomBoard($hotel),
            default => in_array($tab, $procTabs, true)
                ? []
                : $this->erp->housekeepingDashboard($hotel, $filter),
        };

        $viewData = [
            'hotel' => $hotel,
            'ui' => $this->erp->uiConfig(),
            'tab' => $tab,
            'filter' => $filter,
            'payload' => $payload,
            'staffOptions' => $this->erp->staffOptions($hotel),
            'roomUnits' => $this->erp->roomUnitOptions($hotel),
            'isProcurementTab' => in_array($tab, $procTabs, true),
            'receivedDepartmentId' => $departmentId,
        ];

        if (in_array($tab, $procTabs, true)) {
            $viewData = array_merge($viewData, $this->procurementIndex->payload($hotel, $user, 'hk', $departmentId));
        }

        $viewData['hkStoreRequestAwaitingReceiveCount'] = $this->hkStoreRequests->pendingReceiveCount($hotel);

        if ($tab === 'store-requests') {
            $viewData = array_merge($viewData, $this->hkStoreRequests->payload($hotel, $user, $request));
            $viewData['storeIndentPermissions'] = $viewData['hkStoreRequestPermissions'];
        }

        if ($tab === 'dept-stock') {
            $viewData = array_merge($viewData, $this->hkDepartmentStores->stockPayload($hotel, $user, $request));
        }

        if ($tab === 'consumption') {
            $viewData = array_merge($viewData, $this->hkDepartmentStores->consumptionPayload($hotel, $user, $request));
            $viewData['storeConsumptionPermissions'] = $viewData['hkConsumptionPermissions'];
        }

        return view('hotel.erp.maintenance-hk.index', $viewData);
    }

    public function showConsumption(HotelStoreConsumption $consumption): View
    {
        $keys = config('hotel_erp_hk.consumption_permission_keys', []);
        abort_unless(auth()->user()->hasPermission($keys['view'] ?? 'hk.consumption.view'), 403);

        $hotel = auth()->user()->hotel()->firstOrFail();
        $consumption = $this->hkDepartmentStores->consumptionForDetail($hotel, $consumption);

        return view('hotel.erp.stores.partials.consumption-detail-ajax', [
            'viewStoreConsumption' => $consumption,
            'consumptionShowRoomDistribution' => true,
        ]);
    }

    public function showStoreRequest(HotelStoreIndent $indent): View
    {
        $user = auth()->user();
        $perms = $this->hkStoreRequests->permissions($user);
        abort_unless($perms['create'] || $perms['edit'] || $perms['delete'] || $perms['receive'], 403);

        $hotel = $user->hotel()->firstOrFail();
        $this->hkStoreRequests->assertIndentBelongsToHk($hotel, $indent);
        $indent->load(['department', 'items', 'receivedByUser']);

        return view('hotel.erp.stores.partials.indent-detail-ajax', [
            'viewStoreIndent' => $indent,
        ]);
    }

    public function storeConsumption(Request $request): RedirectResponse
    {
        $this->assertHkConsumptionPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validateHkConsumptionPayload($request, $hotel);
        $departmentId = (int) $validated['hotel_department_id'];
        $this->hkDepartmentStores->assertDepartmentAllowed($hotel, $departmentId);
        $lines = $this->mapHkConsumptionItems($validated['items']);

        try {
            $consumption = $this->storeConsumption->createConsumption($hotel, $departmentId, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.maintenance-hk.index', [
                'tab' => 'consumption',
                'view_consumption' => $consumption->id,
            ])
            ->with('success', 'Consumption '.$consumption->consumption_number.' recorded.');
    }

    public function storeStoreRequest(Request $request): RedirectResponse
    {
        $this->assertHkStoreRequestPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $validated = $this->validateHkStoreRequestPayload($request, $hotel);
        $departmentId = (int) $validated['hotel_department_id'];
        $this->hkStoreRequests->assertDepartmentAllowed($hotel, $departmentId);
        $lines = $this->storeIndents->linesFromRequestRows($hotel, $validated['items']);

        try {
            $this->storeIndents->createIndent($hotel, $departmentId, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.maintenance-hk.index', ['tab' => 'store-requests'])
            ->with('success', 'Store request submitted. The store will issue stock when approved.');
    }

    public function updateStoreRequest(Request $request, HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertHkStoreRequestPermission('edit');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->hkStoreRequests->assertIndentBelongsToHk($hotel, $indent);

        $validated = $this->validateHkStoreRequestPayload($request, $hotel);
        $departmentId = (int) $validated['hotel_department_id'];
        $this->hkStoreRequests->assertDepartmentAllowed($hotel, $departmentId);
        $lines = $this->storeIndents->linesFromRequestRows($hotel, $validated['items']);

        try {
            $this->storeIndents->updateIndent($hotel, $indent, $departmentId, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.maintenance-hk.index', ['tab' => 'store-requests'])
            ->with('success', 'Store request updated.');
    }

    public function destroyStoreRequest(HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertHkStoreRequestPermission('delete');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->hkStoreRequests->assertIndentBelongsToHk($hotel, $indent);

        try {
            $this->storeIndents->deleteIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.maintenance-hk.index', ['tab' => 'store-requests'])
            ->with('success', 'Store request deleted.');
    }

    public function receiveStoreRequest(HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertHkStoreRequestPermission('receive');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->hkStoreRequests->assertIndentBelongsToHk($hotel, $indent);

        try {
            $this->storeIndents->receiveIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $indent->load('department');

        return redirect()
            ->route('hotel.maintenance-hk.index', ['tab' => 'store-requests'])
            ->with('success', 'Received into '.($indent->department?->name ?? 'department').' stock.');
    }

    public function startTask(Request $request, HotelHousekeepingTask $task): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $request->validate([
            'assigned_to' => ['nullable', 'integer'],
        ]);

        try {
            $this->erp->startHousekeepingTask(
                $hotel,
                $task,
                $request->filled('assigned_to') ? (int) $request->input('assigned_to') : null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Housekeeping task started.');
    }

    public function completeTask(HotelHousekeepingTask $task): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->erp->completeHousekeepingTask($hotel, $task);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Room marked cleaned.');
    }

    public function storeMaintenanceOrder(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:32'],
            'priority' => ['required', 'string', 'max:16'],
            'hotel_room_unit_id' => ['nullable', 'integer'],
            'room_no' => ['nullable', 'string', 'max:64'],
            'assigned_to' => ['nullable', 'integer'],
            'blocks_room' => ['nullable', 'boolean'],
        ]);

        $data['blocks_room'] = $request->boolean('blocks_room');

        $this->erp->createMaintenanceOrder($hotel, $data);

        return redirect()
            ->route('hotel.maintenance-hk.index', ['tab' => 'maintenance'])
            ->with('success', 'Maintenance work order created.');
    }

    public function updateMaintenanceOrder(Request $request, HotelMaintenanceOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'status' => ['required', 'string', 'max:32'],
        ]);

        try {
            $this->erp->updateMaintenanceStatus($hotel, $order, $data['status']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Work order updated.');
    }

    public function updateRoomStatus(Request $request, int $unit): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $data = $request->validate([
            'status' => ['required', 'string', 'max:32'],
        ]);

        try {
            $this->erp->setRoomStatus($hotel, $unit, $data['status']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Room status updated.');
    }

    private function resolveTab(string $tab): string
    {
        $tabs = config('hotel_erp_hk.tabs', []);

        return array_key_exists($tab, $tabs) ? $tab : 'housekeeping';
    }

    private function assertHkStoreRequestPermission(string $action): void
    {
        $keys = config('hotel_erp_hk.store_request_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    private function assertHkConsumptionPermission(string $action): void
    {
        $keys = config('hotel_erp_hk.consumption_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    /** @return array<string, mixed> */
    private function validateHkConsumptionPayload(Request $request, \App\Models\Hotel $hotel): array
    {
        return $request->validate([
            'hotel_department_id' => [
                'required',
                'integer',
                Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.master_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.comments' => ['nullable', 'string', 'max:500'],
            'items.*.room_all' => ['nullable', 'boolean'],
            'items.*.room_unit_ids' => ['nullable', 'array'],
            'items.*.room_unit_ids.*' => ['integer'],
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapHkConsumptionItems(array $rows): array
    {
        return collect($rows)->map(function (array $item) {
            $masterId = (int) ($item['master_item_id'] ?? 0);
            $unitIds = $item['room_unit_ids'] ?? [];
            if (! is_array($unitIds)) {
                $unitIds = [];
            }

            return [
                'hotel_store_master_item_id' => $masterId,
                'room_all' => filter_var($item['room_all'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'room_unit_ids' => array_values(array_filter(array_map('intval', $unitIds))),
                'name' => $item['name'],
                'quantity' => (float) $item['quantity'],
                'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                'comments' => trim((string) ($item['comments'] ?? '')) ?: null,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function validateHkStoreRequestPayload(Request $request, \App\Models\Hotel $hotel): array
    {
        return $request->validate([
            'hotel_department_id' => [
                'required',
                'integer',
                Rule::exists('hotel_departments', 'id')->where('hotel_id', $hotel->id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.master_item_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.comments' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
