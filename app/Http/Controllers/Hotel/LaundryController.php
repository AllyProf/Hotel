<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelLaundryOrder;
use App\Services\FrontDeskPaymentService;
use App\Services\DepartmentProcurementIndexData;
use App\Services\Laundry\LaundryOrderService;
use App\Services\Laundry\LaundryPriceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class LaundryController extends Controller
{
    public function __construct(
        private LaundryOrderService $orders,
        private LaundryPriceDataService $prices,
        private FrontDeskPaymentService $deskPayments,
        private DepartmentProcurementIndexData $procurementIndex,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->orders->laundryServiceForHotel($hotel);

        if (! $service) {
            return redirect()
                ->route('hotel.services.index')
                ->with('error', 'Add the Laundry service under PMS → Services first.');
        }

        $tab = $request->input('tab', 'progressing');
        if ($tab === 'active') {
            $tab = 'progressing';
        }
        if ($tab === 'ready') {
            $tab = 'done';
        }
        if ($tab === 'market') {
            $tab = 'purchase-lists';
        }

        $procTabs = ['purchase-lists', 'received', 'stock-receive'];
        $isProcurementTab = in_array($tab, $procTabs, true);
        $departmentId = is_numeric($request->query('department')) ? (int) $request->query('department') : null;

        $orders = $isProcurementTab
            ? collect()
            : $this->orders->ordersQuery($hotel, $tab)->limit(100)->get();

        $viewData = [
            'hotel' => $hotel,
            'service' => $service,
            'tab' => $tab,
            'orders' => $orders,
            'ui' => $this->orders->uiConfig(),
            'isProcurementTab' => $isProcurementTab,
            'receivedDepartmentId' => $departmentId,
        ];

        if ($isProcurementTab) {
            $viewData = array_merge($viewData, $this->procurementIndex->payload($hotel, auth()->user(), 'laundry', $departmentId));
        }

        return view('hotel.pms.laundry.index', $viewData);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->orders->laundryServiceForHotel($hotel);
        $prefillReservationId = (int) $request->input('cm_reservation_id', 0);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Add the Laundry service first.');
        }

        $this->prices->ensureDefaultPriceRows($service);
        $service->load(['serviceCategories', 'servicePackages', 'laundryPrices.category', 'laundryPrices.package']);

        return view('hotel.pms.laundry.create', [
            'hotel' => $hotel,
            'service' => $service,
            'reservations' => $this->orders->inHouseReservations($hotel),
            'prices' => $service->laundryPrices->where('is_active', true)->values(),
            'settings' => $service->laundrySettings(),
            'defaultCurrency' => $service->displayCurrency($hotel),
            'prefillReservationId' => $prefillReservationId,
            'ui' => $this->orders->uiConfig(),
            'paymentOptions' => $this->deskPaymentViewOptions($hotel, $service),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->orders->laundryServiceForHotel($hotel);

        if (! $service) {
            return redirect()->route('hotel.services.index')->with('error', 'Laundry service not found.');
        }

        $validated = $request->validate([
            'cm_reservation_id' => ['nullable', 'integer'],
            'room_no' => ['nullable', 'string', 'max:20'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'is_express' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.price_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['nullable', 'string', 'max:120'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.item_notes' => ['nullable', 'string', 'max:500'],
            'payment_mode' => ['nullable', 'string', 'max:80'],
            'payment_provider' => ['nullable', 'string', 'max:120'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->orders->createOrder(
                $hotel,
                $service,
                $validated,
                $validated['lines'],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('hotel.laundry.index', ['tab' => 'progressing'])
            ->with('success', 'Ticket '.$order->ticket_no.' created — status Progressing.');
    }

    public function print(HotelLaundryOrder $order): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $order->load(['lines.category', 'service', 'creator', 'completer']);

        return view('hotel.pms.laundry.print', [
            'hotel' => $hotel,
            'order' => $order,
        ]);
    }

    public function show(HotelLaundryOrder $order): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $order->load(['lines.category', 'reservation.roomUnit', 'service', 'creator', 'completer']);

        return view('hotel.pms.laundry.show', [
            'hotel' => $hotel,
            'order' => $order,
            'expectedReady' => $this->orders->expectedReadyAt($order->service, $order->received_at ?? now()),
            'ui' => $this->orders->uiConfig(),
        ]);
    }

    public function updateStatus(Request $request, HotelLaundryOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        try {
            $this->orders->updateStatus($order, $validated['status'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', 'Ticket marked done.');
    }

    /** @return array<string, mixed> */
    private function deskPaymentViewOptions($hotel, $service): array
    {
        $walkInModes = $this->deskPayments->walkInPaymentModes($hotel);

        return array_merge($this->deskPayments->optionsForHotel($hotel), [
            'currency' => $service->displayCurrency($hotel),
            'walk_in_payment_modes' => $walkInModes,
            'default_payment_mode' => $walkInModes[0] ?? 'Cash',
        ]);
    }

    public function postFolio(HotelLaundryOrder $order): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $order->hotel_id === (int) $hotel->id, 404);

        $order->load('reservation');
        if (! $order->reservation) {
            return redirect()->back()->with('error', 'This ticket is not linked to a reservation.');
        }

        $this->orders->postToFolio($order, $order->reservation);

        return redirect()
            ->route('hotel.laundry.show', $order)
            ->with('success', 'Charge posted to guest folio.');
    }
}
