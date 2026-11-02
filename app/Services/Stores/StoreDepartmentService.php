<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class StoreDepartmentService
{
    /** @param  array{search?: string}  $filters */
    public function departmentsForHotel(Hotel $hotel, array $filters = []): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('name');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term);
            });
        }

        return $query->get();
    }

    /** @param  array<string, mixed>  $data */
    public function createDepartment(Hotel $hotel, array $data): HotelDepartment
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Department name is required.');
        }

        $exists = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('name', $name)
            ->exists();
        if ($exists) {
            throw new InvalidArgumentException('A department with this name already exists.');
        }

        return HotelDepartment::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'contact_person' => $this->nullableString($data['contact_person'] ?? null),
            'amount_issued' => max(0, (float) ($data['amount_issued'] ?? 0)),
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function updateDepartment(Hotel $hotel, HotelDepartment $department, array $data): HotelDepartment
    {
        $this->assertHotelOwns($hotel, $department);

        $name = trim((string) ($data['name'] ?? $department->name));
        if ($name === '') {
            throw new InvalidArgumentException('Department name is required.');
        }

        $duplicate = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('name', $name)
            ->where('id', '!=', $department->id)
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException('A department with this name already exists.');
        }

        $department->update([
            'name' => $name,
            'contact_person' => $this->nullableString($data['contact_person'] ?? null),
            'amount_issued' => max(0, (float) ($data['amount_issued'] ?? 0)),
        ]);

        return $department->fresh();
    }

    public function deleteDepartment(Hotel $hotel, HotelDepartment $department): void
    {
        $this->assertHotelOwns($hotel, $department);

        if ($department->staff()->exists()) {
            throw new InvalidArgumentException('Move staff to another department before deleting.');
        }

        $department->delete();
    }

    public function formatMoney(float $amount, Hotel $hotel): string
    {
        $currency = $hotel->currency ?? 'TZS';
        $decimals = in_array(strtoupper($currency), ['TZS', 'UGX', 'RWF'], true) ? 0 : 2;
        $formatted = number_format($amount, $decimals);

        return trim($currency.' '.$formatted);
    }

    private function assertHotelOwns(Hotel $hotel, HotelDepartment $department): void
    {
        if ((int) $department->hotel_id !== (int) $hotel->id) {
            abort(404);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
