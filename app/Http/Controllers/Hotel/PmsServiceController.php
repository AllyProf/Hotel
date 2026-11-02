<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceCategory;
use App\Models\HotelPmsServicePackage;
use App\Services\HotelSettingsService;
use App\Services\PmsServiceCategoryDataService;
use App\Services\PmsServiceDataService;
use App\Services\Laundry\LaundryPriceDataService;
use App\Services\Conference\ConferenceRateDataService;
use App\Services\Swimming\SwimmingRateDataService;
use App\Services\PmsServicePackageDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class PmsServiceController extends Controller
{
    public function __construct(
        private PmsServiceDataService $services,
        private PmsServiceCategoryDataService $categories,
        private PmsServicePackageDataService $packages,
        private LaundryPriceDataService $laundryPrices,
        private SwimmingRateDataService $swimmingRates,
        private ConferenceRateDataService $conferenceRates,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        app(HotelSettingsService::class)->ensureDefaults($hotel);

        return view('hotel.pms.services.index', [
            'hotel' => $hotel,
            'cards' => $this->services->cardsForHotel($hotel),
            'ui' => $this->services->uiConfig(),
        ]);
    }

    public function show(HotelPmsService $service): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $service->loadCount(['serviceCategories', 'servicePackages']);

        $laundryPriceRows = collect();
        $swimmingRateRows = collect();
        $conferenceRateRows = collect();

        if ($service->isLaundry()) {
            if ($service->serviceCategories()->count() === 0
                && count($this->categories->categoryTemplatesForService($service)) > 0) {
                $this->categories->importAllTemplates($hotel, $service);
            }
            if ($service->servicePackages()->count() === 0
                && count($this->packages->packageTemplatesForService($service)) > 0) {
                $this->packages->importAllTemplates($hotel, $service);
            }

            $service->load(['serviceCategories', 'servicePackages']);
            $this->laundryPrices->ensureDefaultPriceRows($service);
            $this->laundryPrices->backfillMissingPackages($service);
            $laundryPriceRows = $this->laundryPrices->pricesForService($service);
        }

        if ($service->isSwimming()) {
            if ($service->serviceCategories()->count() === 0
                && count($this->categories->categoryTemplatesForService($service)) > 0) {
                $this->categories->importAllTemplates($hotel, $service);
            }

            $service->load(['serviceCategories']);
            $this->swimmingRates->ensureDefaultRateRows($service);
            $swimmingRateRows = $this->swimmingRates->ratesForService($service);
        }

        if ($service->isConference()) {
            if ($service->serviceCategories()->count() === 0
                && count($this->categories->categoryTemplatesForService($service)) > 0) {
                $this->categories->importAllTemplates($hotel, $service);
            }

            $service->load(['serviceCategories']);
            $this->conferenceRates->ensureDefaultRateRows($service);
            $conferenceRateRows = $this->conferenceRates->ratesForService($service);
        }

        return view('hotel.pms.services.show', [
            'hotel' => $hotel,
            'service' => $service,
            'cards' => $this->categories->cardsForService($service),
            'packageCards' => $this->packages->cardsForService($service),
            'hasTemplates' => count($this->categories->categoryTemplatesForService($service)) > 0,
            'hasPackageTemplates' => count($this->packages->packageTemplatesForService($service)) > 0,
            'laundryPriceRows' => $laundryPriceRows,
            'swimmingRateRows' => $swimmingRateRows,
            'conferenceRateRows' => $conferenceRateRows,
            'defaultLaundryCurrency' => $service->displayCurrency($hotel),
            'pricingUnits' => config('hotel_pms_service_templates.pricing_units', []),
            'vatRate' => app(HotelSettingsService::class)->vatRate($hotel),
            'ui' => $this->services->uiConfig(),
        ]);
    }

    public function updateConferenceRates(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->isConference(), 404);

        $confCurrencies = ConferenceRateDataService::allowedCurrencies();
        $confUnits = ConferenceRateDataService::allowedPricingUnits();

        $validated = $request->validate([
            'rates' => ['nullable', 'array'],
            'rates.*.id' => ['nullable', 'integer'],
            'rates.*.category_id' => ['nullable', 'integer'],
            'rates.*.label' => ['nullable', 'string', 'max:120'],
            'rates.*.capacity' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rates.*.pricing_unit' => ['nullable', 'string', Rule::in($confUnits)],
            'rates.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'rates.*.currency' => ['nullable', 'string', 'size:3', Rule::in($confCurrencies)],
            'rates.*.is_active' => ['nullable', 'boolean'],
        ]);

        $rows = $validated['rates'] ?? [];
        foreach ($rows as $i => $row) {
            $rows[$i]['is_active'] = $request->boolean('rates.'.$i.'.is_active');
        }

        $this->conferenceRates->syncRates($hotel, $service, $rows);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Conference rates saved.')
            ->withFragment('conference-rates');
    }

    public function updateSwimmingRates(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->isSwimming(), 404);

        $swimCurrencies = SwimmingRateDataService::allowedCurrencies();
        $swimUnits = SwimmingRateDataService::allowedPricingUnits();
        $swimAudiences = SwimmingRateDataService::allowedAudiences();

        $validated = $request->validate([
            'rates' => ['nullable', 'array'],
            'rates.*.id' => ['nullable', 'integer'],
            'rates.*.category_id' => ['nullable', 'integer'],
            'rates.*.audience' => ['nullable', 'string', Rule::in($swimAudiences)],
            'rates.*.pricing_unit' => ['nullable', 'string', Rule::in($swimUnits)],
            'rates.*.label' => ['nullable', 'string', 'max:120'],
            'rates.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'rates.*.currency' => ['nullable', 'string', 'size:3', Rule::in($swimCurrencies)],
            'rates.*.is_active' => ['nullable', 'boolean'],
        ]);

        $rows = $validated['rates'] ?? [];
        foreach ($rows as $i => $row) {
            $rows[$i]['is_active'] = $request->boolean('rates.'.$i.'.is_active');
        }

        $this->swimmingRates->syncRates($hotel, $service, $rows);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Swimming pass rates saved.')
            ->withFragment('swimming-rates');
    }

    public function updateLaundryPrices(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->isLaundry(), 404);

        $validated = $request->validate([
            'prices' => ['nullable', 'array'],
            'prices.*.id' => ['nullable', 'integer'],
            'prices.*.item_name' => ['nullable', 'string', 'max:120'],
            'prices.*.category_id' => ['nullable', 'integer'],
            'prices.*.package_id' => ['nullable', 'integer'],
            'prices.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.currency' => ['nullable', 'string', 'size:3', Rule::in(array_keys(config('currencies', [])))],
            'prices.*.is_active' => ['nullable', 'boolean'],
        ]);

        $rows = $validated['prices'] ?? [];
        foreach ($rows as $i => $row) {
            $rows[$i]['is_active'] = $request->boolean('prices.'.$i.'.is_active');
        }

        $this->laundryPrices->syncPrices($hotel, $service, $rows);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Laundry prices saved.')
            ->withFragment('laundry-prices');
    }

    public function updateSettings(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $rules = [
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(array_keys(config('currencies', [])))],
            'pricing_unit' => ['required', 'string', Rule::in(HotelPmsService::PRICING_UNITS)],
            'tax_category' => ['required', 'string', 'max:60'],
            'hsn_code' => ['nullable', 'string', 'max:40'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'visible_on_be' => ['nullable', 'boolean'],
            'amount_editable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'string', 'max:500'],
        ];

        if ($service->isParking()) {
            $rules['grace_period_minutes'] = ['nullable', 'integer', 'min:0', 'max:1440'];
            $rules['complimentary_for_inhouse'] = ['nullable', 'boolean'];
            $rules['require_plate_number'] = ['nullable', 'boolean'];
            $rules['require_vehicle_type'] = ['nullable', 'boolean'];
            $rules['collect_vehicle_notes'] = ['nullable', 'boolean'];
        }

        if ($service->isConference()) {
            $rules['business_hours_start'] = ['nullable', 'string', 'max:8'];
            $rules['business_hours_end'] = ['nullable', 'string', 'max:8'];
            $rules['allow_external_booking'] = ['nullable', 'boolean'];
            $rules['require_contact_for_external'] = ['nullable', 'boolean'];
            $rules['require_room_for_inhouse'] = ['nullable', 'boolean'];
            $rules['post_to_folio'] = ['nullable', 'boolean'];
        }

        if ($service->isSwimming()) {
            $rules['open_time'] = ['nullable', 'string', 'max:8'];
            $rules['close_time'] = ['nullable', 'string', 'max:8'];
            $rules['max_persons_per_entry'] = ['nullable', 'integer', 'min:1', 'max:99'];
            $rules['complimentary_for_inhouse'] = ['nullable', 'boolean'];
            $rules['allow_desk_complimentary_override'] = ['nullable', 'boolean'];
            $rules['require_room_for_inhouse'] = ['nullable', 'boolean'];
            $rules['require_contact_for_walkin'] = ['nullable', 'boolean'];
            $rules['post_to_folio'] = ['nullable', 'boolean'];
            $rules['allow_walk_in'] = ['nullable', 'boolean'];
        }

        if ($service->isLaundry()) {
            $rules['turnaround_hours'] = ['nullable', 'integer', 'min:1', 'max:168'];
            $rules['express_surcharge_percent'] = ['nullable', 'integer', 'min:0', 'max:500'];
            $rules['complimentary_pieces'] = ['nullable', 'integer', 'min:0', 'max:99'];
            $rules['require_room_number'] = ['nullable', 'boolean'];
            $rules['post_to_folio'] = ['nullable', 'boolean'];
            $rules['laundry_items'] = ['nullable', 'array'];
            $rules['laundry_items.*.name'] = ['nullable', 'string', 'max:120'];
        }

        $validated = $request->validate($rules);

        $validated['tax_inclusive'] = $request->boolean('tax_inclusive');
        $validated['visible_on_be'] = $request->boolean('visible_on_be', false);
        $validated['amount_editable'] = $request->boolean('amount_editable', true);
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($service->isParking()) {
            $validated['complimentary_for_inhouse'] = $request->boolean('complimentary_for_inhouse');
            $validated['require_plate_number'] = $request->boolean('require_plate_number');
            $validated['require_vehicle_type'] = $request->boolean('require_vehicle_type');
            $validated['collect_vehicle_notes'] = $request->boolean('collect_vehicle_notes');
            $validated['grace_period_minutes'] = (int) $request->input('grace_period_minutes', 0);
        }

        if ($service->isConference()) {
            $validated['allow_external_booking'] = $request->boolean('allow_external_booking');
            $validated['require_contact_for_external'] = $request->boolean('require_contact_for_external');
            $validated['require_room_for_inhouse'] = $request->boolean('require_room_for_inhouse');
            $validated['post_to_folio'] = $request->boolean('post_to_folio');
        }

        if ($service->isSwimming()) {
            $validated['complimentary_for_inhouse'] = $request->boolean('complimentary_for_inhouse');
            $validated['allow_desk_complimentary_override'] = $request->boolean('allow_desk_complimentary_override');
            $validated['require_room_for_inhouse'] = $request->boolean('require_room_for_inhouse');
            $validated['require_contact_for_walkin'] = $request->boolean('require_contact_for_walkin');
            $validated['post_to_folio'] = $request->boolean('post_to_folio');
            $validated['allow_walk_in'] = $request->boolean('allow_walk_in');
            $validated['max_persons_per_entry'] = (int) $request->input('max_persons_per_entry', 10);
        }

        if ($service->isLaundry()) {
            $validated['turnaround_hours'] = (int) $request->input('turnaround_hours', 24);
            $validated['express_surcharge_percent'] = (int) $request->input('express_surcharge_percent', 0);
            $validated['complimentary_pieces'] = (int) $request->input('complimentary_pieces', 0);
            $validated['require_room_number'] = $request->boolean('require_room_number');
            $validated['post_to_folio'] = $request->boolean('post_to_folio');
            $validated['laundry_items'] = $request->input('laundry_items', []);
        }

        $this->services->updateSettings($service, $validated);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Service settings saved successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $service = $this->services->createCustom($hotel, $validated['name']);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Service added. You can now add categories.');
    }

    public function storeFromTemplate(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'template_key' => ['required', 'string', Rule::in(array_keys(config('hotel_pms_service_templates.templates', [])))],
        ]);

        try {
            $service = $this->services->createFromTemplate($hotel, $validated['template_key']);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('hotel.services.index')
                ->with('error', $exception->getMessage());
        }

        $message = match (true) {
            $service->isLaundry() => 'Laundry added with categories, packages, and default items.',
            $service->isSwimming() => 'Swimming added with pass categories. Set rates and pool rules below.',
            $service->isConference() => 'Conference added with event categories. Set packages and desk rules below.',
            $service->showsCatalog() => 'Service added with its category templates.',
            default => 'Service added. Configure pricing and settings below.',
        };

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', $message);
    }

    public function destroy(HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $this->services->deleteService($service);

        return redirect()
            ->route('hotel.services.index')
            ->with('success', 'Service removed successfully.');
    }

    public function storeCategory(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $this->categories->createCustom($hotel, $service, $validated['name']);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Category added successfully.');
    }

    public function storeCategoryFromTemplate(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $templateKeys = array_keys($this->categories->categoryTemplatesForService($service));

        $validated = $request->validate([
            'template_key' => ['required', 'string', Rule::in($templateKeys)],
        ]);

        try {
            $this->categories->createFromTemplate($hotel, $service, $validated['template_key']);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('hotel.services.show', $service)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Category added successfully.');
    }

    public function importCategories(HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);

        $imported = $this->categories->importAllTemplates($hotel, $service);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', $imported > 0
                ? "{$imported} categor".($imported === 1 ? 'y' : 'ies').' imported.'
                : 'All category templates are already added.');
    }

    public function updateCategory(Request $request, HotelPmsService $service, HotelPmsServiceCategory $category): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless((int) $category->hotel_pms_service_id === (int) $service->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $this->categories->updateCategory($category, $validated);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Category updated successfully.');
    }

    public function destroyCategory(HotelPmsService $service, HotelPmsServiceCategory $category): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless((int) $category->hotel_pms_service_id === (int) $service->id, 404);

        $this->categories->deleteCategory($category);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Category removed successfully.');
    }

    public function storePackage(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->usesPackages(), 404);

        $validated = $request->validate([
            'package_name' => ['required', 'string', 'max:120'],
            'package_abbreviation' => ['nullable', 'string', 'max:20'],
        ]);

        $this->packages->createCustom(
            $hotel,
            $service,
            $validated['package_name'],
            $validated['package_abbreviation'] ?? null
        );

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Package added successfully.')
            ->withFragment('packages');
    }

    public function storePackageFromTemplate(Request $request, HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->usesPackages(), 404);

        $templateKeys = array_keys($this->packages->packageTemplatesForService($service));

        $validated = $request->validate([
            'template_key' => ['required', 'string', Rule::in($templateKeys)],
        ]);

        try {
            $this->packages->createFromTemplate($hotel, $service, $validated['template_key']);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('hotel.services.show', $service)
                ->with('error', $exception->getMessage())
                ->withFragment('packages');
        }

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Package added successfully.')
            ->withFragment('packages');
    }

    public function importPackages(HotelPmsService $service): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->usesPackages(), 404);

        $imported = $this->packages->importAllTemplates($hotel, $service);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', $imported > 0
                ? "{$imported} packag".($imported === 1 ? 'e' : 'es').' imported.'
                : 'All package templates are already added.')
            ->withFragment('packages');
    }

    public function updatePackage(Request $request, HotelPmsService $service, HotelPmsServicePackage $package): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->usesPackages(), 404);
        abort_unless((int) $package->hotel_pms_service_id === (int) $service->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $this->packages->updatePackage($package, $validated);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Package updated successfully.')
            ->withFragment('packages');
    }

    public function destroyPackage(HotelPmsService $service, HotelPmsServicePackage $package): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $service->hotel_id === (int) $hotel->id, 404);
        abort_unless($service->usesPackages(), 404);
        abort_unless((int) $package->hotel_pms_service_id === (int) $service->id, 404);

        $this->packages->deletePackage($package);

        return redirect()
            ->route('hotel.services.show', $service)
            ->with('success', 'Package removed successfully.')
            ->withFragment('packages');
    }
}
