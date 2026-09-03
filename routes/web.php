<?php

use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hotel\AccountsController;
use App\Http\Controllers\Hotel\AnalyticsController;
use App\Http\Controllers\Hotel\BranchController;
use App\Http\Controllers\Hotel\RoomController;
use App\Http\Controllers\Webhooks\CmReservationController;
use App\Http\Controllers\Hotel\ChannelManager\BulkUpdateController;
use App\Http\Controllers\Hotel\ChannelManager\LiveBookingsController;
use App\Http\Controllers\Hotel\ChannelManager\MessagesController;
use App\Http\Controllers\Hotel\ChannelManager\OtaCommissionController;
use App\Http\Controllers\Hotel\ChannelManager\OtaContentController;
use App\Http\Controllers\Hotel\ChannelManager\OtaMappingController;
use App\Http\Controllers\Hotel\ChannelManager\ReviewsController;
use App\Http\Controllers\Hotel\ChannelManager\UpdateRatesController;
use App\Http\Controllers\Hotel\ChannelManager\UpdateRoomsController;
use App\Http\Controllers\Hotel\IntegrationController;
use App\Http\Controllers\Hotel\RoleController;
use App\Http\Controllers\Hotel\SettingsController;
use App\Http\Controllers\Hotel\CompanyController;
use App\Http\Controllers\Hotel\ExpenseController;
use App\Http\Controllers\Hotel\GuestController;
use App\Http\Controllers\Hotel\LogController;
use App\Http\Controllers\Hotel\PaymentGatewayController;
use App\Http\Controllers\Hotel\ReportController;
use App\Http\Controllers\Hotel\ReservationController;
use App\Http\Controllers\Hotel\RoomViewController;
use App\Http\Controllers\Hotel\StayViewController;
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
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    Route::resource('rooms', RoomController::class)->except(['show']);

    Route::middleware('plan.feature:property_management_system')->group(function () {
        Route::get('/stay-view', [StayViewController::class, 'index'])->name('stay-view');
        Route::post('/stay-view/sync', [StayViewController::class, 'sync'])->name('stay-view.sync');
        Route::get('/room-view', [RoomViewController::class, 'index'])->name('room-view');
        Route::post('/room-view/sync', [RoomViewController::class, 'sync'])->name('room-view.sync');
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::get('/reservations/group-booking', [ReservationController::class, 'createGroup'])->name('reservations.group-booking');
        Route::post('/reservations/group-booking', [ReservationController::class, 'storeGroup'])->name('reservations.group-booking.store');
        Route::post('/reservations/group-booking/check-availability', [ReservationController::class, 'checkGroupAvailability'])->name('reservations.group-booking.check-availability');
        Route::get('/reservations/multi-booking', [ReservationController::class, 'createMulti'])->name('reservations.multi-booking');
        Route::post('/reservations/multi-booking', [ReservationController::class, 'storeMulti'])->name('reservations.multi-booking.store');
        Route::post('/reservations/multi-booking/check-availability', [ReservationController::class, 'checkMultiAvailability'])->name('reservations.multi-booking.check-availability');
        Route::get('/reservations/guests/search', [ReservationController::class, 'searchGuests'])->name('reservations.guests.search');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::post('/reservations/sync', [ReservationController::class, 'sync'])->name('reservations.sync');
        Route::get('/reservations/export', [ReservationController::class, 'export'])->name('reservations.export');
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/list', [CompanyController::class, 'list'])->name('companies.list');
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('/companies/upload', [CompanyController::class, 'upload'])->name('companies.upload');
        Route::get('/companies/template', [CompanyController::class, 'template'])->name('companies.template');
        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::post('/guests/remove-duplicates', [GuestController::class, 'removeDuplicates'])->name('guests.remove-duplicates');
        Route::post('/guests/upload', [GuestController::class, 'upload'])->name('guests.upload');
        Route::get('/guests/template', [GuestController::class, 'template'])->name('guests.template');
        Route::get('/guests/export', [GuestController::class, 'export'])->name('guests.export');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/payment-gateway', [PaymentGatewayController::class, 'index'])->name('payment-gateway.index');
        Route::get('/payment-gateway/settlement', [PaymentGatewayController::class, 'settlement'])->name('payment-gateway.settlement');
        Route::get('/payment-gateway/invoices', [PaymentGatewayController::class, 'invoices'])->name('payment-gateway.invoices');
        Route::get('/payment-gateway/unsettled', [PaymentGatewayController::class, 'unsettled'])->name('payment-gateway.unsettled');
        Route::post('/payment-gateway/auto-links', [PaymentGatewayController::class, 'enableAutoLinks'])->name('payment-gateway.auto-links');
        Route::post('/payment-gateway/bank-details', [PaymentGatewayController::class, 'updateBankDetails'])->name('payment-gateway.bank-details');
        Route::post('/payment-gateway/send-link', [PaymentGatewayController::class, 'sendLink'])->name('payment-gateway.send-link');
    });

    Route::middleware('plan.feature:accounting_expenses_system')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/deposit', [ExpenseController::class, 'storeDeposit'])->name('expenses.deposit');

        Route::get('/accounts', [AccountsController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/companies', [AccountsController::class, 'storeCompany'])->name('accounts.companies.store');
        Route::post('/accounts/vendors', [AccountsController::class, 'storeVendor'])->name('accounts.vendors.store');
        Route::get('/accounts/purchase-orders/create', [AccountsController::class, 'createPurchaseOrder'])->name('accounts.purchase-orders.create');
        Route::post('/accounts/purchase-orders', [AccountsController::class, 'storePurchaseOrder'])->name('accounts.purchase-orders.store');
    });

    Route::middleware('plan.feature:revenue_management_software')->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/hotel-performance', [AnalyticsController::class, 'hotelPerformance'])->name('hotel-performance');
        Route::get('/daily-report', [AnalyticsController::class, 'dailyReport'])->name('daily-report');
        Route::get('/trend-analysis', [AnalyticsController::class, 'trendAnalysis'])->name('trend-analysis');
        Route::get('/dynamic-pricing', [AnalyticsController::class, 'dynamicPricing'])->name('dynamic-pricing');
        Route::get('/pickup-report', [AnalyticsController::class, 'pickupReport'])->name('pickup-report');
    });

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
        Route::get('/live-bookings/poll', [LiveBookingsController::class, 'poll'])->name('live-bookings.poll');
        Route::get('/live-bookings/export', [LiveBookingsController::class, 'export'])->name('live-bookings.export');
        Route::post('/live-bookings/sync', [LiveBookingsController::class, 'sync'])->name('live-bookings.sync');
        Route::get('/ota-mapping', [OtaMappingController::class, 'index'])->name('ota-mapping');
        Route::post('/ota-mapping/fetch-property', [OtaMappingController::class, 'fetchProperty'])->name('ota-mapping.fetch-property');
        Route::post('/ota-mapping/sync-multipliers', [OtaMappingController::class, 'syncMultipliers'])->name('ota-mapping.sync-multipliers');
        Route::post('/ota-mapping/{slug}', [OtaMappingController::class, 'store'])->name('ota-mapping.store');
        Route::get('/ota-commission', [OtaCommissionController::class, 'index'])->name('ota-commission');
        Route::get('/messages', [MessagesController::class, 'index'])->name('messages.index');
        Route::post('/messages/sync', [MessagesController::class, 'sync'])->name('messages.sync');
        Route::get('/ota-content', [OtaContentController::class, 'index'])->name('ota-content.index');
        Route::post('/ota-content/refresh', [OtaContentController::class, 'refresh'])->name('ota-content.refresh');

        Route::middleware('plan.feature:reviews_reputation_management')->group(function () {
            Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews.index');
            Route::post('/reviews/sync', [ReviewsController::class, 'sync'])->name('reviews.sync');
        });
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
