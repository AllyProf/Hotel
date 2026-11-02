<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelStoreConsumption;
use App\Models\HotelStoreIndent;
use App\Services\Stores\DepartmentStorePortalIndexData;
use App\Services\Stores\StoreConsumptionService;
use App\Services\Stores\StoreIndentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DepartmentStoresController extends Controller
{
    public function __construct(
        private DepartmentStorePortalIndexData $portal,
        private StoreIndentService $storeIndents,
        private StoreConsumptionService $storeConsumption,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        abort_unless($this->userCanAccessPortal($user), 403);

        $tab = (string) $request->input('tab', 'store-requests');
        $tabs = config('hotel_department_stores.tabs', []);
        if (! array_key_exists($tab, $tabs)) {
            $tab = 'store-requests';
        }

        $viewData = [
            'tab' => $tab,
            'ui' => [
                'title' => config('hotel_department_stores.title', 'Department Stores'),
                'tabs' => $tabs,
            ],
            'deptStoreRequestAwaitingReceiveCount' => $this->portal->pendingReceiveCount($hotel, $user),
        ];

        if ($tab === 'store-requests') {
            $viewData = array_merge($viewData, $this->portal->storeRequestPayload($hotel, $user, $request));
        } elseif ($tab === 'dept-stock') {
            $viewData = array_merge($viewData, $this->portal->stockPayload($hotel, $user, $request));
        } elseif ($tab === 'consumption') {
            $viewData = array_merge($viewData, $this->portal->consumptionPayload($hotel, $user, $request));
        }

        return view('hotel.erp.department-stores.index', $viewData);
    }

    public function showStoreRequest(HotelStoreIndent $indent): View
    {
        $user = auth()->user();
        $perms = $this->portal->storeRequestPermissions($user);
        abort_unless($perms['create'] || $perms['edit'] || $perms['delete'] || $perms['receive'], 403);

        $hotel = $user->hotel()->firstOrFail();
        $this->portal->assertIndentBelongsToPortal($hotel, $user, $indent);
        $indent->load(['department', 'items', 'receivedByUser']);

        return view('hotel.erp.stores.partials.indent-detail-ajax', [
            'viewStoreIndent' => $indent,
        ]);
    }

    public function storeStoreRequest(Request $request): RedirectResponse
    {
        $this->assertStoreRequestPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $validated = $this->validateStoreRequestPayload($request, $hotel);
        $departmentId = (int) $validated['hotel_department_id'];
        $this->portal->assertDepartmentAllowed($hotel, $user, $departmentId);
        $lines = $this->storeIndents->linesFromRequestRows($hotel, $validated['items']);

        try {
            $this->storeIndents->createIndent($hotel, $departmentId, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.department-stores.index', ['tab' => 'store-requests'])
            ->with('success', 'Store request submitted. The store will issue stock when approved.');
    }

    public function updateStoreRequest(Request $request, HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertStoreRequestPermission('edit');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $this->portal->assertIndentBelongsToPortal($hotel, $user, $indent);

        $validated = $this->validateStoreRequestPayload($request, $hotel);
        $departmentId = (int) $validated['hotel_department_id'];
        $this->portal->assertDepartmentAllowed($hotel, $user, $departmentId);
        $lines = $this->storeIndents->linesFromRequestRows($hotel, $validated['items']);

        try {
            $this->storeIndents->updateIndent($hotel, $indent, $departmentId, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.department-stores.index', ['tab' => 'store-requests'])
            ->with('success', 'Store request updated.');
    }

    public function destroyStoreRequest(HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertStoreRequestPermission('delete');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $this->portal->assertIndentBelongsToPortal($hotel, $user, $indent);

        try {
            $this->storeIndents->deleteIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.department-stores.index', ['tab' => 'store-requests'])
            ->with('success', 'Store request deleted.');
    }

    public function receiveStoreRequest(HotelStoreIndent $indent): RedirectResponse
    {
        $this->assertStoreRequestPermission('receive');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $this->portal->assertIndentBelongsToPortal($hotel, $user, $indent);

        try {
            $this->storeIndents->receiveIndent($hotel, $indent);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.department-stores.index', ['tab' => 'store-requests'])
            ->with('success', 'Stock received into department.');
    }

    public function showConsumption(HotelStoreConsumption $consumption): View
    {
        $keys = config('hotel_department_stores.consumption_permission_keys', []);
        abort_unless(auth()->user()->hasPermission($keys['view'] ?? 'dept_stores.consumption.view'), 403);

        $hotel = auth()->user()->hotel()->firstOrFail();
        $consumption = $this->portal->consumptionForDetail($hotel, auth()->user(), $consumption);

        return view('hotel.erp.stores.partials.consumption-detail-ajax', [
            'viewStoreConsumption' => $consumption,
            'consumptionShowRoomDistribution' => false,
        ]);
    }

    public function storeConsumption(Request $request): RedirectResponse
    {
        $this->assertConsumptionPermission('create');
        $hotel = auth()->user()->hotel()->firstOrFail();
        $user = auth()->user();
        $validated = $this->validateConsumptionPayload($request, $hotel);
        $departmentId = (int) $validated['hotel_department_id'];
        $this->portal->assertDepartmentAllowed($hotel, $user, $departmentId);
        $lines = $this->mapConsumptionItems($validated['items']);

        try {
            $consumption = $this->storeConsumption->createConsumption($hotel, $departmentId, $lines);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.department-stores.index', [
                'tab' => 'consumption',
                'view_consumption' => $consumption->id,
            ])
            ->with('success', 'Consumption '.$consumption->consumption_number.' recorded.');
    }

    private function userCanAccessPortal(\App\Models\User $user): bool
    {
        $sr = $this->portal->storeRequestPermissions($user);
        $st = $this->portal->stockPermissions($user);
        $co = $this->portal->consumptionPermissions($user);

        return ($sr['create'] || $sr['edit'] || $sr['delete'] || $sr['receive'])
            || ($st['view'] ?? false)
            || ($co['view'] ?? false)
            || ($co['create'] ?? false);
    }

    private function assertStoreRequestPermission(string $action): void
    {
        $keys = config('hotel_department_stores.store_request_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    private function assertConsumptionPermission(string $action): void
    {
        $keys = config('hotel_department_stores.consumption_permission_keys', []);
        $key = $keys[$action] ?? null;
        abort_if($key === null, 500);
        abort_unless(auth()->user()->hasPermission($key), 403);
    }

    /** @return array<string, mixed> */
    private function validateStoreRequestPayload(Request $request, \App\Models\Hotel $hotel): array
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

    /** @return array<string, mixed> */
    private function validateConsumptionPayload(Request $request, \App\Models\Hotel $hotel): array
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
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapConsumptionItems(array $rows): array
    {
        return collect($rows)->map(function (array $item) {
            return [
                'hotel_store_master_item_id' => (int) ($item['master_item_id'] ?? 0),
                'name' => $item['name'],
                'quantity' => (float) $item['quantity'],
                'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                'comments' => trim((string) ($item['comments'] ?? '')) ?: null,
            ];
        })->all();
    }
}
