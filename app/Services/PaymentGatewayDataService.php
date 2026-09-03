<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelPaymentLink;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PaymentGatewayDataService
{
    public function __construct(
        private HotelSettingsService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.payment_gateway', []);
    }

    /** @return array<string, string> */
    public function orderStatusOptions(): array
    {
        return [
            '' => 'All',
            'paid' => 'Paid',
            'pending' => 'Pending',
            'unpaid' => 'Unpaid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
        ];
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $defaultDays = (int) ($ui['default_date_range_days'] ?? 7);
        $fromDate = $request->input('from_date', now()->subDays($defaultDays)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $orderStatus = strtolower(trim((string) $request->input('order_status', '')));
        $allowedStatuses = ['paid', 'pending', 'unpaid', 'failed', 'refunded'];

        if ($orderStatus !== '' && ! in_array($orderStatus, $allowedStatuses, true)) {
            $orderStatus = '';
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'transaction_id' => trim((string) $request->input('transaction_id', '')),
            'booking_id' => trim((string) $request->input('booking_id', '')),
            'guest_name' => trim((string) $request->input('guest_name', '')),
            'order_status' => $orderStatus,
            'payment_link' => trim((string) $request->input('payment_link', '')),
            'per_page' => $this->perPageFromRequest($request),
            'submitted' => $request->has('submitted'),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function query(int $hotelId, string $hotelCode, array $filters): Builder
    {
        $query = CmReservation::query()
            ->where(function (Builder $builder) use ($hotelId, $hotelCode) {
                $builder->where('hotel_id', $hotelId)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED);

        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }

        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        if ($filters['booking_id'] !== '') {
            $query->where('booking_id', 'like', '%'.$filters['booking_id'].'%');
        }

        if ($filters['guest_name'] !== '') {
            $term = '%'.$filters['guest_name'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('guest_first_name', 'like', $term)
                    ->orWhere('guest_last_name', 'like', $term);
            });
        }

        if ($filters['transaction_id'] !== '') {
            $term = '%'.$filters['transaction_id'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('booking_id', 'like', $term)
                    ->orWhere('payload->transactionId', 'like', $term)
                    ->orWhere('payload->transaction_id', 'like', $term)
                    ->orWhere('payload->paymentId', 'like', $term)
                    ->orWhere('payload->payment_id', 'like', $term);
            });
        }

        if ($filters['payment_link'] !== '') {
            $term = '%'.$filters['payment_link'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('payload->paymentLink', 'like', $term)
                    ->orWhere('payload->payment_link', 'like', $term)
                    ->orWhere('payload->paymentUrl', 'like', $term)
                    ->orWhere('payload->payment_url', 'like', $term);
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @param  array<string, mixed>  $filters */
    public function unsettledRows(int $hotelId, string $hotelCode, array $filters): Collection
    {
        $filters = array_merge($filters, ['order_status' => '']);

        return $this->rows($hotelId, $hotelCode, $filters)
            ->filter(function (array $row) {
                $unsettled = ($row['settlement_date'] ?? '—') === '—';

                return $unsettled && in_array($row['order_status'] ?? '', ['paid', 'pending'], true);
            })
            ->values();
    }

    /** @return array<string, string|null> */
    public function bankDetails(Hotel $hotel): array
    {
        return [
            'bank_name' => $hotel->bank_name,
            'bank_account_name' => $hotel->bank_account_name,
            'bank_account_no' => $hotel->bank_account_no,
            'bank_ifsc' => $hotel->bank_ifsc,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function updateBankDetails(Hotel $hotel, array $data): Hotel
    {
        $hotel->update([
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'bank_ifsc' => $data['bank_ifsc'] ?? null,
        ]);

        return $hotel->fresh();
    }

    /** @param  array<string, mixed>  $filters */
    public function rows(int $hotelId, string $hotelCode, array $filters): Collection
    {
        $reservationRows = $this->query($hotelId, $hotelCode, $filters)
            ->get()
            ->map(fn (CmReservation $reservation) => $this->mapRow($reservation));

        $linkQuery = HotelPaymentLink::query()->where('hotel_id', $hotelId);

        if (! empty($filters['from_date'])) {
            $linkQuery->where('sent_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }

        if (! empty($filters['to_date'])) {
            $linkQuery->where('sent_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        if ($filters['guest_name'] !== '') {
            $term = '%'.$filters['guest_name'].'%';
            $linkQuery->where('guest_name', 'like', $term);
        }

        if ($filters['booking_id'] !== '') {
            $linkQuery->where('invoice_id', 'like', '%'.$filters['booking_id'].'%');
        }

        if ($filters['payment_link'] !== '') {
            $linkQuery->where('payment_link', 'like', '%'.$filters['payment_link'].'%');
        }

        $linkRows = $linkQuery->orderByDesc('sent_at')->get()->map(fn (HotelPaymentLink $link) => $this->mapLinkRow($link));

        return $reservationRows
            ->merge($linkRows)
            ->when($filters['order_status'] !== '', function (Collection $rows) use ($filters) {
                return $rows->filter(fn (array $row) => $row['order_status'] === $filters['order_status']);
            })
            ->sortByDesc(fn (array $row) => $row['sort_at'] ?? '')
            ->values()
            ->map(function (array $row) {
                unset($row['sort_at']);

                return $row;
            });
    }

    /** @return array<string, mixed> */
    public function mapLinkRow(HotelPaymentLink $link): array
    {
        $amount = (float) $link->amount;
        $pgCharges = round($amount * 0.02, 2);
        $orderStatus = $link->status === HotelPaymentLink::STATUS_PAID ? 'paid' : 'pending';

        return [
            'id' => 'link-'.$link->id,
            'booking_id' => $link->invoice_id ?: '—',
            'name' => $link->guest_name ?: '—',
            'product' => 'Payment Link',
            'transaction_id' => 'PL-'.$link->id,
            'transaction_date' => $link->sent_at?->format('d/m/Y H:i') ?? '—',
            'checkin' => '—',
            'checkout' => '—',
            'status' => $this->orderStatusLabel($orderStatus),
            'order_status' => $orderStatus,
            'amount' => number_format($amount, 2),
            'pg_charges' => number_format($pgCharges, 2),
            'tax' => '0.00',
            'net_amount' => number_format(max(0, $amount - $pgCharges), 2),
            'confirmation_id' => $link->invoice_id ?: '—',
            'payment_link' => $link->payment_link ?: '—',
            'payment_link_url' => $link->payment_link,
            'settlement_date' => '—',
            'sort_at' => $link->sent_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /** @return array<string, mixed> */
    public function mapRow(CmReservation $reservation): array
    {
        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $amount = (float) ($reservation->amount_after_tax ?? 0);
        $tax = (float) ($reservation->tax ?? 0);
        $pgCharges = $this->pgCharges($reservation, $payload, $amount);
        $orderStatus = $this->orderStatus($reservation, $payload);
        $paymentLink = $reservation->paymentLinkUrl();

        return [
            'id' => $reservation->id,
            'booking_id' => $reservation->booking_id ?: '—',
            'name' => $reservation->guestName(),
            'product' => $this->productLabel($reservation),
            'transaction_id' => $this->transactionId($reservation, $payload),
            'transaction_date' => $reservation->created_at?->format('d/m/Y H:i') ?? '—',
            'checkin' => $reservation->checkinLabel(),
            'checkout' => $reservation->checkoutLabel(),
            'status' => $this->orderStatusLabel($orderStatus),
            'order_status' => $orderStatus,
            'amount' => $this->moneyLabel($amount, $reservation->currency),
            'pg_charges' => $this->moneyLabel($pgCharges, $reservation->currency),
            'tax' => $this->moneyLabel($tax, $reservation->currency),
            'net_amount' => $this->moneyLabel(max(0, $amount - $pgCharges), $reservation->currency),
            'confirmation_id' => $this->confirmationId($reservation, $payload),
            'payment_link' => $paymentLink ?: '—',
            'payment_link_url' => $paymentLink,
            'settlement_date' => $this->settlementDate($payload),
            'sort_at' => $reservation->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function transactionId(CmReservation $reservation, array $payload): string
    {
        foreach (['transactionId', 'transaction_id', 'paymentId', 'payment_id', 'txn_id'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $reservation->booking_id ?: '—';
    }

    /** @param  array<string, mixed>  $payload */
    private function confirmationId(CmReservation $reservation, array $payload): string
    {
        foreach (['confirmationId', 'confirmation_id', 'confirmationNo', 'confirmation_no'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $reservation->booking_id ?: '—';
    }

    /** @param  array<string, mixed>  $payload */
    private function settlementDate(array $payload): string
    {
        foreach (['settlementDate', 'settlement_date', 'settled_at'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return '—';
    }

    /** @param  array<string, mixed>  $payload */
    private function pgCharges(CmReservation $reservation, array $payload, float $amount): float
    {
        foreach (['pgCharges', 'pg_charges', 'gatewayCharges', 'gateway_charges'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return round((float) $payload[$key], 2);
            }
        }

        if ($this->orderStatus($reservation, $payload) === 'paid' && $amount > 0) {
            return round($amount * 0.02, 2);
        }

        return 0;
    }

    /** @param  array<string, mixed>  $payload */
    private function orderStatus(CmReservation $reservation, array $payload): string
    {
        foreach (['orderStatus', 'order_status', 'paymentStatus', 'payment_status'] as $key) {
            $value = strtolower(trim((string) ($payload[$key] ?? '')));

            if ($value !== '') {
                return $this->normalizeOrderStatus($value);
            }
        }

        if (! empty($payload['refunded']) || ! empty($payload['is_refunded'])) {
            return 'refunded';
        }

        if (! empty($payload['payment_failed']) || ! empty($payload['failed'])) {
            return 'failed';
        }

        $paymentLabel = strtolower($reservation->paymentLabel());

        if (str_contains($paymentLabel, 'prepaid') || str_contains($paymentLabel, 'paid')) {
            return 'paid';
        }

        if (! empty($payload['send_payment_link']) || $reservation->paymentLinkUrl()) {
            return ! empty($payload['payment_paid']) ? 'paid' : 'pending';
        }

        if (str_contains($paymentLabel, 'hotel')) {
            return 'unpaid';
        }

        return 'unpaid';
    }

    private function normalizeOrderStatus(string $value): string
    {
        return match (true) {
            str_contains($value, 'refund') => 'refunded',
            str_contains($value, 'fail') => 'failed',
            str_contains($value, 'pend') => 'pending',
            str_contains($value, 'paid') || str_contains($value, 'success') => 'paid',
            str_contains($value, 'unpaid') || str_contains($value, 'due') => 'unpaid',
            default => 'unpaid',
        };
    }

    private function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            default => 'Unpaid',
        };
    }

    private function productLabel(CmReservation $reservation): string
    {
        $room = $reservation->roomLabel();
        $meal = $reservation->mealPlanLabel();

        if ($room !== '—' && $meal !== '—') {
            return $room.' ('.$meal.')';
        }

        return $room !== '—' ? $room : ($meal !== '—' ? $meal : '—');
    }

    private function moneyLabel(float $amount, ?string $currency): string
    {
        return number_format($amount, 2).' '.strtoupper($currency ?: '');
    }

    private function perPageFromRequest(Request $request): int
    {
        $ui = $this->uiConfig();
        $options = $ui['per_page_options'] ?? [20, 50, 100];
        $perPage = (int) $request->input('per_page', $ui['default_per_page'] ?? 20);

        return in_array($perPage, $options, true) ? $perPage : (int) ($ui['default_per_page'] ?? 20);
    }

    /** @return array<string, mixed> */
    public function dateRangeFiltersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();
        $defaultDays = (int) ($ui['default_date_range_days'] ?? 7);
        $fromDate = $request->input('from_date', now()->subDays($defaultDays)->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'per_page' => $this->perPageFromRequest($request),
            'submitted' => $request->has('submitted'),
        ];
    }

    public function autoSendEnabled(Hotel $hotel): bool
    {
        $this->settings->ensureDefaults($hotel);
        $pms = $hotel->settings()->first()?->pms ?? [];

        return ! empty($pms['auto_send_payment_links']);
    }

    public function setAutoSend(Hotel $hotel, bool $enabled): void
    {
        $this->settings->ensureDefaults($hotel);
        $settings = $hotel->settings()->firstOrFail();
        $pms = is_array($settings->pms) ? $settings->pms : [];
        $pms['auto_send_payment_links'] = $enabled;
        $settings->update(['pms' => $pms]);
    }

    /** @param  array<string, mixed>  $data */
    public function sendPaymentLink(Hotel $hotel, array $data): HotelPaymentLink
    {
        $token = Str::upper(Str::random(12));
        $link = url('/pay/'.$hotel->id.'/'.$token);

        return HotelPaymentLink::query()->create([
            'hotel_id' => $hotel->id,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'amount' => $data['amount'],
            'guest_name' => $data['guest_name'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'payment_link' => $link,
            'status' => HotelPaymentLink::STATUS_PENDING,
            'sent_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    public function settlementRows(int $hotelId, string $hotelCode, array $filters): Collection
    {
        if (! ($filters['submitted'] ?? false)) {
            return collect();
        }

        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();
        $groups = [];

        $reservations = CmReservation::query()
            ->where(function (Builder $builder) use ($hotelId, $hotelCode) {
                $builder->where('hotel_id', $hotelId)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->get();

        foreach ($reservations as $reservation) {
            $payload = is_array($reservation->payload) ? $reservation->payload : [];

            if ($this->orderStatus($reservation, $payload) !== 'paid') {
                continue;
            }

            $settlementRaw = $this->settlementDateRaw($payload) ?: $reservation->created_at?->format('Y-m-d');

            if ($settlementRaw === null) {
                continue;
            }

            try {
                $settlementAt = Carbon::parse($settlementRaw);
            } catch (\Throwable) {
                continue;
            }

            if ($settlementAt->lt($from) || $settlementAt->gt($to)) {
                continue;
            }

            $type = $this->paymentTypeLabel($payload, $reservation);
            $key = $settlementAt->format('Y-m-d').'|'.$type;
            $amount = (float) ($reservation->amount_after_tax ?? 0);
            $pgCharges = $this->pgCharges($reservation, $payload, $amount);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'date_of_settlement' => $settlementAt->format('d/m/Y'),
                    'type' => $type,
                    'no_of_transactions' => 0,
                    'amount_transferred' => 0.0,
                    'sort_date' => $settlementAt->format('Y-m-d'),
                ];
            }

            $groups[$key]['no_of_transactions']++;
            $groups[$key]['amount_transferred'] += max(0, $amount - $pgCharges);
        }

        $paidLinks = HotelPaymentLink::query()
            ->where('hotel_id', $hotelId)
            ->where('status', HotelPaymentLink::STATUS_PAID)
            ->whereBetween('sent_at', [$from, $to])
            ->get();

        foreach ($paidLinks as $link) {
            $settlementAt = $link->sent_at ?? $link->created_at;
            $key = $settlementAt->format('Y-m-d').'|Online';
            $amount = (float) $link->amount;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'date_of_settlement' => $settlementAt->format('d/m/Y'),
                    'type' => 'Online',
                    'no_of_transactions' => 0,
                    'amount_transferred' => 0.0,
                    'sort_date' => $settlementAt->format('Y-m-d'),
                ];
            }

            $groups[$key]['no_of_transactions']++;
            $groups[$key]['amount_transferred'] += $amount;
        }

        return collect($groups)
            ->sortByDesc('sort_date')
            ->values()
            ->map(function (array $row) {
                $row['amount_transferred'] = number_format($row['amount_transferred'], 2);

                return $row;
            });
    }

    /** @param  array<string, mixed>  $filters */
    public function invoiceRows(int $hotelId, string $hotelCode, array $filters): Collection
    {
        if (! ($filters['submitted'] ?? false)) {
            return collect();
        }

        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();
        $settlements = $this->settlementRows($hotelId, $hotelCode, $filters);

        if ($settlements->isEmpty()) {
            return collect();
        }

        $groups = [];

        foreach ($settlements as $row) {
            try {
                $date = Carbon::createFromFormat('d/m/Y', $row['date_of_settlement'])->startOfMonth();
            } catch (\Throwable) {
                continue;
            }

            $key = $date->format('Y-m');
            $periodStart = $date->copy()->startOfMonth();
            $periodEnd = $date->copy()->endOfMonth();

            if ($periodEnd->lt($from) || $periodStart->gt($to)) {
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'invoice_date' => $periodEnd->format('d/m/Y'),
                    'start_date' => $periodStart->format('d/m/Y'),
                    'end_date' => $periodEnd->format('d/m/Y'),
                    'invoice_num' => 'INV-'.$periodEnd->format('Ym').'-'.$hotelId,
                    'type' => 'Payment Gateway',
                    'sort_date' => $periodEnd->format('Y-m-d'),
                ];
            }
        }

        return collect($groups)
            ->sortByDesc('sort_date')
            ->values()
            ->map(function (array $row) {
                unset($row['sort_date']);

                return $row;
            });
    }

    /** @param  array<string, mixed>  $payload */
    private function paymentTypeLabel(array $payload, CmReservation $reservation): string
    {
        $payment = trim((string) ($payload['paymentType'] ?? $payload['payment_type'] ?? ''));

        if ($payment !== '') {
            return $payment;
        }

        return $reservation->paymentLabel() !== '—' ? $reservation->paymentLabel() : 'Online';
    }

    /** @param  array<string, mixed>  $payload */
    private function settlementDateRaw(array $payload): ?string
    {
        foreach (['settlementDate', 'settlement_date', 'settled_at'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
