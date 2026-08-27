<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            if ($user?->isPlatformOwner()) {
                return route('dashboard');
            }

            return route('hotel.dashboard');
        });
        $middleware->alias([
            'platform.owner' => \App\Http\Middleware\EnsurePlatformOwner::class,
            'hotel.user' => \App\Http\Middleware\EnsureHotelUser::class,
            'plan.feature' => \App\Http\Middleware\EnsurePlanFeature::class,
            'hotel.admin' => \App\Http\Middleware\EnsureHotelAdmin::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
