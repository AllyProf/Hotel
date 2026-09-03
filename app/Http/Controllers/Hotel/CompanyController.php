<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelCompany;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CompanyDataService;
use App\Services\CompanyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyController extends Controller
{
    public function __construct(
        private CompanyDataService $companies,
        private CompanyImportService $import,
        private ChannelManagerCodeResolver $codes,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->companies->filtersFromRequest($request);
        $billingStats = $this->companies->billingStatsByCompany($hotel, $hotelCode);

        $companyList = $this->companies->query($hotel->id, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hotel.pms.companies.index', [
            'hotel' => $hotel,
            'companies' => $companyList,
            'billingStats' => $billingStats,
            'filters' => $filters,
            'options' => $this->companies->formOptions($hotel),
            'ui' => $this->companies->uiConfig(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->companies->filtersFromRequest($request);

        $companies = $this->companies->query($hotel->id, $filters)
            ->limit(100)
            ->get()
            ->map->toPickerRow();

        return response()->json(['companies' => $companies]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('hotel.companies.index');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'gst_vat' => ['nullable', 'string', 'max:80'],
            'contracted_rates' => ['nullable', 'array'],
            'contracted_rates.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $company = $this->companies->createCompany($hotel, $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Company created successfully.',
                'company' => $company->toPickerRow(),
            ], 201);
        }

        return redirect()
            ->route('hotel.companies.index')
            ->with('success', 'Company '.$company->name.' created successfully.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $result = $this->import->import($hotel, $validated['file']->getRealPath());

        $message = "Imported {$result['imported']} company(ies), updated {$result['updated']}.";

        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }

        if ($result['errors'] !== []) {
            $message .= ' '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        return redirect()
            ->route('hotel.companies.index', $request->only(['search', 'per_page']))
            ->with($result['errors'] !== [] && $result['imported'] === 0 && $result['updated'] === 0
                ? 'warning'
                : 'success', $message);
    }

    public function template(): BinaryFileResponse
    {
        auth()->user()->hotel()->firstOrFail();
        $path = $this->import->templatePath();

        return response()->download($path, 'company-import-template.xlsx')->deleteFileAfterSend(true);
    }
}
