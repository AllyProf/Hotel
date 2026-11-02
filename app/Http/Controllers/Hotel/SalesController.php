<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\Bar\BarCatalogService;
use App\Services\Bar\BarPurchaseListService;
use App\Services\Bar\BarSaleService;
use App\Services\FrontDeskPaymentService;
use App\Services\PmsServiceItemDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function __construct(
        private BarCatalogService $bar,
        private PmsServiceItemDataService $items,
        private BarSaleService $barSales,
        private BarPurchaseListService $purchaseLists,
        private FrontDeskPaymentService $deskPayments,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $service = $this->bar->barServiceForHotel($hotel);

        if (! $service) {
            return redirect()
                ->route('hotel.services.index')
                ->with('error', 'Add the Bar service under PMS → Services first.');
        }

        $this->bar->ensureCatalogReady($hotel, $service);
        $service->load(['serviceCategories', 'servicePackages']);

        $catalogItems = $this->items->itemsForService($service);
        $view = $request->query('view', 'list');
        if (! in_array($view, ['list', 'grid'], true)) {
            $view = 'list';
        }

        $barSettings = $service->barSettings();

        return view('sales.create', [
            'hotel' => $hotel,
            'service' => $service,
            'barSettings' => $barSettings,
            'catalogView' => $view,
            'inHouseReservations' => $this->barSales->inHouseReservations($hotel),
            'sellCatalog' => $this->barSales->sellCatalogFromItems($catalogItems),
            'sellCurrency' => $this->purchaseLists->budgetCurrencyForHotel($hotel),
            'paymentOptions' => $this->deskPaymentViewOptions($hotel, $service),
        ]);
    }

    /** @return array<string, mixed> */
    private function deskPaymentViewOptions($hotel, $service): array
    {
        $walkInModes = $this->deskPayments->walkInPaymentModes($hotel);

        return array_merge($this->deskPayments->optionsForHotel($hotel), [
            'currency' => $this->purchaseLists->budgetCurrencyForHotel($hotel),
            'walk_in_payment_modes' => $walkInModes,
            'default_payment_mode' => $walkInModes[0] ?? 'Cash',
        ]);
    }
}
