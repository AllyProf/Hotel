<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelPosOrder;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    public function __construct(
        private PosOrderService $pos,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $posDepartments = $this->pos->posDepartmentsForHotel($hotel);

        if ($posDepartments->isEmpty()) {
            return redirect()
                ->route('hotel.hr.index', ['tab' => 'departments'])
                ->with('error', 'Enable “Show in POS” on at least one HR department and link it to a PMS catalog service (e.g. Bar).');
        }

        $departmentId = (int) $request->query('department', 0);
        $legacyOutlet = $request->has('outlet') ? (int) $request->query('outlet') : null;
        $department = $this->pos->resolvePosDepartment($hotel, $departmentId ?: null, $legacyOutlet);
        abort_unless($department, 404);
        $service = $this->pos->serviceForPosDepartment($department);
        abort_unless($service, 404);

        $from = $this->parseDate($request->query('from'), endOfDay: false) ?? now()->copy()->startOfDay();
        $to = $this->parseDate($request->query('to'), endOfDay: true) ?? now()->copy()->endOfDay();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $search = trim((string) $request->query('search', ''));

        $currentQuery = $this->pos->ordersQuery($hotel, $service, HotelPosOrder::STATUS_OPEN, $from, $to);
        $billedQuery = $this->pos->ordersQuery($hotel, $service, HotelPosOrder::STATUS_BILLED, $from, $to);

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $filter = fn ($q) => $q->where(function ($inner) use ($needle) {
                $inner->whereRaw('LOWER(order_no) like ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(invoice_no, "")) like ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(details, "")) like ?', [$needle])
                    ->orWhereHas('waiter', function ($waiter) use ($needle) {
                        $waiter->whereRaw('LOWER(name) like ?', [$needle]);
                    });
            });
            $filter($currentQuery);
            $filter($billedQuery);
        }

        $catalog = $this->pos->catalogPayload($service, $department);
        $taxPercent = $this->pos->taxPercentForService($service);
        $currency = $this->pos->currencyForHotel($hotel, $service);

        $stats = $this->pos->dashboardStats($hotel, $service, $from, $to);
        $waiters = $this->pos->waitersForHotel($hotel);
        $defaultWaiterUserId = $this->pos->defaultWaiterUserIdForPos($hotel, auth()->user(), $waiters);

        $currentOrders = $currentQuery->with(['lines', 'service', 'company', 'reservation.roomUnit', 'waiter.department'])->limit(100)->get();
        $billedOrders = $billedQuery->with(['lines', 'service', 'company', 'reservation.roomUnit', 'waiter.department'])->limit(100)->get();
        $ordersForView = $currentOrders->merge($billedOrders);

        return view('hotel.erp.pos.index', [
            'hotel' => $hotel,
            'posDepartments' => $posDepartments,
            'department' => $department,
            'service' => $service,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'stats' => $stats,
            'kpiCards' => $this->pos->dashboardKpiCards($stats),
            'currentOrders' => $currentOrders,
            'openOrderEditById' => $currentOrders->mapWithKeys(
                fn (HotelPosOrder $order) => [$order->id => $this->pos->orderEditPayload($order)]
            ),
            'orderViewById' => $ordersForView->mapWithKeys(
                fn (HotelPosOrder $order) => [$order->id => $this->pos->orderViewPayload($hotel, $order)]
            ),
            'billedOrders' => $billedOrders,
            'catalog' => $catalog,
            'taxPercent' => $taxPercent,
            'taxInclusive' => $this->pos->pricesTaxInclusiveForService($service),
            'currency' => $currency,
            'currencyDisplay' => $this->pos->currencyDisplayMeta($currency),
            'reservations' => $this->pos->inHouseReservations($hotel),
            'companies' => $this->pos->companiesForHotel($hotel),
            'waiters' => $waiters,
            'defaultWaiterUserId' => $defaultWaiterUserId,
            'requireWaiterOnOrder' => (bool) config('hotel_pos.require_waiter_on_order', false),
            'reportTypes' => config('hotel_pos.report_types', []),
            'paymentTypes' => config('hotel_pos.payment_types', []),
            'posUpdateOrderUrlTemplate' => str_replace(
                '/0',
                '/__ID__',
                route('hotel.pos.orders.update', ['order' => 0])
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->pos->resolveServiceForPos(
            $hotel,
            (int) $request->input('hotel_department_id') ?: null,
            (int) $request->input('hotel_pms_service_id') ?: null,
        );
        abort_unless($service, 404);

        $validated = $request->validate([
            'hotel_pms_service_id' => ['required', 'integer', Rule::in([$service->id])],
            'hotel_department_id' => ['nullable', 'integer'],
            'details' => ['nullable', 'string', 'max:255'],
            'guest_type' => ['required', 'string', Rule::in(['walk_in', 'in_house'])],
            'cm_reservation_id' => ['nullable', 'integer', 'required_if:guest_type,in_house'],
            'walk_in_label' => ['nullable', 'string', 'max:120'],
            'waiter_user_id' => ['nullable', 'integer'],
            'is_complimentary' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['required', 'string', 'max:160'],
            'lines.*.category_id' => ['nullable', 'integer'],
            'lines.*.category_name' => ['nullable', 'string', 'max:120'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.package_id' => ['nullable', 'integer'],
            'lines.*.unit_label' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $order = $this->pos->createOrder(
                $hotel,
                $service,
                $validated,
                $validated['lines'],
                auth()->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.pos.index', [
                'department' => (int) $request->input('hotel_department_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ])
            ->with('success', 'Order '.$order->order_no.' added to Current Orders.');
    }

    public function update(Request $request, HotelPosOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $service = $this->pos->resolveServiceForPos(
            $hotel,
            (int) $request->input('hotel_department_id') ?: null,
            (int) $request->input('hotel_pms_service_id') ?: null,
        );
        abort_unless($service && (int) $service->id === (int) $order->hotel_pms_service_id, 404);

        $validated = $request->validate([
            'hotel_pms_service_id' => ['required', 'integer', Rule::in([$service->id])],
            'hotel_department_id' => ['nullable', 'integer'],
            'details' => ['nullable', 'string', 'max:255'],
            'guest_type' => ['required', 'string', Rule::in(['walk_in', 'in_house'])],
            'cm_reservation_id' => ['nullable', 'integer', 'required_if:guest_type,in_house'],
            'walk_in_label' => ['nullable', 'string', 'max:120'],
            'waiter_user_id' => ['nullable', 'integer'],
            'is_complimentary' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['required', 'string', 'max:160'],
            'lines.*.category_id' => ['nullable', 'integer'],
            'lines.*.category_name' => ['nullable', 'string', 'max:120'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.package_id' => ['nullable', 'integer'],
            'lines.*.unit_label' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $order = $this->pos->updateOpenOrder(
                $hotel,
                $order,
                $service,
                $validated,
                $validated['lines'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('hotel.pos.index', [
                'department' => (int) $request->input('hotel_department_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ])
            ->with('success', 'Order '.$order->order_no.' updated.');
    }

    public function show(HotelPosOrder $order): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $order->load(['lines', 'service', 'hotel', 'reservation.roomUnit', 'company', 'creator', 'waiter.department']);

        return view('hotel.erp.pos.order-view', [
            'hotel' => $hotel,
            'order' => $order,
            'currency' => $this->pos->currencyForHotel($hotel, $order->service),
        ]);
    }

    public function bill(HotelPosOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $order = $this->pos->billOrder($hotel, $order, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order billed as invoice '.$order->invoice_no.'. It is now under Billed Orders.');
    }

    public function pay(Request $request, HotelPosOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'payment_type' => ['required', 'string', 'max:40'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'cm_reservation_id' => [
                Rule::requiredIf(fn () => $request->input('payment_type') === 'room_bill'),
                'nullable',
                'integer',
                Rule::exists('cm_reservations', 'id')->where('hotel_id', $hotel->id),
            ],
            'hotel_company_id' => [
                Rule::requiredIf(fn () => $request->input('payment_type') === 'bill_to_company'),
                'nullable',
                'integer',
                Rule::exists('hotel_companies', 'id')->where('hotel_id', $hotel->id),
            ],
            'comments' => ['nullable', 'string', 'max:500'],
            'payment_provider' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->pos->applyPayment($hotel, $order, $validated, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $order->isOpen()
            ? 'Payment recorded for '.$order->order_no.'. You can bill the order when ready.'
            : 'Payment recorded for '.$order->order_no.'.';

        return back()->with('success', $message);
    }

    public function destroy(HotelPosOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        try {
            $this->pos->cancelOpenOrder($hotel, $order);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order removed from Current Orders.');
    }

    public function report(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $departmentId = (int) $request->query('department', 0);
        $legacyOutlet = $request->has('outlet') ? (int) $request->query('outlet') : null;
        $service = $this->pos->resolveServiceForPos($hotel, $departmentId ?: null, $legacyOutlet);
        abort_unless($service, 404);

        $type = (string) $request->query('type', 'item_consumption');
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        if ($type === 'payments') {
            $rows = $this->pos->paymentsReport($hotel, $service, $from, $to);
            $total = round(collect($rows)->sum('amount'), 2);

            return response()->json(['type' => 'payments', 'rows' => $rows, 'total' => $total]);
        }

        $rows = $this->pos->itemConsumptionReport($hotel, $service, $from, $to);

        return response()->json(['type' => 'item_consumption', 'rows' => $rows]);
    }

    private function parseDate(mixed $value, bool $endOfDay = false): ?\Carbon\Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $d = \Carbon\Carbon::parse($value);

            return $endOfDay ? $d->endOfDay() : $d->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
