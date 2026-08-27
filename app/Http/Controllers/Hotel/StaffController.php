<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HotelRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private HotelRoleService $roles) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with('plan')->first();
        $this->roles->ensureDefaults($hotel);

        $staff = $hotel->users()
            ->where('role', User::ROLE_HOTEL_STAFF)
            ->with(['hotelRole', 'branch'])
            ->latest()
            ->paginate(10);

        return view('hotel.staff.index', compact('hotel', 'staff'));
    }

    public function create(): View
    {
        return view('hotel.staff.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel;
        $validated = $this->validateStaff($request);

        $hotel->users()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role' => User::ROLE_HOTEL_STAFF,
            'hotel_role_id' => $validated['hotel_role_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('hotel.staff.index')
            ->with('success', 'Staff member created successfully.');
    }

    public function edit(User $staff): View
    {
        $this->authorizeStaff($staff);

        return view('hotel.staff.edit', array_merge($this->formData(), compact('staff')));
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->authorizeStaff($staff);
        $validated = $this->validateStaff($request, $staff);

        $staff->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'hotel_role_id' => $validated['hotel_role_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($validated['password'])) {
            $staff->update(['password' => $validated['password']]);
        }

        return redirect()
            ->route('hotel.staff.index')
            ->with('success', 'Staff member updated successfully.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->authorizeStaff($staff);
        $staff->delete();

        return redirect()
            ->route('hotel.staff.index')
            ->with('success', 'Staff member removed successfully.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $hotel = auth()->user()->hotel()->with('plan')->first();

        return [
            'hotel' => $hotel,
            'roles' => $hotel->roles()->orderBy('name')->get(),
            'branches' => $hotel->supportsMultiBranch() ? $hotel->branches()->orderBy('name')->get() : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function validateStaff(Request $request, ?User $staff = null): array
    {
        $hotelId = auth()->user()->hotel_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($staff?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'hotel_role_id' => [
                'required',
                Rule::exists('hotel_roles', 'id')->where('hotel_id', $hotelId),
            ],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('hotel_id', $hotelId),
            ],
        ]);
    }

    private function authorizeStaff(User $staff): void
    {
        if ($staff->hotel_id !== auth()->user()->hotel_id || $staff->role !== User::ROLE_HOTEL_STAFF) {
            abort(403);
        }
    }
}
