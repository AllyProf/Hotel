<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;

class BranchContextService
{
    public const SESSION_KEY = 'active_branch_id';

    /** @return Collection<int, Branch> */
    public function availableBranches(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user || $user->isPlatformOwner()) {
            return collect();
        }

        $hotel = $user->hotel;

        if (! $hotel?->supportsMultiBranch()) {
            return collect();
        }

        $query = $hotel->branches()
            ->where('status', Branch::STATUS_ACTIVE)
            ->orderByDesc('is_headquarters')
            ->orderBy('name');

        if ($user->isHotelStaff() && $user->branch_id) {
            $query->where('id', $user->branch_id);
        }

        return $query->get();
    }

    public function shouldShowSwitcher(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || $user->isPlatformOwner()) {
            return false;
        }

        if ($user->isHotelStaff() && $user->branch_id) {
            return false;
        }

        return $this->availableBranches($user)->count() >= 2;
    }

    public function activeBranch(?User $user = null): ?Branch
    {
        $user ??= auth()->user();

        if (! $user || $user->isPlatformOwner()) {
            return null;
        }

        $branches = $this->availableBranches($user);

        if ($branches->isEmpty()) {
            return null;
        }

        $sessionId = session(self::SESSION_KEY);

        if ($sessionId) {
            $fromSession = $branches->firstWhere('id', (int) $sessionId);

            if ($fromSession) {
                return $fromSession;
            }
        }

        if ($user->branch_id) {
            $assigned = $branches->firstWhere('id', $user->branch_id);

            if ($assigned) {
                return $assigned;
            }
        }

        return $branches->firstWhere('is_headquarters', true) ?? $branches->first();
    }

    public function setActiveBranch(User $user, Branch $branch): void
    {
        if ($branch->hotel_id !== $user->hotel_id) {
            abort(403);
        }

        if ($branch->status !== Branch::STATUS_ACTIVE) {
            abort(422, 'Cannot switch to an inactive branch.');
        }

        if ($user->isHotelStaff() && $user->branch_id && $user->branch_id !== $branch->id) {
            abort(403);
        }

        session([self::SESSION_KEY => $branch->id]);
    }

    public function clearActiveBranch(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
