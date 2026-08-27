<?php

use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hotel\BranchController;
use App\Http\Controllers\Hotel\RoomController;
use App\Http\Controllers\Webhooks\CmReservationController;
use App\Http\Controllers\Hotel\ChannelManager\BulkUpdateController;
use App\Http\Controllers\Hotel\ChannelManager\LiveBookingsController;
use App\Http\Controllers\Hotel\ChannelManager\OtaCommissionController;
use App\Http\Controllers\Hotel\ChannelManager\OtaMappingController;
use App\Http\Controllers\Hotel\ChannelManager\UpdateRatesController;
use App\Http\Controllers\Hotel\ChannelManager\UpdateRoomsController;
use App\Http\Controllers\Hotel\IntegrationController;
use App\Http\Controllers\Hotel\RoleController;
use App\Http\Controllers\Hotel\SettingsController;
use App\Http\Controllers\Hotel\StaffController;
use App\Http\Controllers\HotelDashboardController;
use App\Http\Controllers\ValiPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/webhooks/cm/reservations', CmReservationController::class)->name('webhooks.cm.reservations');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/stop-impersonating', [ImpersonationController::class, 'stopImpersonating'])->name('stop-impersonating');
});

Route::middleware(['auth', 'hotel.user'])->prefix('hotel')->name('hotel.')->group(function () {
    Route::get('/dashboard', [HotelDashboardController::class, 'index'])->name('dashboard');

    Route::put('/integrations/booking-engine', [IntegrationController::class, 'updateBookingEngine'])
        ->middleware('plan.feature:booking_engine_website')
        ->name('integrations.booking-engine');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/whatsapp/connect', [SettingsController::class, 'connectWhatsapp'])->name('settings.whatsapp.connect');
    Route::post('/settings/whatsapp/disconnect', [SettingsController::class, 'disconnectWhatsapp'])->name('settings.whatsapp.disconnect');

    Route::resource('rooms', RoomController::class)->except(['show']);

    Route::middleware('plan.feature:channel_manager')->prefix('channel-manager')->name('channel-manager.')->group(function () {
        Route::get('/update-rates', [UpdateRatesController::class, 'index'])->name('update-rates');
        Route::post('/update-rates', [UpdateRatesController::class, 'store'])->name('update-rates.store');
        Route::post('/update-rates/availability', [UpdateRatesController::class, 'updateAvailability'])->name('update-rates.availability');
        Route::post('/update-rates/events', [UpdateRatesController::class, 'storeEvent'])->name('update-rates.events');
        Route::get('/update-rooms', [UpdateRoomsController::class, 'index'])->name('update-rooms');
        Route::post('/update-rooms', [UpdateRoomsController::class, 'store'])->name('update-rooms.store');
        Route::get('/bulk-update', [BulkUpdateController::class, 'index'])->name('bulk-update');
        Route::post('/bulk-update', [BulkUpdateController::class, 'store'])->name('bulk-update.store');
        Route::get('/live-bookings', [LiveBookingsController::class, 'index'])->name('live-bookings');
        Route::get('/live-bookings/export', [LiveBookingsController::class, 'export'])->name('live-bookings.export');
        Route::post('/live-bookings/sync', [LiveBookingsController::class, 'sync'])->name('live-bookings.sync');
        Route::get('/ota-mapping', [OtaMappingController::class, 'index'])->name('ota-mapping');
        Route::post('/ota-mapping/{slug}', [OtaMappingController::class, 'store'])->name('ota-mapping.store');
        Route::get('/ota-commission', [OtaCommissionController::class, 'index'])->name('ota-commission');
    });

    Route::middleware('plan.feature:groups_chain_hotels_system')->group(function () {
        Route::post('/branches/switch', [BranchController::class, 'switch'])->name('branches.switch');
        Route::resource('branches', BranchController::class)->except(['show']);
    });

    Route::middleware(['hotel.admin', 'plan.feature:hr_employee_management_system'])->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('staff', StaffController::class)->except(['show']);
    });
});

Route::middleware(['auth', 'platform.owner'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin/integrations')->name('admin.integrations.')->group(function () {
        Route::get('/', [AdminIntegrationController::class, 'index'])->name('index');
        Route::put('/channel-manager', [AdminIntegrationController::class, 'updateChannelManager'])->name('channel-manager');
        Route::post('/test-apis', [AdminIntegrationController::class, 'testApis'])->name('test-apis');
    });

    Route::prefix('admin/hotels')->name('admin.hotels.')->group(function () {
        Route::get('/', [HotelController::class, 'index'])->name('index');
        Route::get('/create', [HotelController::class, 'create'])->name('create');
        Route::post('/', [HotelController::class, 'store'])->name('store');
        Route::post('/{hotel}/impersonate', [ImpersonationController::class, 'impersonate'])->name('impersonate');
    });

    Route::prefix('admin/plans')->name('admin.plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index');
        Route::get('/create', [PlanController::class, 'create'])->name('create');
        Route::post('/', [PlanController::class, 'store'])->name('store');
        Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
        Route::put('/{plan}', [PlanController::class, 'update'])->name('update');
    });

    Route::prefix('ui')->name('ui.')->group(function () {
        Route::get('/bootstrap', [ValiPageController::class, 'bootstrapComponents'])->name('bootstrap');
        Route::get('/cards', [ValiPageController::class, 'uiCards'])->name('cards');
        Route::get('/widgets', [ValiPageController::class, 'widgets'])->name('widgets');
    });

    Route::get('/charts', [ValiPageController::class, 'charts'])->name('charts');

    Route::prefix('forms')->name('forms.')->group(function () {
        Route::get('/components', [ValiPageController::class, 'formComponents'])->name('components');
        Route::get('/custom', [ValiPageController::class, 'formCustom'])->name('custom');
        Route::get('/samples', [ValiPageController::class, 'formSamples'])->name('samples');
        Route::get('/notifications', [ValiPageController::class, 'formNotifications'])->name('notifications');
    });

    Route::prefix('tables')->name('tables.')->group(function () {
        Route::get('/basic', [ValiPageController::class, 'tableBasic'])->name('basic');
        Route::get('/data', [ValiPageController::class, 'tableDataTable'])->name('data');
    });

    Route::prefix('pages')->name('pages.')->group(function () {
        Route::get('/blank', [ValiPageController::class, 'blankPage'])->name('blank');
        Route::get('/lockscreen', [ValiPageController::class, 'lockscreen'])->name('lockscreen');
        Route::get('/user', [ValiPageController::class, 'userPage'])->name('user');
        Route::get('/invoice', [ValiPageController::class, 'invoicePage'])->name('invoice');
        Route::get('/calendar', [ValiPageController::class, 'calendarPage'])->name('calendar');
        Route::get('/mailbox', [ValiPageController::class, 'mailboxPage'])->name('mailbox');
        Route::get('/error', [ValiPageController::class, 'errorPage'])->name('error');
    });
});
