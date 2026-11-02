<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_PLATFORM_OWNER = 'platform_owner';

    public const ROLE_HOTEL_ADMIN = 'hotel_admin';

    public const ROLE_HOTEL_STAFF = 'hotel_staff';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'hotel_id',
        'hotel_role_id',
        'hotel_department_id',
        'branch_id',
        'phone',
        'phone_country_code',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hotelRole(): BelongsTo
    {
        return $this->belongsTo(HotelRole::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HotelDepartment::class, 'hotel_department_id');
    }

    public function staffDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HotelStaffDocument::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isPlatformOwner(): bool
    {
        return $this->role === self::ROLE_PLATFORM_OWNER;
    }

    public function isHotelAdmin(): bool
    {
        return $this->role === self::ROLE_HOTEL_ADMIN;
    }

    /** Hotel admin / platform owner — full access to ERP workflows (bar recipient, accountant, etc.). */
    public function hasFullHotelAccess(): bool
    {
        return $this->isPlatformOwner() || $this->isHotelAdmin();
    }

    public function isHotelStaff(): bool
    {
        return $this->role === self::ROLE_HOTEL_STAFF;
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isPlatformOwner() || $this->isHotelAdmin()) {
            return true;
        }

        return (bool) $this->hotelRole?->hasPermission($key);
    }

    public function sidebarDesignation(): string
    {
        return match ($this->role) {
            self::ROLE_PLATFORM_OWNER => 'Platform Owner',
            self::ROLE_HOTEL_ADMIN => 'Hotel Admin',
            default => 'Hotel Staff',
        };
    }
}
