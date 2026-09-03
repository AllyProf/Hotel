<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelCompany;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CompanyImportService
{
    public function __construct(
        private CompanyDataService $companies,
    ) {}

    /** @return array{imported: int, updated: int, skipped: int, errors: list<string>} */
    public function import(Hotel $hotel, string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if ($rows === []) {
            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['The file is empty.'],
            ];
        }

        $headerRow = array_shift($rows);
        $columns = $this->mapColumns(is_array($headerRow) ? $headerRow : []);

        if ($columns['name'] === null) {
            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Missing required "Name" column in the first row.'],
            ];
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            $name = trim((string) ($row[$columns['name']] ?? ''));

            if ($name === '') {
                $skipped++;

                continue;
            }

            $payload = [
                'name' => $name,
                'contact_person' => $columns['contact_person'] !== null
                    ? trim((string) ($row[$columns['contact_person']] ?? '')) ?: null
                    : null,
                'email' => $columns['email'] !== null
                    ? trim((string) ($row[$columns['email']] ?? '')) ?: null
                    : null,
                'phone' => $columns['phone'] !== null
                    ? trim((string) ($row[$columns['phone']] ?? '')) ?: null
                    : null,
                'address' => $columns['address'] !== null
                    ? trim((string) ($row[$columns['address']] ?? '')) ?: null
                    : null,
                'gst_vat' => $columns['gst_vat'] !== null
                    ? trim((string) ($row[$columns['gst_vat']] ?? '')) ?: null
                    : null,
            ];

            $existing = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                HotelCompany::query()->create([
                    'hotel_id' => $hotel->id,
                    ...$payload,
                    'contracted_rates' => [],
                ]);
                $imported++;
            }
        }

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    public function templatePath(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Name', 'Contact Person', 'Email', 'Phone', 'Address', 'GST/ VAT'],
            ['Acme Travel Ltd', 'Jane Doe', 'billing@acme.com', '0712345678', 'Dar es Salaam', 'VAT-12345'],
        ]);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = storage_path('app/temp/company-import-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** @param  list<mixed>  $headerRow
     * @return array{name: ?int, contact_person: ?int, email: ?int, phone: ?int, address: ?int, gst_vat: ?int}
     */
    private function mapColumns(array $headerRow): array
    {
        $columns = [
            'name' => null,
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'gst_vat' => null,
        ];

        foreach ($headerRow as $index => $label) {
            $key = $this->normalizeHeader((string) $label);

            if ($key === '') {
                continue;
            }

            if (in_array($key, ['name', 'company name', 'company'], true)) {
                $columns['name'] = (int) $index;
            } elseif (in_array($key, ['contact person', 'contact', 'contact name'], true)) {
                $columns['contact_person'] = (int) $index;
            } elseif (in_array($key, ['email', 'e-mail'], true)) {
                $columns['email'] = (int) $index;
            } elseif (in_array($key, ['phone', 'mobile', 'contact number'], true)) {
                $columns['phone'] = (int) $index;
            } elseif ($key === 'address') {
                $columns['address'] = (int) $index;
            } elseif (in_array($key, ['gst/ vat', 'gst/vat', 'gst', 'vat', 'tax id'], true)) {
                $columns['gst_vat'] = (int) $index;
            }
        }

        return $columns;
    }

    /** @param  list<mixed>  $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $label): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $label) ?? ''));
    }
}
