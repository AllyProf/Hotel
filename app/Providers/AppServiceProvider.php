<?php

namespace App\Providers;

use App\Services\BranchContextService;
use App\Services\HotelMenuService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();

        View::composer('layouts.partials._sidebar-hotel', function ($view) {
            $user = auth()->user();
            $hotel = $user?->hotel()?->with('plan')->first();
            $sidebarGroups = $hotel
                ? app(HotelMenuService::class)->sidebarGroups($hotel)
                : [];

            $view->with([
                'hotel' => $hotel,
                'sidebarGroups' => $sidebarGroups,
            ]);
        });

        View::composer('layouts.partials._header', function ($view) {
            $user = auth()->user();
            $branchContext = app(BranchContextService::class);

            $view->with([
                'showBranchSwitcher' => $branchContext->shouldShowSwitcher($user),
                'switcherBranches' => $branchContext->availableBranches($user),
                'activeBranch' => $branchContext->activeBranch($user),
            ]);
        });
    }
}
