<?php

namespace App\Services;

class PlanFeatureService
{
    /** @return array<string, string> */
    public function all(): array
    {
        return config('plan_features', []);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function label(string $key): string
    {
        return $this->all()[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /** @param  array<int, string>|null  $selected */
    public function labelsFor(?array $selected): array
    {
        if (empty($selected)) {
            return [];
        }

        return array_values(array_map(fn (string $key) => $this->label($key), $selected));
    }
}
