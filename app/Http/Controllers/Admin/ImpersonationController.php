<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function impersonate(Hotel $hotel): RedirectResponse
    {
        $admin = $hotel->users()
            ->where('role', User::ROLE_HOTEL_ADMIN)
            ->first();

        if (! $admin) {
            return redirect()
                ->back()
                ->with('error', 'No hotel admin account found for this hotel.');
        }

        session(['impersonate_original_user' => Auth::id()]);
        Auth::login($admin);

        return redirect()
            ->route('hotel.dashboard')
            ->with('success', "You are now viewing as {$hotel->name}.");
    }

    public function stopImpersonating(): RedirectResponse
    {
        $platformOwnerId = session('impersonate_original_user');

        if ($platformOwnerId) {
            $hotelName = Auth::user()->hotel?->name ?? 'Hotel';
            $owner = User::find($platformOwnerId);

            if ($owner) {
                Auth::login($owner);
            }

            session()->forget('impersonate_original_user');

            return redirect()
                ->route('admin.hotels.index')
                ->with('success', "Stopped impersonating {$hotelName}. Welcome back.");
        }

        return redirect()->route('dashboard');
    }
}
