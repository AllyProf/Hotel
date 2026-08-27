<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HotelController extends Controller
{
    public function index(): View
    {
        $hotels = Hotel::with(['creator', 'plan', 'adminUser'])
            ->withCount('branches')
            ->latest()
            ->paginate(10);

        return view('admin.hotels.index', compact('hotels'));
    }

    public function create(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.hotels.create', compact('plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phone = trim(($validated['phone_country_code'] ?? '').ltrim($validated['phone'] ?? '', '0'));

        DB::transaction(function () use ($validated, $request, $phone) {
            $hotel = Hotel::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'tin' => $validated['tin'] ?? null,
                'phone' => $phone !== '' ? $phone : null,
                'phone_country_code' => $validated['phone_country_code'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'],
                'country' => $validated['country'],
                'country_code' => strtoupper($validated['country_code']),
                'plan_id' => $validated['plan_id'],
                'status' => Hotel::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);

            User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['admin_password'],
                'role' => User::ROLE_HOTEL_ADMIN,
                'hotel_id' => $hotel->id,
            ]);
        });

        return redirect()
            ->route('admin.hotels.index')
            ->with('success', 'Hotel account created successfully.');
    }
}
