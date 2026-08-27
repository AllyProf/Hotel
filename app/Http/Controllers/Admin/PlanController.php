<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private PlanFeatureService $features) {}

    public function index(): View
    {
        $plans = Plan::withCount('hotels')->orderBy('sort_order')->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create(): View
    {
        $featureOptions = $this->features->all();

        return view('admin.plans.create', compact('featureOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);
        $validated['features'] = array_values($validated['features'] ?? []);
        $validated['is_active'] = $request->boolean('is_active', true);

        Plan::create($validated);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Subscription plan created successfully.');
    }

    public function edit(Plan $plan): View
    {
        $featureOptions = $this->features->all();

        return view('admin.plans.edit', compact('plan', 'featureOptions'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $plan->id);
        $validated['features'] = array_values($validated['features'] ?? []);
        $validated['is_active'] = $request->boolean('is_active');

        $plan->update($validated);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'Subscription plan updated successfully.');
    }

    /** @return array<string, mixed> */
    private function validatePlan(Request $request, ?int $planId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'max_rooms' => ['required', 'integer', 'min:0'],
            'max_users' => ['required', 'integer', 'min:0'],
            'max_branches' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in($this->features->keys())],
        ]);
    }
}
