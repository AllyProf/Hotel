<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCompany;
use App\Models\HotelRatePlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CompanyDataService
{
    public function __construct(
        private CreateReservationService $reservations,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.companies', []);
    }

    /** @return array<string, mixed> */
    public function formOptions(Hotel $hotel): array
    {
        return $this->reservations->formOptions($hotel);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'per_page' => $this->perPageFromRequest($request),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function query(int $hotelId, array $filters): Builder
    {
        $query = HotelCompany::query()
            ->where('hotel_id', $hotelId)
            ->orderBy('name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('gst_vat', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query;
    }

    /** @return array<int, array{billed: float, outstanding: float, invoices: int, payments: float, currency: string}> */
    public function billingStatsByCompany(Hotel $hotel, string $hotelCode): array
    {
        $reservations = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->get();

        $stats = [];

        foreach ($reservations as $reservation) {
            $payload = is_array($reservation->payload) ? $reservation->payload : [];
            $companyId = (int) ($payload['bill_to_company_id'] ?? 0);

            if ($companyId <= 0) {
                continue;
            }

            if (! isset($stats[$companyId])) {
                $stats[$companyId] = [
                    'billed' => 0.0,
                    'outstanding' => 0.0,
                    'invoices' => 0,
                    'payments' => 0.0,
                    'currency' => strtoupper((string) ($reservation->currency ?: $hotel->currency ?: 'USD')),
                ];
            }

            $amount = (float) ($reservation->amount_after_tax ?? 0);
            $paymentType = strtolower((string) ($payload['paymentType'] ?? $payload['payment_type'] ?? ''));
            $isOutstanding = str_contains($paymentType, 'bill')
                || str_contains($paymentType, 'company')
                || str_contains($paymentType, 'credit');

            $stats[$companyId]['billed'] += $amount;
            $stats[$companyId]['invoices']++;
            $stats[$companyId]['currency'] = strtoupper((string) ($reservation->currency ?: $stats[$companyId]['currency']));

            if ($isOutstanding) {
                $stats[$companyId]['outstanding'] += $amount;
            } else {
                $stats[$companyId]['payments'] += $amount;
            }
        }

        return $stats;
    }

    /** @param  array<string, mixed>  $data */
    public function createCompany(Hotel $hotel, array $data): HotelCompany
    {
        return HotelCompany::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'gst_vat' => $data['gst_vat'] ?? null,
            'contracted_rates' => $this->normalizeContractedRates($hotel, $data['contracted_rates'] ?? []),
        ]);
    }

    /** @param  array<string|int, mixed>  $rawRates
     * @return array<string, float>
     */
    public function normalizeContractedRates(Hotel $hotel, array $rawRates): array
    {
        $rates = [];

        foreach ($rawRates as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $plan = HotelRatePlan::query()
                ->where('hotel_id', $hotel->id)
                ->with('room')
                ->find($key);

            $label = $plan
                ? trim(($plan->room?->display_name ?: $plan->room?->name).', '.$plan->code.', '.$plan->meal_plan, ' ,')
                : (string) $key;

            $rates[$label] = (float) $value;
        }

        return $rates;
    }

    public function moneyLabel(float $amount, ?string $currency): string
    {
        return number_format($amount, 0).' '.strtoupper($currency ?: 'USD');
    }

    private function perPageFromRequest(Request $request): int
    {
        $options = $this->uiConfig()['per_page_options'] ?? [20, 50, 100];
        $perPage = (int) $request->input('per_page', $options[0] ?? 20);

        return in_array($perPage, $options, true) ? $perPage : ($options[0] ?? 20);
    }
}
