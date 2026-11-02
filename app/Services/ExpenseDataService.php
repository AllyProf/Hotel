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
            ->with('purchaseOrder')
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
    public function filtersForCashBook(Request $request): array
    {
        $filters = $this->filtersFromRequest($request);
        if (! $request->filled('payment_type')) {
            $filters['payment_type'] = 'cash';
        }
        $filters['paid_type'] = '';

        return $filters;
    }

    /** @param  array<string, mixed>  $filters
     * @return array{groups: list<array<string, mixed>>, empty_message: string}
     */
    public function cashBookLedger(int $hotelId, array $filters): array
    {
        $entries = $this->cashBookEntriesQuery($hotelId, $filters)->get();
        $running = $this->cashBookOpeningBalance($hotelId, $filters['from_date'], $filters);

        $groups = [];

        foreach ($entries as $expense) {
            $dateKey = $expense->expense_date?->format('Y-m-d') ?? $expense->created_at->format('Y-m-d');
            $paidIn = $this->paidInAmount($expense);
            $paidOut = $this->paidOutAmount($expense);
            $running = round($running + $paidIn - $paidOut, 2);

            if (! isset($groups[$dateKey])) {
                $groups[$dateKey] = [
                    'date_key' => $dateKey,
                    'label' => Carbon::parse($dateKey)->format('d M'),
                    'rows' => [],
                    'closing_balance' => $running,
                ];
            }

            $groups[$dateKey]['rows'][] = [
                'id' => $expense->id,
                'time' => $expense->created_at->format('h:i A'),
                'pos' => $expense->pos_label ?: ($expense->guest_name ? 'Rooms' : '—'),
                'guest' => $expense->guest_name,
                'room_no' => $expense->room_no,
                'type' => $expense->paymentTypeLabel(),
                'category' => $expense->category,
                'expense_date' => $expense->expense_date?->format('d M Y'),
                'invoice_no' => $expense->invoice_no,
                'comments' => $expense->comments,
                'paid_in' => $paidIn,
                'paid_out' => $paidOut,
                'details_url' => $expense->details_path
                    ? asset('storage/'.$expense->details_path)
                    : null,
            ];

            $groups[$dateKey]['closing_balance'] = $running;
        }

        $groupList = collect($groups)
            ->sortByDesc('date_key')
            ->values()
            ->all();

        return [
            'groups' => $groupList,
            'empty_message' => $this->emptyMessage($filters),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function cashBookOpeningBalance(int $hotelId, string $fromDate, array $filters): float
    {
        $before = Carbon::parse($fromDate)->subDay()->format('Y-m-d');
        $priorFilters = array_merge($filters, [
            'from_date' => '1970-01-01',
            'to_date' => $before,
        ]);

        $balance = 0.0;
        foreach ($this->cashBookEntriesQuery($hotelId, $priorFilters)->get() as $expense) {
            $balance += $this->paidInAmount($expense) - $this->paidOutAmount($expense);
        }

        return round($balance, 2);
    }

    /** @param  array<string, mixed>  $filters */
    public function cashBookEntriesQuery(int $hotelId, array $filters): Builder
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
            });

        if ($filters['payment_type'] !== '') {
            $query->where('payment_type', $filters['payment_type']);
        }

        if (($filters['paid_type'] ?? '') !== '') {
            $query->where('paid_type', $filters['paid_type']);
        }

        return $query
            ->orderBy('expense_date')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function paidInAmount(HotelExpense $expense): float
    {
        if ($expense->entry_type === HotelExpense::ENTRY_DEPOSIT || $expense->paid_type === HotelExpense::PAID_IN) {
            return (float) $expense->amount;
        }

        return 0.0;
    }

    private function paidOutAmount(HotelExpense $expense): float
    {
        if ($expense->paid_type === HotelExpense::PAID_OUT && $expense->entry_type !== HotelExpense::ENTRY_DEPOSIT) {
            return (float) $expense->amount;
        }

        return 0.0;
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
            'pos_label' => $data['pos_label'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'room_no' => $data['room_no'] ?? null,
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
