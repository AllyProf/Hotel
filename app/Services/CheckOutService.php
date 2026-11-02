<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCompany;
use App\Models\HotelPaymentLink;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class CheckOutService
{
    public function __construct(
        private FrontDeskService $frontDesk,
        private PaymentGatewayDataService $payments,
    ) {}

    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.check_out', []);
    }

    /** @return list<string> */
    public function paymentModes(Hotel $hotel): array
    {
        $hotel->loadMissing('settings');
        $reservation = is_array($hotel->settings?->reservation) ? $hotel->settings->reservation : [];

        return array_values(array_filter(
            $reservation['payment_modes'] ?? ['Cash', 'Credit Card', 'UPI', 'Bank Transfer', 'Bill to Company', 'Prepaid']
        ));
    }

    /** @return array{bank_providers: list<string>, mobile_payment_providers: list<string>} */
    public function paymentProviderConfig(Hotel $hotel): array
    {
        $hotel->loadMissing('settings');
        $reservation = is_array($hotel->settings?->reservation) ? $hotel->settings->reservation : [];
        $defaults = app(HotelSettingsService::class)->defaultReservation();

        return [
            'bank_providers' => array_values($reservation['bank_providers'] ?? $defaults['bank_providers'] ?? []),
            'mobile_payment_providers' => array_values($reservation['mobile_payment_providers'] ?? $defaults['mobile_payment_providers'] ?? []),
        ];
    }

    /** @return list<string> */
    public function paymentProviderOptions(Hotel $hotel, string $paymentMode): array
    {
        $mode = strtolower(trim($paymentMode));
        $config = $this->paymentProviderConfig($hotel);

        if ($mode === '') {
            return [];
        }

        if (str_contains($mode, 'bank')) {
            return $config['bank_providers'];
        }

        if (preg_match('/mobile|m-pesa|mpesa|wallet|tigo|airtel|halopesa|upi/', $mode)) {
            return $config['mobile_payment_providers'];
        }

        return [];
    }

    public function paymentProviderLabel(string $paymentMode): string
    {
        return str_contains(strtolower($paymentMode), 'bank') ? 'Bank' : 'Mobile Payment Provider';
    }

    public function paymentReferenceRequired(Hotel $hotel): bool
    {
        $hotel->loadMissing('settings');
        $pms = is_array($hotel->settings?->pms) ? $hotel->settings->pms : [];

        return (bool) ($pms['payment_reference_required'] ?? false);
    }

    /** @return array{date: Carbon, tab: string, stats: array<string, int>, bookings: Collection<int, CmReservation>, front_desk: array<string, mixed>} */
    public function listForHotel(Hotel $hotel, Carbon $date, string $tab = 'departures'): array
    {
        $date = $date->copy()->startOfDay();
        $bookings = match ($tab) {
            'in_house' => $this->inHouseQuery($hotel, $date)->get(),
            'checked_out' => $this->checkedOutQuery($hotel, $date)->get(),
            default => $this->departuresQuery($hotel, $date)->get(),
        };

        return [
            'date' => $date,
            'tab' => $tab,
            'stats' => [
                'departures' => $this->departuresQuery($hotel, $date)->count(),
                'in_house' => $this->inHouseQuery($hotel, $date)->count(),
                'checked_out' => $this->checkedOutQuery($hotel, $date)->count(),
            ],
            'bookings' => $bookings,
            'front_desk' => $this->frontDesk->settings($hotel),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function checkoutContexts(Hotel $hotel, Collection $bookings): array
    {
        $contexts = [];

        foreach ($bookings as $booking) {
            $contexts[$booking->id] = array_merge(
                $this->frontDesk->checkoutContext($hotel, $booking),
                $this->paymentContext($booking),
            );
        }

        return $contexts;
    }

    /** @return array<string, mixed> */
    public function paymentContext(CmReservation $reservation): array
    {
        return [
            'total_due' => $reservation->totalDue(),
            'amount_paid' => $reservation->effectiveAmountPaid(),
            'balance_due' => $reservation->balanceDue(),
            'is_prepaid' => $reservation->isPrepaid(),
            'payment_cleared' => $reservation->isPaymentCleared(),
            'balance_label' => $reservation->balanceLabel(),
            'payment_link_url' => $reservation->paymentLinkUrl(),
            'guest_email' => $reservation->guestEmail(),
            'guest_phone' => $reservation->guestPhone(),
            'bill_to_company_id' => (int) (is_array($reservation->payload) ? ($reservation->payload['bill_to_company_id'] ?? 0) : 0),
        ];
    }

    /** @return list<array{id: int, name: string}> */
    public function companiesForHotel(Hotel $hotel): array
    {
        return HotelCompany::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (HotelCompany $company) => [
                'id' => $company->id,
                'name' => $company->name,
            ])
            ->all();
    }

    public function isBillToCompanyMode(string $paymentMode): bool
    {
        $mode = strtolower(trim($paymentMode));

        return str_contains($mode, 'bill') && str_contains($mode, 'company');
    }

    public function isPrepaidMode(string $paymentMode): bool
    {
        return str_contains(strtolower(trim($paymentMode)), 'prepaid');
    }

    public function collectPayment(
        Hotel $hotel,
        CmReservation $reservation,
        float $amount,
        string $paymentMode,
        ?float $pendingLateFee = null,
        ?string $paymentProvider = null,
        ?string $paymentReference = null,
        ?int $billToCompanyId = null,
    ): CmReservation {
        if ($reservation->isCancelled()) {
            throw new InvalidArgumentException('Cancelled bookings cannot accept payments.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $providerOptions = $this->paymentProviderOptions($hotel, $paymentMode);
        $paymentProvider = trim((string) ($paymentProvider ?? ''));
        $paymentReference = trim((string) ($paymentReference ?? ''));

        if ($providerOptions !== [] && $paymentProvider === '') {
            throw new InvalidArgumentException('Please select a '.$this->paymentProviderLabel($paymentMode).'.');
        }

        if ($paymentProvider !== '' && ! in_array($paymentProvider, $providerOptions, true)) {
            throw new InvalidArgumentException('Selected payment provider is not valid.');
        }

        if ($this->paymentReferenceRequired($hotel)
            && $paymentReference === ''
            && ! $this->isBillToCompanyMode($paymentMode)
            && ! $this->isPrepaidMode($paymentMode)) {
            throw new InvalidArgumentException('Payment reference number is required.');
        }

        $company = null;

        if ($this->isBillToCompanyMode($paymentMode)) {
            if ($billToCompanyId === null || $billToCompanyId <= 0) {
                throw new InvalidArgumentException('Please select a company to bill.');
            }

            $company = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($billToCompanyId)
                ->first();

            if ($company === null) {
                throw new InvalidArgumentException('Selected company is not valid.');
            }
        }

        $lateFee = max(0, (float) ($pendingLateFee ?? 0));
        $balance = $reservation->balanceDue($lateFee > 0 ? $lateFee : null);

        if ($balance <= 0) {
            throw new InvalidArgumentException('This booking is already fully paid.');
        }

        if ($amount - $balance > 0.009) {
            throw new InvalidArgumentException('Payment amount cannot exceed the balance due.');
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $payments = is_array($payload['checkout_payments'] ?? null) ? $payload['checkout_payments'] : [];
        $payments[] = [
            'amount' => round($amount, 2),
            'mode' => $paymentMode,
            'provider' => $paymentProvider !== '' ? $paymentProvider : null,
            'reference' => $paymentReference !== '' ? $paymentReference : null,
            'company_id' => $company?->id,
            'company_name' => $company?->name,
            'paid_at' => now()->toIso8601String(),
        ];
        $payload['checkout_payments'] = $payments;

        if ($company !== null) {
            $payload['bill_to_company_id'] = $company->id;
            $payload['bill_to'] = $company->name;
            $payload['paymentType'] = 'Bill to Company';
        }

        $reservation->update([
            'amount_paid' => round((float) ($reservation->amount_paid ?? 0) + $amount, 2),
            'payload' => $payload,
        ]);

        return $reservation->fresh(['roomUnit']);
    }

    public function sendPaymentLink(
        Hotel $hotel,
        CmReservation $reservation,
        string $email,
        string $phone,
        float $amount,
        ?float $pendingLateFee = null,
    ): HotelPaymentLink {
        if ($reservation->isCancelled()) {
            throw new InvalidArgumentException('Cancelled bookings cannot receive payment links.');
        }

        if ($reservation->isPrepaid()) {
            throw new InvalidArgumentException('Prepaid bookings do not require a payment link.');
        }

        $lateFee = max(0, (float) ($pendingLateFee ?? 0));
        $balance = $reservation->balanceDue($lateFee > 0 ? $lateFee : null);

        if ($balance <= 0) {
            throw new InvalidArgumentException('This booking is already fully paid.');
        }

        if ($amount <= 0 || $amount - $balance > 0.009) {
            throw new InvalidArgumentException('Payment link amount must match the balance due.');
        }

        return $this->payments->sendPaymentLink($hotel, [
            'cm_reservation_id' => $reservation->id,
            'email' => $email,
            'phone' => $phone,
            'amount' => round($amount, 2),
            'guest_name' => $reservation->guestName() !== '—' ? $reservation->guestName() : null,
            'invoice_id' => $reservation->booking_id,
            'late_fee_amount' => $lateFee > 0 ? $lateFee : null,
        ]);
    }

    public function checkOut(
        Hotel $hotel,
        CmReservation $reservation,
        bool $applyLateFee = false,
        ?float $lateFeeAmount = null,
    ): CmReservation {
        if ($reservation->isCancelled()) {
            throw new InvalidArgumentException('Cancelled bookings cannot be checked out.');
        }

        if ($reservation->isCheckedOut()) {
            throw new InvalidArgumentException('Guest is already checked out.');
        }

        if (! $reservation->isCheckedIn() && $reservation->pms_status !== CmReservation::PMS_ASSIGNED) {
            throw new InvalidArgumentException('Guest must be checked in before check-out.');
        }

        $settings = $this->frontDesk->settings($hotel);
        $lateFee = 0.0;

        if ($applyLateFee) {
            $lateFee = max(0, $lateFeeAmount ?? $settings['late_checkout_amount']);
        }

        if (! $reservation->isPaymentCleared($lateFee > 0 ? $lateFee : null)) {
            throw new InvalidArgumentException('Payment must be completed before check-out.');
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];

        if ($lateFee > 0) {
            $payload['late_checkout'] = [
                'amount' => $lateFee,
                'applied_at' => now()->toIso8601String(),
            ];
        }

        $updates = [
            'pms_status' => CmReservation::PMS_CHECKED_OUT,
            'checked_out_at' => now(),
            'late_checkout_fee' => $lateFee > 0 ? $lateFee : null,
            'payload' => $payload,
        ];

        if ($lateFee > 0 && $reservation->amount_after_tax !== null) {
            $updates['amount_after_tax'] = round((float) $reservation->amount_after_tax + $lateFee, 2);
        }

        $reservation->update($updates);

        app(RoomViewService::class)->markUnitsDirty($hotel, $reservation->assignedRoomUnitIds());

        return $reservation->fresh(['roomUnit']);
    }

    private function departuresQuery(Hotel $hotel, Carbon $date)
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->whereDate('checkout', $date)
            ->whereIn('pms_status', [CmReservation::PMS_CHECKED_IN, CmReservation::PMS_ASSIGNED])
            ->with(['roomUnit'])
            ->orderBy('room_unit_id')
            ->orderBy('guest_first_name');
    }

    private function inHouseQuery(Hotel $hotel, Carbon $date)
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', CmReservation::PMS_CHECKED_IN)
            ->whereDate('checkin', '<=', $date)
            ->whereDate('checkout', '>', $date)
            ->with(['roomUnit'])
            ->orderBy('room_unit_id')
            ->orderBy('guest_first_name');
    }

    private function checkedOutQuery(Hotel $hotel, Carbon $date)
    {
        return CmReservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->where('pms_status', CmReservation::PMS_CHECKED_OUT)
            ->where(function ($query) use ($date) {
                $query->whereDate('checked_out_at', $date)
                    ->orWhere(function ($inner) use ($date) {
                        $inner->whereDate('checkout', $date)
                            ->whereNull('checked_out_at');
                    });
            })
            ->with(['roomUnit'])
            ->orderByDesc('checked_out_at')
            ->orderBy('guest_first_name');
    }
}
