<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\ReportDataService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportDataService $reports,
        private ChannelManagerCodeResolver $codes,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->reports->filtersFromRequest($request);
        $filterOptions = $this->reports->filterOptions($hotel, $hotelCode);
        $submitted = $request->has('submitted');
        $result = $submitted
            ? $this->reports->generate($hotel, $hotelCode, $filters)
            : ['available' => false, 'title' => '', 'columns' => [], 'rows' => []];

        return view('hotel.pms.reports.index', [
            'hotel' => $hotel,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'ui' => $this->reports->uiConfig(),
            'result' => $result,
            'submitted' => $submitted,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->reports->filtersFromRequest($request);
        $result = $this->reports->generate($hotel, $hotelCode, $filters);

        if (! $result['available']) {
            abort(404, 'Report not available.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$result['columns']]);

        foreach ($result['rows'] as $index => $row) {
            $sheet->fromArray([array_values($row)], null, 'A'.($index + 2));
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = str($result['title'])->slug('_').'-'.now()->format('Y-m-d').'.xlsx';
        $path = storage_path('app/temp/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
