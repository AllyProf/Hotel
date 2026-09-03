<?php

namespace App\Services\ChannelManager;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Services\CmReservationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OtaCommissionDataService
{
    public function __construct(
        private ChannelManagerClient $client,
        private ChannelManagerCodeResolver $codes,
        private CmReservationService $reservations,
    ) {}

    /** @param  array{start_date: string, end_date: string, filter_type: string}  $filters @return array{rows: Collection<int, array<string, mixed>>, summary: array<string, mixed>, sync_message: string|null} */
    public function report(Hotel $hotel, array $filters, bool $syncFromApi = true): array
    {
        $hotelCode = $this->codes->hotelCode($hotel);
        $syncMessage = null;

        if ($syncFromApi && $this->client->isConfigured()) {
            $result = $this->reservations->syncFromChannelManager(
                $hotelCode,
                $hotel->id,
                $filters['start_date'],
                $filters['end_date']
            );

            $syncMessage = $result['message'];
        }

        $rows = $this->queryRows($hotel, $hotelCode, $filters);

        return [
            'rows' => $rows,
            'summary' => $this->summarize($rows),
            'sync_message' => $syncMessage,
        ];
    }

    /** @param  array{start_date: string, end_date: string, filter_type: string}  $filters @return Collection<int, array<string, mixed>> */
    private function queryRows(Hotel $hotel, string $hotelCode, array $filters): Collection
    {
        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->endOfDay();

        $query = CmReservation::query()
            ->where(function ($q) use ($hotel, $hotelCode) {
                $q->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED);

        match ($filters['filter_type']) {
            'stay_date' => $query
                ->whereDate('checkin', '<=', $end)
                ->whereDate('checkout', '>=', $start),
            'booking_date' => $query
                ->whereBetween('created_at', [$start, $end]),
            'checkout_date' => $query
                ->whereBetween('checkout', [$start->toDateString(), $end->toDateString()]),
            default => $query
                ->whereBetween('checkin', [$start->toDateString(), $end->toDateString()]),
        };

        return $query
            ->orderByDesc('checkin')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CmReservation $reservation) => $this->rowFromReservation($reservation))
            ->values();
    }

    /** @return array<string, mixed> */
    private function rowFromReservation(CmReservation $reservation): array
    {
        $commission = $reservation->commissionAmount();
        $currency = $reservation->currency ?? '';

        return [
            'ota' => $reservation->sourceDisplayLabel(),
            'booking_id' => $reservation->booking_id,
            'guest' => $reservation->guestName(),
            'checkin' => $reservation->checkinLabel(),
            'checkout' => $reservation->checkoutLabel(),
            'amount' => $reservation->priceLabel(),
            'commission' => $commission !== null
                ? number_format($commission, 2).($currency !== '' ? ' '.$currency : '')
                : '—',
            'commission_value' => $commission,
            'currency' => $currency,
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $rows @return array<string, mixed> */
    private function summarize(Collection $rows): array
    {
        $withCommission = $rows->filter(fn (array $row) => $row['commission_value'] !== null);
        $totalCommission = $withCommission->sum('commission_value');
        $currency = (string) ($withCommission->first()['currency'] ?? '');

        $byOta = $withCommission
            ->groupBy('ota')
            ->map(fn (Collection $group) => [
                'bookings' => $group->count(),
                'commission' => $group->sum('commission_value'),
            ])
            ->sortByDesc('commission');

        return [
            'bookings' => $rows->count(),
            'with_commission' => $withCommission->count(),
            'total_commission' => $totalCommission,
            'currency' => $currency,
            'by_ota' => $byOta->all(),
        ];
    }
}
