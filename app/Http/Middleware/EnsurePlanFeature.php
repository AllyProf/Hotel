<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $hotel = $request->user()?->hotel()->with('plan')->first();

        if (! $hotel?->hasFeature($feature)) {
            abort(403, 'This feature is not included in your subscription plan.');
        }

        return $next($request);
    }
}
