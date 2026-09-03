<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelCompany extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'gst_vat',
        'contracted_rates',
    ];

    protected function casts(): array
    {
        return [
            'contracted_rates' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function contractedRatesSummary(): string
    {
        $rates = is_array($this->contracted_rates) ? $this->contracted_rates : [];

        if ($rates === []) {
            return '—';
        }

        $parts = [];

        foreach ($rates as $label => $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }

            $parts[] = is_string($label) && ! is_numeric($label)
                ? $label.': '.$amount
                : (string) $amount;
        }

        return $parts !== [] ? implode(', ', $parts) : '—';
    }

    /** @return array<string, mixed> */
    public function toPickerRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email ?: '—',
            'gst_vat' => $this->gst_vat ?: '—',
            'contracted_rates' => $this->contractedRatesSummary(),
        ];
    }
}
