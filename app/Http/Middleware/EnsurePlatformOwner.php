<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPlatformOwner()) {
            abort(403, 'Only the platform owner can access this area.');
        }

        return $next($request);
    }
}
