<?php

namespace App\Services;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelGuest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GuestDataService
{
    /** @return array<string, mixed> */
    public function uiConfig(): array
    {
        return config('hotel_pms.guests', []);
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $ui = $this->uiConfig();

        return [
            'search' => trim((string) $request->input('search', '')),
            'per_page' => $this->perPageFromRequest($request),
        ];
    }

    public function syncFromReservations(Hotel $hotel, string $hotelCode): int
    {
        $reservations = CmReservation::query()
            ->where(function (Builder $query) use ($hotel, $hotelCode) {
                $query->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->where('status', '!=', CmReservation::STATUS_CANCELLED)
            ->orderBy('created_at')
            ->get();

        $aggregated = [];

        foreach ($reservations as $reservation) {
            $email = $this->normalizeEmail($this->extractEmail($reservation));
            $phone = $this->normalizePhone($this->extractPhone($reservation));
            $name = $this->normalizeName($reservation->guestName());
            $key = $this->aggregateKey($email, $phone, $name);
            $currency = strtoupper(trim((string) ($reservation->currency ?: $hotel->currency ?: 'USD')));

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'name' => $name,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'total_value' => 0.0,
                    'previous_stays' => 0,
                    'currency' => $currency,
                ];
            }

            $aggregated[$key]['previous_stays']++;
            $aggregated[$key]['total_value'] += (float) ($reservation->amount_after_tax ?? 0);
            $aggregated[$key]['currency'] = $currency;

            if ($name !== 'Guest') {
                $aggregated[$key]['name'] = $name;
            }

            if ($email) {
                $aggregated[$key]['email'] = $email;
            }

            if ($phone) {
                $aggregated[$key]['phone'] = $phone;
            }
        }

        foreach ($aggregated as $row) {
            $guest = $this->findExistingGuest($hotel->id, $row['email'], $row['phone'], $row['name']);

            if ($guest) {
                $guest->update([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'total_value' => round($row['total_value'], 2),
                    'previous_stays' => $row['previous_stays'],
                    'currency' => $row['currency'],
                ]);

                continue;
            }

            HotelGuest::query()->create([
                'hotel_id' => $hotel->id,
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'total_value' => round($row['total_value'], 2),
                'previous_stays' => $row['previous_stays'],
                'currency' => $row['currency'],
            ]);
        }

        return count($aggregated);
    }

    /** @param  array{name: string, phone: ?string, email: ?string, total_value: ?float, previous_stays: ?int, currency: ?string}  $data */
    public function upsertImportedGuest(Hotel $hotel, array $data): string
    {
        $email = $this->normalizeEmail($data['email'] ?? null);
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $name = $this->normalizeName($data['name']);
        $guest = $this->findExistingGuest($hotel->id, $email ?: null, $phone ?: null, $name);

        $payload = [
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
        ];

        if ($data['total_value'] !== null) {
            $payload['total_value'] = round((float) $data['total_value'], 2);
        }

        if ($data['previous_stays'] !== null) {
            $payload['previous_stays'] = max(0, (int) $data['previous_stays']);
        }

        if (! empty($data['currency'])) {
            $payload['currency'] = strtoupper((string) $data['currency']);
        }

        if ($guest) {
            $guest->update($payload);

            return 'updated';
        }

        HotelGuest::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'total_value' => $data['total_value'] !== null ? round((float) $data['total_value'], 2) : 0,
            'previous_stays' => $data['previous_stays'] !== null ? max(0, (int) $data['previous_stays']) : 0,
            'currency' => ! empty($data['currency']) ? strtoupper((string) $data['currency']) : strtoupper($hotel->currency ?: 'USD'),
        ]);

        return 'imported';
    }

    public function normalizeEmailPublic(?string $email): string
    {
        return $this->normalizeEmail($email);
    }

    public function normalizePhonePublic(?string $phone): string
    {
        return $this->normalizePhone($phone);
    }

    public function removeDuplicates(Hotel $hotel): int
    {
        $guests = HotelGuest::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('id')
            ->get();

        $merged = 0;
        $keepersByEmail = [];
        $keepersByPhone = [];

        foreach ($guests as $guest) {
            $keeper = null;

            if ($guest->email) {
                $emailKey = strtolower($guest->email);
                $keeper = $keepersByEmail[$emailKey] ?? null;
            }

            if ($keeper === null && $guest->phone) {
                $keeper = $keepersByPhone[$guest->phone] ?? null;
            }

            if ($keeper !== null && $keeper->id !== $guest->id) {
                $this->mergeGuests($keeper, $guest);
                $merged++;

                continue;
            }

            if ($guest->email) {
                $keepersByEmail[strtolower($guest->email)] = $guest;
            }

            if ($guest->phone) {
                $keepersByPhone[$guest->phone] = $guest;
            }
        }

        return $merged;
    }

    /** @param  array<string, mixed>  $filters */
    public function query(int $hotelId, array $filters): Builder
    {
        $query = HotelGuest::query()
            ->where('hotel_id', $hotelId)
            ->orderByDesc('total_value')
            ->orderByDesc('previous_stays')
            ->orderBy('name');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query;
    }

    public function attachPhoto(Hotel $hotel, string $name, ?string $email, ?string $phone, string $photoPath): void
    {
        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);
        $name = $this->normalizeName($name);

        $guest = $this->findExistingGuest($hotel->id, $email ?: null, $phone ?: null, $name);

        if ($guest) {
            $guest->update(['photo_path' => $photoPath]);

            return;
        }

        HotelGuest::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'photo_path' => $photoPath,
            'total_value' => 0,
            'previous_stays' => 0,
            'currency' => strtoupper($hotel->currency ?: 'USD'),
        ]);
    }

    private function findExistingGuest(int $hotelId, ?string $email, ?string $phone, string $name): ?HotelGuest
    {
        $query = HotelGuest::query()->where('hotel_id', $hotelId);

        if ($email) {
            $match = (clone $query)->where('email', $email)->first();

            if ($match) {
                return $match;
            }
        }

        if ($phone) {
            $match = (clone $query)->where('phone', $phone)->first();

            if ($match) {
                return $match;
            }
        }

        return (clone $query)->where('name', $name)->first();
    }

    private function mergeGuests(HotelGuest $keep, HotelGuest $remove): void
    {
        if ($keep->currency && $remove->currency && $keep->currency === $remove->currency) {
            $keep->total_value = round((float) $keep->total_value + (float) $remove->total_value, 2);
        } elseif (! $keep->currency && $remove->currency) {
            $keep->currency = $remove->currency;
            $keep->total_value = round((float) $keep->total_value + (float) $remove->total_value, 2);
        } elseif ($keep->currency === $remove->currency) {
            $keep->total_value = round((float) $keep->total_value + (float) $remove->total_value, 2);
        }

        $keep->previous_stays = (int) $keep->previous_stays + (int) $remove->previous_stays;

        if (! $keep->phone && $remove->phone) {
            $keep->phone = $remove->phone;
        }

        if (! $keep->email && $remove->email) {
            $keep->email = $remove->email;
        }

        if (! $keep->photo_path && $remove->photo_path) {
            $keep->photo_path = $remove->photo_path;
        }

        if (! $keep->currency && $remove->currency) {
            $keep->currency = $remove->currency;
        }

        $keep->save();
        $remove->delete();
    }

    private function extractEmail(CmReservation $reservation): string
    {
        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

        return trim((string) ($guest['email'] ?? $payload['guest_email'] ?? ''));
    }

    private function extractPhone(CmReservation $reservation): string
    {
        $payload = is_array($reservation->payload) ? $reservation->payload : [];
        $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

        return trim((string) ($guest['phone'] ?? $payload['guest_phone'] ?? ''));
    }

    private function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    private function normalizePhone(?string $phone): string
    {
        return preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
    }

    private function normalizeName(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' || $name === '—' ? 'Guest' : $name;
    }

    private function aggregateKey(string $email, string $phone, string $name): string
    {
        if ($email !== '') {
            return 'e:'.$email;
        }

        if ($phone !== '') {
            return 'p:'.$phone;
        }

        return 'n:'.strtolower($name);
    }

    /** @return list<array{id: int, name: string, email: ?string, phone: ?string}> */
    public function searchGuests(Hotel $hotel, string $term, int $limit = 10): array
    {
        $term = trim($term);

        if (strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';

        return HotelGuest::query()
            ->where('hotel_id', $hotel->id)
            ->where(function (Builder $query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderByDesc('previous_stays')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (HotelGuest $guest) => [
                'id' => $guest->id,
                'name' => $guest->name,
                'email' => $guest->email,
                'phone' => $guest->phone,
            ])
            ->values()
            ->all();
    }

    private function perPageFromRequest(Request $request): int
    {
        $options = $this->uiConfig()['per_page_options'] ?? [20, 50, 100];
        $perPage = (int) $request->input('per_page', $options[0] ?? 20);

        return in_array($perPage, $options, true) ? $perPage : ($options[0] ?? 20);
    }
}
