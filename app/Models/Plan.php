<?php

namespace App\Models;

use App\Services\PlanFeatureService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_cycle',
        'max_rooms',
        'max_users',
        'max_branches',
        'description',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    public function billingLabel(): string
    {
        $cycle = $this->billing_cycle === 'yearly' ? 'year' : 'month';

        return number_format((float) $this->price, 0).' / '.$cycle;
    }

    public function roomsLimitLabel(): string
    {
        return ($this->max_rooms ?? 0) === 0 ? 'Unlimited rooms' : $this->max_rooms.' rooms';
    }

    public function usersLimitLabel(): string
    {
        return ($this->max_users ?? 0) === 0 ? 'Unlimited users' : $this->max_users.' users';
    }

    public function branchesLimitLabel(): string
    {
        return ($this->max_branches ?? 0) === 0 ? 'Unlimited branches' : $this->max_branches.' branches';
    }

    /** @return list<string> */
    public function enabledFeatureKeys(): array
    {
        return is_array($this->features) ? array_values($this->features) : [];
    }

    /** @return list<string> */
    public function enabledFeatureLabels(): array
    {
        return app(PlanFeatureService::class)->labelsFor($this->enabledFeatureKeys());
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->enabledFeatureKeys(), true);
    }
}
