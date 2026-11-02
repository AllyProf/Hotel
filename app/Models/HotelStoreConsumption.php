<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelStoreConsumption extends Model
{
    protected $fillable = [
        'hotel_id',
        'hotel_department_id',
        'created_by',
        'consumption_number',
        'notes',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HotelDepartment::class, 'hotel_department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HotelStoreConsumptionItem::class);
    }

    public function dateTimeLabel(): string
    {
        return $this->created_at?->format('d M h:i A') ?? '—';
    }

    public function itemCount(): int
    {
        return $this->items->count();
    }

    public function totalQuantity(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public static function formatQuantity(float $quantity): string
    {
        if (abs($quantity - round($quantity)) < 0.001) {
            return (string) (int) round($quantity);
        }

        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }

    /** @return list<string> */
    public function itemLineSummaries(): array
    {
        return $this->items->map(function (HotelStoreConsumptionItem $line) {
            $qty = self::formatQuantity((float) $line->quantity);
            $unit = trim((string) ($line->displayUnit() ?? ''));

            return $line->name.' × '.$qty.($unit !== '' ? ' '.$unit : '');
        })->all();
    }

    /** @return list<string> */
    public function distributedRoomLabels(): array
    {
        $labels = collect();
        foreach ($this->items as $line) {
            foreach ($line->distributedRoomLabels() as $label) {
                $labels->push($label);
            }
        }

        return $labels->unique()->values()->all();
    }
}
