<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Branch extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'hotel_id',
        'name',
        'slug',
        'email',
        'phone',
        'phone_country_code',
        'address',
        'city',
        'country',
        'country_code',
        'status',
        'is_headquarters',
    ];

    protected function casts(): array
    {
        return [
            'is_headquarters' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (empty($branch->slug)) {
                $branch->slug = static::generateUniqueSlug($branch->hotel_id, $branch->name);
            }
        });
    }

    public static function generateUniqueSlug(int $hotelId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (static::where('hotel_id', $hotelId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
