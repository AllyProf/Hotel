<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelGuest;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\GuestDataService;
use App\Services\GuestImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestController extends Controller
{
    public function __construct(
        private GuestDataService $guests,
        private GuestImportService $import,
        private ChannelManagerCodeResolver $codes,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->guests->filtersFromRequest($request);

        $this->guests->syncFromReservations($hotel, $hotelCode);

        $guestList = $this->guests->query($hotel->id, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hotel.pms.guests.index', [
            'hotel' => $hotel,
            'guests' => $guestList,
            'filters' => $filters,
            'ui' => $this->guests->uiConfig(),
        ]);
    }

    public function removeDuplicates(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);

        $this->guests->syncFromReservations($hotel, $hotelCode);
        $merged = $this->guests->removeDuplicates($hotel);

        return redirect()
            ->route('hotel.guests.index', $request->only(['search', 'per_page']))
            ->with('success', $merged > 0
                ? "Removed {$merged} duplicate guest record(s)."
                : 'No duplicate guests found.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $result = $this->import->import($hotel, $validated['file']->getRealPath());

        $message = "Imported {$result['imported']} guest(s), updated {$result['updated']}.";

        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }

        if ($result['errors'] !== []) {
            $message .= ' '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        return redirect()
            ->route('hotel.guests.index', $request->only(['search', 'per_page']))
            ->with($result['errors'] !== [] && $result['imported'] === 0 && $result['updated'] === 0
                ? 'warning'
                : 'success', $message);
    }

    public function template(): BinaryFileResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $path = $this->import->templatePath($hotel);

        return response()->download($path, 'guest-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function export(Request $request): StreamedResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->guests->filtersFromRequest($request);

        $this->guests->syncFromReservations($hotel, $hotelCode);

        $rows = $this->guests->query($hotel->id, $filters)->get();
        $filename = 'guests-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Phone', 'Email', 'Total Value', 'Previous Stays', 'Currency']);

            foreach ($rows as $guest) {
                /** @var HotelGuest $guest */
                fputcsv($handle, [
                    $guest->name,
                    $guest->phone ?? '',
                    $guest->email ?? '',
                    number_format((float) $guest->total_value, 0, '.', ''),
                    $guest->previous_stays,
                    strtoupper($guest->currency ?? ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
