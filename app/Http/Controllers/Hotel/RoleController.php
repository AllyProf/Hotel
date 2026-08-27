<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRole;
use App\Services\HotelRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private HotelRoleService $roles) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel;
        $this->roles->ensureDefaults($hotel);

        $roles = $hotel->roles()->withCount('users')->orderBy('name')->get();
        $permissionGroups = config('hotel_permissions.groups', []);

        return view('hotel.roles.index', compact('hotel', 'roles', 'permissionGroups'));
    }

    public function create(): View
    {
        return view('hotel.roles.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel;
        $validated = $this->validateRole($request);

        $hotel->roles()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $this->roles->sanitizePermissions($validated['permissions'] ?? []),
            'is_system' => false,
        ]);

        return redirect()
            ->route('hotel.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(HotelRole $role): View
    {
        $this->authorizeRole($role);

        return view('hotel.roles.edit', array_merge($this->formData(), compact('role')));
    }

    public function update(Request $request, HotelRole $role): RedirectResponse
    {
        $this->authorizeRole($role);
        $validated = $this->validateRole($request, $role);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $this->roles->sanitizePermissions($validated['permissions'] ?? []),
        ]);

        return redirect()
            ->route('hotel.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(HotelRole $role): RedirectResponse
    {
        $this->authorizeRole($role);

        if ($role->is_system) {
            return redirect()
                ->route('hotel.roles.index')
                ->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('hotel.roles.index')
                ->with('error', 'Remove staff assigned to this role before deleting it.');
        }

        $role->delete();

        return redirect()
            ->route('hotel.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'permissionGroups' => config('hotel_permissions.groups', []),
        ];
    }

    /** @return array<string, mixed> */
    private function validateRole(Request $request, ?HotelRole $role = null): array
    {
        $hotelId = auth()->user()->hotel_id;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('hotel_roles', 'name')->where('hotel_id', $hotelId)->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:120'],
        ]);
    }

    private function authorizeRole(HotelRole $role): void
    {
        if ($role->hotel_id !== auth()->user()->hotel_id) {
            abort(403);
        }
    }
}
