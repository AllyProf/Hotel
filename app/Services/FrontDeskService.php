<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use Carbon\Carbon;
use InvalidArgumentException;

class FrontDeskService
{
    /** @return array{checkin_time: string, checkout_time: string, late_checkout_amount: float, checkin_time_label: string, checkout_time_label: string, payment_reference_required: bool} */
    public function settings(Hotel $hotel): array
    {
        $hotel->loadMissing('settings');
        $pms = is_array($hotel->settings?->pms) ? $hotel->settings->pms : [];

        $checkinTime = (string) ($pms['front_desk_checkin_time'] ?? '14:00');
        $checkoutTime = (string) ($pms['front_desk_checkout_time'] ?? '11:00');

        return [
            'checkin_time' => $checkinTime,
            'checkout_time' => $checkoutTime,
            'checkin_time_label' => $this->formatTimeLabel($checkinTime),
            'checkout_time_label' => $this->formatTimeLabel($checkoutTime),
            'late_checkout_amount' => (float) ($pms['late_checkout_amount'] ?? 0),
            'payment_reference_required' => (bool) ($pms['payment_reference_required'] ?? false),
        ];
    }

    public function isLateCheckout(Hotel $hotel, CmReservation $reservation, ?Carbon $at = null): bool
    {
        if ($reservation->checkout === null) {
            return false;
        }

        $at = ($at ?? now())->copy();
        $settings = $this->settings($hotel);
        $cutoff = $this->checkoutDeadline($reservation->checkout, $settings['checkout_time']);

        if ($cutoff === null) {
            return $at->isAfter($reservation->checkout->copy()->endOfDay());
        }

        return $at->greaterThan($cutoff);
    }

    /** @return array{is_late: bool, checkout_time_label: string, checkin_time_label: string, late_checkout_amount: float, current_time_label: string} */
    public function checkoutContext(Hotel $hotel, CmReservation $reservation): array
    {
        $settings = $this->settings($hotel);

        return [
            'is_late' => $this->isLateCheckout($hotel, $reservation),
            'checkin_time_label' => $settings['checkin_time_label'],
            'checkout_time_label' => $settings['checkout_time_label'],
            'late_checkout_amount' => $settings['late_checkout_amount'],
            'current_time_label' => now()->format('g:i A'),
        ];
    }

    public function checkoutDeadline(Carbon $checkoutDate, string $checkoutTime): ?Carbon
    {
        $parsed = $this->parseTime($checkoutTime);

        if ($parsed === null) {
            return null;
        }

        return $checkoutDate->copy()->startOfDay()
            ->setTime((int) $parsed->format('H'), (int) $parsed->format('i'));
    }

    public function parseTime(string $value): ?Carbon
    {
        $value = trim(str_replace(' ', '', $value));

        if ($value === '') {
            return null;
        }

        foreach (['H:i', 'G:i', 'g:iA', 'g:ia', 'ga', 'gA', 'h:iA', 'h:ia'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                return $parsed ?: null;
            } catch (\Throwable) {
                continue;
            }
        }

        throw new InvalidArgumentException('Invalid time format: '.$value);
    }

    private function formatTimeLabel(string $value): string
    {
        try {
            return $this->parseTime($value)?->format('g:i A') ?? $value;
        } catch (InvalidArgumentException) {
            return $value;
        }
    }
}
