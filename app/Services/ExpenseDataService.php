<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelExpense;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ExpenseDataService
{
    public function __construct(
        private HotelSettingsService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.expenses', []);
    }

    /** @return array<string, mixed> */
    public function formOptions(Hotel $hotel): array
    {
        $this->settings->ensureDefaults($hotel);
        $settings = $hotel->settings()->first();
        $reservation = is_array($settings?->reservation) ? $settings->reservation : [];

        return [
            'payment_types' => HotelExpense::paymentTypeLabels(),
            'paid_types' => HotelExpense::paidTypeLabels(),
            'categories' => $reservation['expense_categories'] ?? [
                'Electricity Expenses',
                'Staff Expenses',
                'Purchases',
                'Maintenance Expenses',
                'Housekeeping Expenses',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $fromDate = $request->input('from_date', now()->subDays(7)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $paymentType = trim((string) $request->input('payment_type', ''));
        $paidType = trim((string) $request->input('paid_type', ''));

        if ($paymentType !== '' && ! in_array($paymentType, HotelExpense::PAYMENT_TYPES, true)) {
            $paymentType = '';
        }

        if ($paidType !== '' && ! in_array($paidType, [HotelExpense::PAID_IN, HotelExpense::PAID_OUT], true)) {
            $paidType = '';
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'payment_type' => $paymentType,
            'paid_type' => $paidType,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function query(int $hotelId, array $filters): Builder
    {
        $query = HotelExpense::query()
            ->where('hotel_id', $hotelId)
            ->where(function (Builder $builder) use ($filters) {
                $builder->whereBetween('expense_date', [$filters['from_date'], $filters['to_date']])
                    ->orWhere(function (Builder $depositQuery) use ($filters) {
                        $depositQuery->where('entry_type', HotelExpense::ENTRY_DEPOSIT)
                            ->whereBetween('created_at', [
                                Carbon::parse($filters['from_date'])->startOfDay(),
                                Carbon::parse($filters['to_date'])->endOfDay(),
                            ]);
                    });
            })
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($filters['payment_type'] !== '') {
            $query->where('payment_type', $filters['payment_type']);
        }

        if ($filters['paid_type'] !== '') {
            $query->where('paid_type', $filters['paid_type']);
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    public function emptyMessage(array $filters): string
    {
        $from = Carbon::parse($filters['from_date'])->format('d M');
        $to = Carbon::parse($filters['to_date'])->format('d M');

        $typeLabel = $filters['payment_type'] === ''
            ? 'All'
            : (HotelExpense::paymentTypeLabels()[$filters['payment_type']] ?? 'All');

        return "No {$typeLabel} payments were made between {$from} & {$to}";
    }

    /** @param  array<string, mixed>  $data */
    public function createExpense(Hotel $hotel, array $data, ?UploadedFile $details = null): HotelExpense
    {
        $expense = new HotelExpense([
            'hotel_id' => $hotel->id,
            'entry_type' => HotelExpense::ENTRY_EXPENSE,
            'paid_type' => $data['paid_type'] ?? HotelExpense::PAID_OUT,
            'payment_type' => $data['payment_type'],
            'amount' => $data['amount'],
            'category' => $data['category'] ?? null,
            'expense_date' => $data['expense_date'],
            'invoice_no' => $data['invoice_no'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'comments' => $data['comments'] ?? null,
        ]);

        if ($details) {
            $expense->details_path = $details->store('hotel-expenses/'.$hotel->id, 'public');
        }

        $expense->save();

        return $expense;
    }

    /** @param  array<string, mixed>  $data */
    public function createDeposit(Hotel $hotel, array $data, ?UploadedFile $details = null): HotelExpense
    {
        $expense = new HotelExpense([
            'hotel_id' => $hotel->id,
            'entry_type' => HotelExpense::ENTRY_DEPOSIT,
            'paid_type' => HotelExpense::PAID_IN,
            'payment_type' => $data['payment_type'],
            'amount' => $data['amount'],
            'expense_date' => now()->toDateString(),
            'comments' => $data['comments'] ?? null,
        ]);

        if ($details) {
            $expense->details_path = $details->store('hotel-expenses/'.$hotel->id, 'public');
        }

        $expense->save();

        return $expense;
    }
}
