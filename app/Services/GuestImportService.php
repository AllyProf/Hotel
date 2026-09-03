<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelGuest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GuestImportService
{
    public function __construct(
        private GuestDataService $guests,
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
        $defaultCurrency = strtoupper($hotel->currency ?: 'USD');

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

            $phone = $columns['phone'] !== null
                ? $this->guests->normalizePhonePublic((string) ($row[$columns['phone']] ?? ''))
                : '';
            $email = $columns['email'] !== null
                ? $this->guests->normalizeEmailPublic((string) ($row[$columns['email']] ?? ''))
                : '';

            $totalValue = null;
            $currency = $columns['currency'] !== null
                ? strtoupper(trim((string) ($row[$columns['currency']] ?? '')))
                : null;

            if ($columns['total_value'] !== null) {
                $parsed = $this->parseAmountCell((string) ($row[$columns['total_value']] ?? ''));
                $totalValue = $parsed['amount'];
                $currency = $currency ?: $parsed['currency'];
            }

            $previousStays = null;

            if ($columns['previous_stays'] !== null && ($row[$columns['previous_stays']] ?? '') !== '') {
                $previousStays = max(0, (int) $row[$columns['previous_stays']]);
            }

            $result = $this->guests->upsertImportedGuest($hotel, [
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'total_value' => $totalValue,
                'previous_stays' => $previousStays,
                'currency' => $currency ?: $defaultCurrency,
            ]);

            if ($result === 'imported') {
                $imported++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $errors[] = 'Row '.$line.': could not save guest.';
                $skipped++;
            }
        }

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    public function templatePath(Hotel $hotel): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $currency = strtoupper($hotel->currency ?: 'TZS');

        $sheet->fromArray([
            ['Name', 'Phone', 'Email', 'Total Value', 'Previous Stays', 'Currency'],
            ['John Doe', '0712345678', 'john@example.com', '50000', '1', $currency],
        ]);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = storage_path('app/temp/guest-import-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** @param  list<mixed>  $headerRow
     * @return array{name: ?int, phone: ?int, email: ?int, total_value: ?int, previous_stays: ?int, currency: ?int}
     */
    private function mapColumns(array $headerRow): array
    {
        $columns = [
            'name' => null,
            'phone' => null,
            'email' => null,
            'total_value' => null,
            'previous_stays' => null,
            'currency' => null,
        ];

        foreach ($headerRow as $index => $label) {
            $key = $this->normalizeHeader((string) $label);

            if ($key === '') {
                continue;
            }

            if (in_array($key, ['name', 'guest name', 'guest', 'customer name'], true)) {
                $columns['name'] = (int) $index;
            } elseif (in_array($key, ['phone', 'mobile', 'contact', 'phone number'], true)) {
                $columns['phone'] = (int) $index;
            } elseif (in_array($key, ['email', 'e-mail', 'email address'], true)) {
                $columns['email'] = (int) $index;
            } elseif (in_array($key, ['total value', 'total', 'value', 'amount', 'total amount'], true)) {
                $columns['total_value'] = (int) $index;
            } elseif (in_array($key, ['previous stays', 'stays', 'visits', 'bookings'], true)) {
                $columns['previous_stays'] = (int) $index;
            } elseif (in_array($key, ['currency', 'curr'], true)) {
                $columns['currency'] = (int) $index;
            }
        }

        return $columns;
    }

    /** @return array{amount: float, currency: ?string} */
    private function parseAmountCell(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['amount' => 0.0, 'currency' => null];
        }

        if (preg_match('/^([\d,.]+)\s*([A-Za-z]{3})$/', $value, $matches)) {
            return [
                'amount' => (float) str_replace(',', '', $matches[1]),
                'currency' => strtoupper($matches[2]),
            ];
        }

        return [
            'amount' => (float) str_replace(',', '', $value),
            'currency' => null,
        ];
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
