<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HotelRole extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HotelRole $role) {
            if (empty($role->slug)) {
                $role->slug = static::generateUniqueSlug($role->hotel_id, $role->name);
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return list<string> */
    public function permissionList(): array
    {
        return array_values($this->permissions ?? []);
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionList(), true);
    }
}
