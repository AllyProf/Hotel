<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHotelUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isPlatformOwner()) {
            abort(403, 'Hotel access only.');
        }

        if (! $user->hotel_id) {
            abort(403, 'No hotel assigned to this account.');
        }

        return $next($request);
    }
}
