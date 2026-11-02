<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use InvalidArgumentException;

class FrontDeskPaymentService
{
    public function __construct(
        private HotelSettingsService $settings,
    ) {}

    /** @return array{payment_modes: list<string>, default_payment_mode: string, currency: string, payment_provider_config: array<string, list<string>>} */
    public function optionsForHotel(Hotel $hotel): array
    {
        $this->settings->ensureDefaults($hotel);
        $reservation = is_array($hotel->settings?->reservation) ? $hotel->settings->reservation : [];
        $defaults = $this->settings->defaultReservation();
        $modes = array_values($reservation['payment_modes'] ?? $defaults['payment_modes'] ?? ['Cash']);

        return [
            'payment_modes' => $modes,
            'default_payment_mode' => $modes[0] ?? 'Cash',
            'currency' => strtoupper($hotel->currency ?: 'TZS'),
            'payment_provider_config' => $this->settings->paymentProviderConfig($hotel),
        ];
    }

    /**
     * Payment is captured at the desk for walk-in / external guests only.
     * In-house stays are charged to the guest folio when configured.
     *
     * @param  array<string, mixed>  $data
     * @return array{payment_mode: ?string, payment_provider: ?string, amount_paid: float, payment_notes: ?string}
     */
    public function parsePayment(Hotel $hotel, array $data, float $total, bool $walkIn = false): array
    {
        $empty = [
            'payment_mode' => null,
            'payment_provider' => null,
            'amount_paid' => 0.0,
            'payment_notes' => null,
        ];

        if ($total <= 0.009 || ! $walkIn) {
            return $empty;
        }

        $mode = trim((string) ($data['payment_mode'] ?? ''));
        if ($mode === '') {
            throw new InvalidArgumentException('Select how the walk-in guest paid.');
        }

        $provider = $this->assertPaymentProvider($hotel, $mode, $data['payment_provider'] ?? null);
        $amountPaid = min(round(max(0, (float) ($data['amount_paid'] ?? 0)), 2), round($total, 2));
        if ($amountPaid <= 0) {
            throw new InvalidArgumentException('Enter the amount the walk-in guest paid.');
        }

        $notes = trim((string) ($data['payment_notes'] ?? ''));

        return [
            'payment_mode' => $mode,
            'payment_provider' => $provider !== '' ? $provider : null,
            'amount_paid' => $amountPaid,
            'payment_notes' => $notes !== '' ? $notes : null,
        ];
    }

    /** @return list<string> */
    public function walkInPaymentModes(Hotel $hotel): array
    {
        $modes = $this->optionsForHotel($hotel)['payment_modes'];

        return array_values(array_filter($modes, function (string $mode): bool {
            return stripos($mode, 'bill to company') === false;
        }));
    }

    /**
     * @param  array{payment_mode: ?string, payment_provider: ?string, amount_paid: float, payment_notes: ?string}  $payment
     */
    public function recordOnReservation(
        CmReservation $reservation,
        array $payment,
        string $source,
        int $sourceId,
        string $sourceRef,
    ): void {
        if ($payment['amount_paid'] <= 0) {
            return;
        }

        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $entries = is_array($payload['desk_service_payments'] ?? null) ? $payload['desk_service_payments'] : [];

        $entries[] = [
            'source' => $source,
            'source_id' => $sourceId,
            'reference' => $sourceRef,
            'amount' => $payment['amount_paid'],
            'mode' => $payment['payment_mode'],
            'provider' => $payment['payment_provider'],
            'notes' => $payment['payment_notes'],
            'paid_at' => now()->toIso8601String(),
        ];
        $payload['desk_service_payments'] = $entries;

        $reservation->update([
            'amount_paid' => round((float) ($reservation->amount_paid ?? 0) + $payment['amount_paid'], 2),
            'payload' => $payload,
        ]);
    }

    public function assertPaymentProvider(Hotel $hotel, string $paymentMode, mixed $paymentProvider): string
    {
        $paymentProvider = trim((string) ($paymentProvider ?? ''));
        $options = $this->settings->paymentProviderOptions($hotel, $paymentMode);

        if ($options !== [] && $paymentProvider === '') {
            throw new InvalidArgumentException(
                'Please select a '.$this->settings->paymentProviderLabel($paymentMode).'.'
            );
        }

        if ($paymentProvider !== '' && ! in_array($paymentProvider, $options, true)) {
            throw new InvalidArgumentException('Selected payment provider is not valid.');
        }

        return $paymentProvider;
    }
}
