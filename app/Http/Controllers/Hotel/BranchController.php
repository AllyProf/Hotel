<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $hotel = auth()->user()->hotel()->with('plan')->first();
        $branches = $hotel->branches()->latest()->paginate(10);
        $canAddBranch = $hotel->canAddBranch();

        return view('hotel.branches.index', compact('hotel', 'branches', 'canAddBranch'));
    }

    public function create(): View|RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('plan')->first();

        if (! $hotel->canAddBranch()) {
            return redirect()
                ->route('hotel.branches.index')
                ->with('error', 'You have reached the maximum number of branches allowed on your plan.');
        }

        return view('hotel.branches.create', compact('hotel'));
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('plan')->first();

        if (! $hotel->canAddBranch()) {
            return redirect()
                ->route('hotel.branches.index')
                ->with('error', 'You have reached the maximum number of branches allowed on your plan.');
        }

        $validated = $this->validateBranch($request, isCreate: true);

        if ($request->boolean('is_headquarters')) {
            $hotel->branches()->update(['is_headquarters' => false]);
        }

        $phone = trim(($validated['phone_country_code'] ?? '').ltrim($validated['phone'] ?? '', '0'));

        $hotel->branches()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $phone !== '' ? $phone : null,
            'phone_country_code' => $validated['phone_country_code'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'country_code' => isset($validated['country_code']) ? strtoupper($validated['country_code']) : null,
            'status' => $validated['status'] ?? Branch::STATUS_ACTIVE,
            'is_headquarters' => $request->boolean('is_headquarters'),
        ]);

        return redirect()
            ->route('hotel.branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function edit(Branch $branch): View
    {
        $this->authorizeBranch($branch);

        return view('hotel.branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $validated = $this->validateBranch($request);

        if ($request->boolean('is_headquarters')) {
            auth()->user()->hotel->branches()
                ->where('id', '!=', $branch->id)
                ->update(['is_headquarters' => false]);
        }

        $phone = trim(($validated['phone_country_code'] ?? '').ltrim($validated['phone'] ?? '', '0'));

        $branch->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $phone !== '' ? $phone : null,
            'phone_country_code' => $validated['phone_country_code'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'country_code' => isset($validated['country_code']) ? strtoupper($validated['country_code']) : null,
            'status' => $validated['status'],
            'is_headquarters' => $request->boolean('is_headquarters'),
        ]);

        return redirect()
            ->route('hotel.branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $branch->delete();

        return redirect()
            ->route('hotel.branches.index')
            ->with('success', 'Branch deleted successfully.');
    }

    public function switch(Request $request, BranchContextService $branchContext): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->authorizeBranch($branch);
        $branchContext->setActiveBranch($request->user(), $branch);

        return back()->with('success', 'Switched to '.$branch->name.'.');
    }

    /** @return array<string, mixed> */
    private function validateBranch(Request $request, bool $isCreate = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'status' => [$isCreate ? 'nullable' : 'required', 'in:active,inactive'],
            'is_headquarters' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeBranch(Branch $branch): void
    {
        if ($branch->hotel_id !== auth()->user()->hotel_id) {
            abort(403);
        }
    }
}
