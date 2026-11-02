<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelBarSale;
use App\Models\HotelLaundryOrder;
use App\Models\HotelSwimmingVisit;
use App\Models\HotelPmsService;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReservationFolioService
{
    /** @return Collection<int, HotelPmsService> */
    public function activeServicesForHotel(Hotel $hotel): Collection
    {
        return HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function serviceRowsForReservation(Hotel $hotel, CmReservation $reservation): array
    {
        $rows = [];

        $laundryOrders = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id)
            ->where('cm_reservation_id', $reservation->id)
            ->with(['creator', 'completer'])
            ->orderByDesc('id')
            ->get();

        $swimmingVisits = HotelSwimmingVisit::query()
            ->where('hotel_id', $hotel->id)
            ->where('cm_reservation_id', $reservation->id)
            ->with(['creator', 'category'])
            ->orderByDesc('id')
            ->get();

        $barSales = HotelBarSale::query()
            ->where('hotel_id', $hotel->id)
            ->where('cm_reservation_id', $reservation->id)
            ->with(['creator', 'lines'])
            ->orderByDesc('id')
            ->get();

        foreach ($barSales as $sale) {
            $lineSummary = $sale->lines->map(fn ($line) => $line->item_name.' × '.$line->quantity)->take(3)->implode(', ');
            $rows[] = [
                'type' => 'bar',
                'service' => 'Bar',
                'reference' => $sale->sale_no,
                'description' => $lineSummary !== '' ? $lineSummary : null,
                'amount' => $sale->formattedTotal($hotel),
                'status' => 'Completed',
                'posted' => $sale->posted_to_folio,
                'posted_label' => $sale->posted_to_folio ? 'On folio' : 'Not posted',
                'staff' => $sale->creator?->name,
                'when' => $sale->sold_at?->format('d/m/Y H:i'),
                'url' => route('hotel.pos.index'),
                'print_url' => null,
            ];
        }

        foreach ($swimmingVisits as $visit) {
            $rows[] = [
                'type' => 'swimming',
                'service' => 'Swimming',
                'reference' => $visit->visit_no,
                'description' => $visit->category?->name,
                'amount' => $visit->formattedTotal($hotel),
                'status' => $visit->statusLabel(),
                'posted' => $visit->posted_to_folio,
                'posted_label' => $visit->posted_to_folio ? 'On folio' : 'Not posted',
                'staff' => $visit->creator?->name,
                'when' => $visit->entered_at?->format('d/m/Y H:i'),
                'url' => route('hotel.swimming.show', $visit),
                'print_url' => route('hotel.swimming.print', $visit),
            ];
        }

        foreach ($laundryOrders as $order) {
            $rows[] = [
                'type' => 'laundry',
                'service' => 'Laundry',
                'reference' => $order->ticket_no,
                'description' => $order->is_express ? 'Express service' : null,
                'amount' => $order->formattedTotal($hotel),
                'status' => $order->statusLabel(),
                'posted' => $order->posted_to_folio,
                'posted_label' => $order->posted_to_folio ? 'On folio' : 'Not posted',
                'staff' => $order->creator?->name,
                'when' => $order->received_at?->format('d/m/Y H:i'),
                'url' => route('hotel.laundry.show', $order),
                'print_url' => route('hotel.laundry.print', $order),
            ];
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $manual = is_array($payload['folio_service_charges'] ?? null) ? $payload['folio_service_charges'] : [];

        foreach ($manual as $charge) {
            if (! is_array($charge)) {
                continue;
            }

            $amount = (float) ($charge['amount'] ?? 0);
            $currency = strtoupper((string) ($charge['currency'] ?? $reservation->currency ?? ''));

            $rows[] = [
                'type' => 'manual',
                'service' => trim((string) ($charge['service_name'] ?? 'Service')),
                'reference' => '—',
                'description' => trim((string) ($charge['description'] ?? '')) ?: null,
                'amount' => number_format($amount, 2).' '.$currency,
                'status' => 'Posted',
                'posted' => true,
                'posted_label' => 'On folio',
                'staff' => $charge['posted_by_name'] ?? null,
                'when' => ! empty($charge['posted_at'])
                    ? \Carbon\Carbon::parse($charge['posted_at'])->format('d/m/Y H:i')
                    : null,
                'url' => null,
                'print_url' => null,
            ];
        }

        return $rows;
    }

    public function canAddServices(CmReservation $reservation): bool
    {
        return ! $reservation->isCancelled() && ! $reservation->isCheckedOut();
    }

    public function addManualCharge(
        Hotel $hotel,
        CmReservation $reservation,
        array $data,
        ?User $user = null,
    ): CmReservation {
        if (! $this->canAddServices($reservation)) {
            throw new InvalidArgumentException('Services cannot be added to this booking.');
        }

        $serviceName = trim((string) ($data['service_name'] ?? ''));
        if ($serviceName === '') {
            throw new InvalidArgumentException('Service name is required.');
        }

        $amount = max(0, (float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $currency = strtoupper(trim((string) ($data['currency'] ?? $reservation->currency ?? $hotel->currency ?? 'TZS')));
        $description = trim((string) ($data['description'] ?? ''));

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $charges = is_array($payload['folio_service_charges'] ?? null) ? $payload['folio_service_charges'] : [];

        $charges[] = [
            'id' => uniqid('svc_', true),
            'hotel_pms_service_id' => ! empty($data['hotel_pms_service_id']) ? (int) $data['hotel_pms_service_id'] : null,
            'service_name' => $serviceName,
            'description' => $description !== '' ? $description : null,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'posted_at' => now()->toIso8601String(),
            'posted_by' => $user?->id,
            'posted_by_name' => $user?->name,
        ];
        $payload['folio_service_charges'] = $charges;

        $reservation->update([
            'amount_after_tax' => round((float) ($reservation->amount_after_tax ?? 0) + $amount, 2),
            'payload' => $payload,
        ]);

        return $reservation->fresh();
    }
}
