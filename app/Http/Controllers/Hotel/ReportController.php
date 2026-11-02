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
            : ['available' => false, 'title' => '', 'columns' => [], 'rows' => [], 'summary' => null];

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

        $rowOffset = 2;
        foreach ($result['rows'] as $row) {
            unset($row['_kind']);
            $line = collect($result['columns'])->map(fn (string $column) => $row[$column] ?? '')->values()->all();
            $sheet->fromArray([$line], null, 'A'.$rowOffset);
            $rowOffset++;
        }

        foreach ($result['summary']['revenue_totals'] ?? [] as $block) {
            $rowOffset += 2;
            $sheet->setCellValue('A'.$rowOffset, ($block['currency'] ?? '').' revenue totals');
            $rowOffset++;
            foreach ($block['items'] ?? [] as $item) {
                $sheet->setCellValue('A'.$rowOffset, $item['label'] ?? '');
                $sheet->setCellValue('B'.$rowOffset, $item['value'] ?? '');
                $rowOffset++;
            }
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
