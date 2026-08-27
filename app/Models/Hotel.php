<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Hotel extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'email',
        'tin',
        'phone',
        'phone_country_code',
        'address',
        'pin_code',
        'city',
        'state',
        'country',
        'country_code',
        'currency',
        'timezone',
        'latitude',
        'longitude',
        'website',
        'google_maps_url',
        'google_review_link',
        'bank_name',
        'bank_account_name',
        'bank_account_no',
        'bank_ifsc',
        'status',
        'plan_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Hotel $hotel) {
            if (empty($hotel->slug)) {
                $hotel->slug = static::generateUniqueSlug($hotel->name);
            }
        });

        static::created(function (Hotel $hotel) {
            $hotel->ensureMainBranch();
        });
    }

    public function ensureMainBranch(): Branch
    {
        $existing = $this->branches()->where('is_headquarters', true)->first();

        if ($existing) {
            return $existing;
        }

        return $this->branches()->create([
            'name' => $this->display_name ?: $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country_code' => $this->phone_country_code,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'status' => Branch::STATUS_ACTIVE,
            'is_headquarters' => true,
        ]);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function adminUser(): HasOne
    {
        return $this->hasOne(User::class)->where('role', User::ROLE_HOTEL_ADMIN);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(HotelSetting::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class);
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(HotelRatePlan::class);
    }

    public function cmReservations(): HasMany
    {
        return $this->hasMany(CmReservation::class);
    }

    public function pmsServices(): HasMany
    {
        return $this->hasMany(HotelPmsService::class);
    }

    public function pmsCategories(): HasMany
    {
        return $this->hasMany(HotelPmsCategory::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(HotelRole::class);
    }

    public function staffUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_HOTEL_STAFF);
    }

    public function hasFeature(string $key): bool
    {
        return (bool) $this->plan?->hasFeature($key);
    }

    public function supportsMultiBranch(): bool
    {
        return $this->hasFeature('groups_chain_hotels_system');
    }

    public function maxBranches(): int
    {
        return (int) ($this->plan?->max_branches ?? 0);
    }

    public function canAddBranch(): bool
    {
        if (! $this->supportsMultiBranch()) {
            return false;
        }

        $max = $this->maxBranches();

        if ($max === 0) {
            return true;
        }

        return $this->branches()->count() < $max;
    }

    public function maxRooms(): int
    {
        return (int) ($this->plan?->max_rooms ?? 0);
    }

    public function canAddRoom(): bool
    {
        $max = $this->maxRooms();

        if ($max === 0) {
            return true;
        }

        return $this->rooms()->count() < $max;
    }

    public function branchesLimitLabel(): string
    {
        return $this->plan?->branchesLimitLabel() ?? '—';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
