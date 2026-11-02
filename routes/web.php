<?php

use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\Hotel\AccountsController;
use App\Http\Controllers\Hotel\BarController;
use App\Http\Controllers\Hotel\BookingEngineController;
use App\Http\Controllers\Hotel\DepartmentProcurementController;
use App\Http\Controllers\Hotel\PosController;
use App\Http\Controllers\Hotel\ReceptionController;
use App\Http\Controllers\Hotel\DepartmentStoresController;
use App\Http\Controllers\Hotel\StoresController;
use App\Http\Controllers\Hotel\LostFoundController;
use App\Http\Controllers\Hotel\MaintenanceHkController;
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
use App\Http\Controllers\Hotel\HrController;
use App\Http\Controllers\Hotel\RoleController;
use App\Http\Controllers\Hotel\StaffController;
use App\Http\Controllers\Hotel\SettingsController;
use App\Http\Controllers\Hotel\CheckInController;
use App\Http\Controllers\Hotel\CheckOutController;
use App\Http\Controllers\Hotel\LaundryController;
use App\Http\Controllers\Hotel\SwimmingController;
use App\Http\Controllers\Hotel\ConferenceController;
use App\Http\Controllers\Hotel\CompanyController;
use App\Http\Controllers\Hotel\ExpenseController;
use App\Http\Controllers\Hotel\GuestController;
use App\Http\Controllers\Hotel\LogController;
use App\Http\Controllers\Hotel\PaymentGatewayController;
use App\Http\Controllers\Hotel\PmsServiceController;
use App\Http\Controllers\Hotel\ReportController;
use App\Http\Controllers\Hotel\ReservationController;
use App\Http\Controllers\Hotel\SalesController;
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

Route::get('/book/{slug}', [PublicBookingController::class, 'show'])->name('book.show');
Route::post('/book/{slug}', [PublicBookingController::class, 'store'])->name('book.store');
Route::get('/book/{slug}/confirmation/{booking}', [PublicBookingController::class, 'confirmation'])->name('book.confirmation');

foreach ([
    ['path' => 'bar', 'name' => 'hotel.bar', 'workflow' => 'bar'],
    ['path' => 'maintenance-hk', 'name' => 'hotel.maintenance-hk', 'workflow' => 'hk'],
    ['path' => 'laundry', 'name' => 'hotel.laundry', 'workflow' => 'laundry'],
    ['path' => 'reception', 'name' => 'hotel.reception', 'workflow' => 'reception'],
] as $procSigned) {
    Route::get('/hotel/'.$procSigned['path'].'/purchase-lists/{purchaseList}/shared', [DepartmentProcurementController::class, 'sharedPurchaseList'])
        ->middleware('signed')
        ->defaults('procurement_workflow', $procSigned['workflow'])
        ->name($procSigned['name'].'.purchase-lists.shared');
    Route::post('/hotel/'.$procSigned['path'].'/purchase-lists/{purchaseList}/shared', [DepartmentProcurementController::class, 'submitSharedPurchaseList'])
        ->middleware('signed')
        ->defaults('procurement_workflow', $procSigned['workflow'])
        ->name($procSigned['name'].'.purchase-lists.shared.submit');
}

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/stop-impersonating', [ImpersonationController::class, 'stopImpersonating'])->name('stop-impersonating');
});

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isPlatformOwner()
        ? redirect()->route('dashboard')
        : redirect()->route('hotel.dashboard');
})->name('home');

Route::middleware(['auth', 'hotel.user'])->group(function () {
    Route::get('/sales/create', [SalesController::class, 'create'])->name('sales.create');
});

Route::middleware(['auth', 'hotel.user'])->prefix('hotel')->name('hotel.')->group(function () {
    Route::get('/dashboard', [HotelDashboardController::class, 'index'])->name('dashboard');

    Route::put('/integrations/booking-engine', [IntegrationController::class, 'updateBookingEngine'])
        ->middleware('plan.feature:booking_engine_website')
        ->name('integrations.booking-engine');

    Route::middleware('plan.feature:booking_engine_website')->prefix('booking-engine')->name('booking-engine.')->group(function () {
        Route::get('/live-link', [BookingEngineController::class, 'liveLink'])->name('live-link');
        Route::put('/live-link', [BookingEngineController::class, 'updateLiveLink'])->name('live-link.update');
        Route::get('/coupons', [BookingEngineController::class, 'coupons'])->name('coupons');
        Route::post('/coupons', [BookingEngineController::class, 'storeCoupon'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [BookingEngineController::class, 'updateCoupon'])->name('coupons.update');
        Route::delete('/coupons/{coupon}', [BookingEngineController::class, 'destroyCoupon'])->name('coupons.destroy');
        Route::get('/photos', [BookingEngineController::class, 'photos'])->name('photos');
        Route::get('/website-config', [BookingEngineController::class, 'websiteConfig'])->name('website-config');
    });

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/whatsapp/connect', [SettingsController::class, 'connectWhatsapp'])->name('settings.whatsapp.connect');
    Route::post('/settings/whatsapp/disconnect', [SettingsController::class, 'disconnectWhatsapp'])->name('settings.whatsapp.disconnect');
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    Route::resource('rooms', RoomController::class)->except(['show']);

    Route::middleware('plan.feature:property_management_system')->group(function () {
        Route::get('/stay-view', [StayViewController::class, 'index'])->name('stay-view');
        Route::post('/stay-view/sync', [StayViewController::class, 'sync'])->name('stay-view.sync');
        Route::post('/stay-view/sample-bookings', [StayViewController::class, 'seedSampleBookings'])->name('stay-view.sample-bookings');
        Route::delete('/stay-view/sample-bookings', [StayViewController::class, 'clearSampleBookings'])->name('stay-view.sample-bookings.clear');
        Route::get('/room-view', [RoomViewController::class, 'index'])->name('room-view');
        Route::post('/room-view/sync', [RoomViewController::class, 'sync'])->name('room-view.sync');
        Route::post('/room-view/{unit}/mark-clean', [RoomViewController::class, 'markClean'])->name('room-view.mark-clean');
        Route::post('/room-view/{unit}/mark-available', [RoomViewController::class, 'markAvailable'])->name('room-view.mark-available');
        Route::get('/check-in', [CheckInController::class, 'index'])->name('check-in.index');
        Route::get('/check-in/{reservation}/units', [CheckInController::class, 'availableUnits'])->name('check-in.units');
        Route::post('/check-in/{reservation}/assign', [CheckInController::class, 'assign'])->name('check-in.assign');
        Route::post('/check-in/{reservation}/check-in', [CheckInController::class, 'checkIn'])->name('check-in.check-in');
        Route::get('/check-out', [CheckOutController::class, 'index'])->name('check-out.index');
        Route::get('/check-out/{reservation}/stay', [CheckOutController::class, 'stay'])->name('check-out.stay');
        Route::get('/check-out/{reservation}/stay/print', [CheckOutController::class, 'printStay'])->name('check-out.stay.print');
        Route::post('/check-out/{reservation}/pay', [CheckOutController::class, 'pay'])->name('check-out.pay');
        Route::post('/check-out/{reservation}/send-link', [CheckOutController::class, 'sendLink'])->name('check-out.send-link');
        Route::post('/check-out/{reservation}', [CheckOutController::class, 'process'])->name('check-out.process');
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::get('/reservations/group-booking', [ReservationController::class, 'createGroup'])->name('reservations.group-booking');
        Route::post('/reservations/group-booking', [ReservationController::class, 'storeGroup'])->name('reservations.group-booking.store');
        Route::post('/reservations/group-booking/check-availability', [ReservationController::class, 'checkGroupAvailability'])->name('reservations.group-booking.check-availability');
        Route::get('/reservations/multi-booking', [ReservationController::class, 'createMulti'])->name('reservations.multi-booking');
        Route::post('/reservations/multi-booking', [ReservationController::class, 'storeMulti'])->name('reservations.multi-booking.store');
        Route::post('/reservations/multi-booking/check-availability', [ReservationController::class, 'checkMultiAvailability'])->name('reservations.multi-booking.check-availability');
        Route::get('/reservations/complimentary', [ReservationController::class, 'createComplimentary'])->name('reservations.complimentary');
        Route::post('/reservations/complimentary', [ReservationController::class, 'storeComplimentary'])->name('reservations.complimentary.store');
        Route::post('/reservations/complimentary/check-availability', [ReservationController::class, 'checkComplimentaryAvailability'])->name('reservations.complimentary.check-availability');
        Route::get('/reservations/company-booking', [ReservationController::class, 'createCompany'])->name('reservations.company-booking');
        Route::post('/reservations/company-booking', [ReservationController::class, 'storeCompany'])->name('reservations.company-booking.store');
        Route::post('/reservations/company-booking/check-availability', [ReservationController::class, 'checkCompanyAvailability'])->name('reservations.company-booking.check-availability');
        Route::get('/reservations/guests/search', [ReservationController::class, 'searchGuests'])->name('reservations.guests.search');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::post('/reservations/sync', [ReservationController::class, 'sync'])->name('reservations.sync');
        Route::get('/reservations/export', [ReservationController::class, 'export'])->name('reservations.export');
        Route::get('/reservations/{reservation}/stay-preview', [ReservationController::class, 'previewStayChange'])->name('reservations.stay-preview');
        Route::post('/reservations/{reservation}/stay', [ReservationController::class, 'updateStay'])->name('reservations.update-stay');
        Route::post('/reservations/{reservation}/send-invoice', [ReservationController::class, 'sendInvoice'])->name('reservations.send-invoice');
        Route::post('/reservations/{reservation}/service-charges', [ReservationController::class, 'storeServiceCharge'])->name('reservations.service-charges.store');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::get('/services', [PmsServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [PmsServiceController::class, 'store'])->name('services.store');
        Route::post('/services/from-template', [PmsServiceController::class, 'storeFromTemplate'])->name('services.from-template');
        Route::get('/services/{service}', [PmsServiceController::class, 'show'])->name('services.show');
        Route::put('/services/{service}/settings', [PmsServiceController::class, 'updateSettings'])->name('services.settings.update');
        Route::put('/services/{service}/laundry-prices', [PmsServiceController::class, 'updateLaundryPrices'])->name('services.laundry-prices.update');
        Route::put('/services/{service}/swimming-rates', [PmsServiceController::class, 'updateSwimmingRates'])->name('services.swimming-rates.update');
        Route::put('/services/{service}/conference-rates', [PmsServiceController::class, 'updateConferenceRates'])->name('services.conference-rates.update');
        Route::get('/laundry', [LaundryController::class, 'index'])->name('laundry.index');
        foreach ([
            ['prefix' => 'laundry', 'workflow' => 'laundry'],
        ] as $procRoute) {
            $wf = $procRoute['workflow'];
            $p = $procRoute['prefix'];
            Route::get("/{$p}/purchase-lists/create", [DepartmentProcurementController::class, 'createPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.create");
            Route::post("/{$p}/purchase-lists", [DepartmentProcurementController::class, 'storePurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.store");
            Route::get("/{$p}/purchase-lists/{purchaseList}/print", [DepartmentProcurementController::class, 'printPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.print");
            Route::get("/{$p}/purchase-lists/{purchaseList}/received", [DepartmentProcurementController::class, 'showReceivedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.received.show");
            Route::get("/{$p}/purchase-lists/{purchaseList}/respond", [DepartmentProcurementController::class, 'respondPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.respond");
            Route::post("/{$p}/purchase-lists/{purchaseList}/respond", [DepartmentProcurementController::class, 'submitRespondPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.respond.submit");
            Route::get("/{$p}/purchase-lists/{purchaseList}/market", [DepartmentProcurementController::class, 'formMarketPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market.form");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-draft", [DepartmentProcurementController::class, 'saveMarketPurchaseDraft'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-draft");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-complete", [DepartmentProcurementController::class, 'completeMarketPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-complete");
            Route::get("/{$p}/purchase-lists/{purchaseList}/market-stock-receive", [DepartmentProcurementController::class, 'formMarketStockReceive'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-stock-receive.form");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-stock-receive", [DepartmentProcurementController::class, 'postMarketStockReceive'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-stock-receive");
            Route::get("/{$p}/purchase-lists/{purchaseList}/edit", [DepartmentProcurementController::class, 'editReturnedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.edit");
            Route::post("/{$p}/purchase-lists/{purchaseList}/resubmit", [DepartmentProcurementController::class, 'resubmitReturnedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.resubmit");
            Route::get("/{$p}/purchase-lists/{purchaseList}", [DepartmentProcurementController::class, 'showPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.show");
        }
        Route::get('/laundry/create', [LaundryController::class, 'create'])->name('laundry.create');
        Route::post('/laundry', [LaundryController::class, 'store'])->name('laundry.store');
        Route::get('/laundry/{order}/print', [LaundryController::class, 'print'])->name('laundry.print');
        Route::get('/laundry/{order}', [LaundryController::class, 'show'])->name('laundry.show');
        Route::post('/laundry/{order}/status', [LaundryController::class, 'updateStatus'])->name('laundry.update-status');
        Route::post('/laundry/{order}/post-folio', [LaundryController::class, 'postFolio'])->name('laundry.post-folio');

        Route::get('/swimming', [SwimmingController::class, 'index'])->name('swimming.index');
        Route::get('/swimming/create', [SwimmingController::class, 'create'])->name('swimming.create');
        Route::post('/swimming', [SwimmingController::class, 'store'])->name('swimming.store');
        Route::get('/swimming/{visit}/print', [SwimmingController::class, 'print'])->name('swimming.print');
        Route::get('/swimming/{visit}', [SwimmingController::class, 'show'])->name('swimming.show');
        Route::post('/swimming/{visit}/check-in', [SwimmingController::class, 'checkIn'])->name('swimming.check-in');
        Route::post('/swimming/{visit}/complete', [SwimmingController::class, 'complete'])->name('swimming.complete');
        Route::post('/swimming/{visit}/post-folio', [SwimmingController::class, 'postFolio'])->name('swimming.post-folio');

        Route::get('/conference', [ConferenceController::class, 'index'])->name('conference.index');
        Route::get('/conference/create', [ConferenceController::class, 'create'])->name('conference.create');
        Route::post('/conference', [ConferenceController::class, 'store'])->name('conference.store');
        Route::get('/conference/{booking}/print', [ConferenceController::class, 'print'])->name('conference.print');
        Route::get('/conference/{booking}', [ConferenceController::class, 'show'])->name('conference.show');
        Route::post('/conference/{booking}/post-folio', [ConferenceController::class, 'postFolio'])->name('conference.post-folio');

        Route::get('/lost-found', [LostFoundController::class, 'index'])->name('lost-found.index');
        Route::post('/lost-found', [LostFoundController::class, 'store'])->name('lost-found.store');
        Route::post('/lost-found/{item}', [LostFoundController::class, 'update'])->name('lost-found.update');
        Route::post('/lost-found/{item}/notify-guest', [LostFoundController::class, 'notifyGuest'])->name('lost-found.notify-guest');
        Route::post('/services/{service}/packages', [PmsServiceController::class, 'storePackage'])->name('services.packages.store');
        Route::post('/services/{service}/packages/from-template', [PmsServiceController::class, 'storePackageFromTemplate'])->name('services.packages.from-template');
        Route::post('/services/{service}/packages/import-all', [PmsServiceController::class, 'importPackages'])->name('services.packages.import-all');
        Route::put('/services/{service}/packages/{package}', [PmsServiceController::class, 'updatePackage'])->name('services.packages.update');
        Route::delete('/services/{service}/packages/{package}', [PmsServiceController::class, 'destroyPackage'])->name('services.packages.destroy');
        Route::delete('/services/{service}', [PmsServiceController::class, 'destroy'])->name('services.destroy');
        Route::post('/services/{service}/categories', [PmsServiceController::class, 'storeCategory'])->name('services.categories.store');
        Route::post('/services/{service}/categories/from-template', [PmsServiceController::class, 'storeCategoryFromTemplate'])->name('services.categories.from-template');
        Route::post('/services/{service}/categories/import-all', [PmsServiceController::class, 'importCategories'])->name('services.categories.import-all');
        Route::put('/services/{service}/categories/{category}', [PmsServiceController::class, 'updateCategory'])->name('services.categories.update');
        Route::delete('/services/{service}/categories/{category}', [PmsServiceController::class, 'destroyCategory'])->name('services.categories.destroy');

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

    Route::middleware('plan.feature:housekeeping_maintenance_system')->group(function () {
        Route::get('/maintenance-hk', [MaintenanceHkController::class, 'index'])->name('maintenance-hk.index');
        Route::post('/maintenance-hk/hk/{task}/start', [MaintenanceHkController::class, 'startTask'])->name('maintenance-hk.hk.start');
        Route::post('/maintenance-hk/hk/{task}/complete', [MaintenanceHkController::class, 'completeTask'])->name('maintenance-hk.hk.complete');
        Route::post('/maintenance-hk/maintenance', [MaintenanceHkController::class, 'storeMaintenanceOrder'])->name('maintenance-hk.maintenance.store');
        Route::post('/maintenance-hk/maintenance/{order}', [MaintenanceHkController::class, 'updateMaintenanceOrder'])->name('maintenance-hk.maintenance.update');
        Route::post('/maintenance-hk/rooms/{unit}/status', [MaintenanceHkController::class, 'updateRoomStatus'])->name('maintenance-hk.rooms.status');
        Route::post('/maintenance-hk/store-requests', [MaintenanceHkController::class, 'storeStoreRequest'])->name('maintenance-hk.store-requests.store');
        Route::put('/maintenance-hk/store-requests/{indent}', [MaintenanceHkController::class, 'updateStoreRequest'])->name('maintenance-hk.store-requests.update');
        Route::delete('/maintenance-hk/store-requests/{indent}', [MaintenanceHkController::class, 'destroyStoreRequest'])->name('maintenance-hk.store-requests.destroy');
        Route::post('/maintenance-hk/store-requests/{indent}/receive', [MaintenanceHkController::class, 'receiveStoreRequest'])->name('maintenance-hk.store-requests.receive');
        Route::post('/maintenance-hk/consumptions', [MaintenanceHkController::class, 'storeConsumption'])->name('maintenance-hk.consumptions.store');
        Route::get('/maintenance-hk/consumptions/{consumption}', [MaintenanceHkController::class, 'showConsumption'])->name('maintenance-hk.consumptions.show');
        Route::get('/maintenance-hk/store-requests/{indent}', [MaintenanceHkController::class, 'showStoreRequest'])->name('maintenance-hk.store-requests.show');
        foreach ([
            ['prefix' => 'maintenance-hk', 'workflow' => 'hk'],
        ] as $procRoute) {
            $wf = $procRoute['workflow'];
            $p = $procRoute['prefix'];
            Route::get("/{$p}/purchase-lists/create", [DepartmentProcurementController::class, 'createPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.create");
            Route::post("/{$p}/purchase-lists", [DepartmentProcurementController::class, 'storePurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.store");
            Route::get("/{$p}/purchase-lists/{purchaseList}/print", [DepartmentProcurementController::class, 'printPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.print");
            Route::get("/{$p}/purchase-lists/{purchaseList}/received", [DepartmentProcurementController::class, 'showReceivedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.received.show");
            Route::get("/{$p}/purchase-lists/{purchaseList}/respond", [DepartmentProcurementController::class, 'respondPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.respond");
            Route::post("/{$p}/purchase-lists/{purchaseList}/respond", [DepartmentProcurementController::class, 'submitRespondPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.respond.submit");
            Route::get("/{$p}/purchase-lists/{purchaseList}/market", [DepartmentProcurementController::class, 'formMarketPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market.form");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-draft", [DepartmentProcurementController::class, 'saveMarketPurchaseDraft'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-draft");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-complete", [DepartmentProcurementController::class, 'completeMarketPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-complete");
            Route::get("/{$p}/purchase-lists/{purchaseList}/market-stock-receive", [DepartmentProcurementController::class, 'formMarketStockReceive'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-stock-receive.form");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-stock-receive", [DepartmentProcurementController::class, 'postMarketStockReceive'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-stock-receive");
            Route::get("/{$p}/purchase-lists/{purchaseList}/edit", [DepartmentProcurementController::class, 'editReturnedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.edit");
            Route::post("/{$p}/purchase-lists/{purchaseList}/resubmit", [DepartmentProcurementController::class, 'resubmitReturnedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.resubmit");
            Route::get("/{$p}/purchase-lists/{purchaseList}", [DepartmentProcurementController::class, 'showPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.show");
        }
    });

    Route::middleware('plan.feature:accounting_expenses_system')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/stay-view/cash-book', [ExpenseController::class, 'cashBook'])->name('stay-view.cash-book');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/deposit', [ExpenseController::class, 'storeDeposit'])->name('expenses.deposit');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::get('/bar', [BarController::class, 'index'])->name('bar.index');
        Route::post('/bar/store-indents/{indent}/receive', [BarController::class, 'receiveStoreIndent'])->name('bar.store-indents.receive');
        Route::post('/bar/sales', [BarController::class, 'storeBarSale'])->name('bar.sales.store');
        foreach ([
            ['prefix' => 'bar', 'workflow' => 'bar'],
            ['prefix' => 'reception', 'workflow' => 'reception'],
        ] as $procRoute) {
            $wf = $procRoute['workflow'];
            $p = $procRoute['prefix'];
            Route::get("/{$p}/purchase-lists/create", [DepartmentProcurementController::class, 'createPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.create");
            Route::post("/{$p}/purchase-lists", [DepartmentProcurementController::class, 'storePurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.store");
            Route::get("/{$p}/purchase-lists/{purchaseList}/print", [DepartmentProcurementController::class, 'printPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.print");
            Route::get("/{$p}/purchase-lists/{purchaseList}/received", [DepartmentProcurementController::class, 'showReceivedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.received.show");
            Route::get("/{$p}/purchase-lists/{purchaseList}/respond", [DepartmentProcurementController::class, 'respondPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.respond");
            Route::post("/{$p}/purchase-lists/{purchaseList}/respond", [DepartmentProcurementController::class, 'submitRespondPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.respond.submit");
            Route::get("/{$p}/purchase-lists/{purchaseList}/market", [DepartmentProcurementController::class, 'formMarketPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market.form");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-draft", [DepartmentProcurementController::class, 'saveMarketPurchaseDraft'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-draft");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-complete", [DepartmentProcurementController::class, 'completeMarketPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-complete");
            Route::get("/{$p}/purchase-lists/{purchaseList}/market-stock-receive", [DepartmentProcurementController::class, 'formMarketStockReceive'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-stock-receive.form");
            Route::post("/{$p}/purchase-lists/{purchaseList}/market-stock-receive", [DepartmentProcurementController::class, 'postMarketStockReceive'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.market-stock-receive");
            Route::get("/{$p}/purchase-lists/{purchaseList}/edit", [DepartmentProcurementController::class, 'editReturnedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.edit");
            Route::post("/{$p}/purchase-lists/{purchaseList}/resubmit", [DepartmentProcurementController::class, 'resubmitReturnedPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.resubmit");
            Route::get("/{$p}/purchase-lists/{purchaseList}", [DepartmentProcurementController::class, 'showPurchaseList'])
                ->defaults('procurement_workflow', $wf)->name("{$p}.purchase-lists.show");
        }

        Route::get('/reception', [ReceptionController::class, 'index'])->name('reception.index');

        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/report', [PosController::class, 'report'])->name('pos.report');
        Route::post('/pos/orders', [PosController::class, 'store'])->name('pos.orders.store');
        Route::put('/pos/orders/{order}', [PosController::class, 'update'])->name('pos.orders.update');
        Route::get('/pos/orders/{order}', [PosController::class, 'show'])->name('pos.orders.show');
        Route::post('/pos/orders/{order}/bill', [PosController::class, 'bill'])->name('pos.orders.bill');
        Route::post('/pos/orders/{order}/pay', [PosController::class, 'pay'])->name('pos.orders.pay');
        Route::delete('/pos/orders/{order}', [PosController::class, 'destroy'])->name('pos.orders.destroy');
        Route::get('/bar/items/create', [BarController::class, 'createItem'])->name('bar.items.create');
        Route::post('/bar/items', [BarController::class, 'storeItem'])->name('bar.items.store');
        Route::get('/bar/items/{item}/edit', [BarController::class, 'editItem'])->name('bar.items.edit');
        Route::put('/bar/items/{item}', [BarController::class, 'updateItem'])->name('bar.items.update');
        Route::delete('/bar/items/{item}', [BarController::class, 'destroyItem'])->name('bar.items.destroy');

        Route::get('/accounts', [AccountsController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/companies', [AccountsController::class, 'storeCompany'])->name('accounts.companies.store');
        Route::post('/accounts/vendors', [AccountsController::class, 'storeVendor'])->name('accounts.vendors.store');
        Route::get('/accounts/purchase-orders/create', [AccountsController::class, 'createPurchaseOrder'])->name('accounts.purchase-orders.create');
        Route::post('/accounts/purchase-orders', [AccountsController::class, 'storePurchaseOrder'])->name('accounts.purchase-orders.store');
        Route::post('/accounts/purchase-orders/{order}/pay', [AccountsController::class, 'payPurchaseOrder'])->name('accounts.purchase-orders.pay');
        Route::post('/accounts/bar-purchase-lists/{purchaseList}/approve', [AccountsController::class, 'approveBarPurchaseList'])->name('accounts.bar-purchase-lists.approve');
        Route::post('/accounts/bar-purchase-lists/{purchaseList}/reject', [AccountsController::class, 'rejectBarPurchaseList'])->name('accounts.bar-purchase-lists.reject');
        Route::post('/accounts/store-purchase-orders/{order}/approve', [AccountsController::class, 'approveStoreDeskPurchaseOrder'])->name('accounts.store-purchase-orders.approve');
        Route::post('/accounts/store-purchase-orders/{order}/reject', [AccountsController::class, 'rejectStoreDeskPurchaseOrder'])->name('accounts.store-purchase-orders.reject');
        Route::get('/accounts/reconciliation/export', [AccountsController::class, 'exportReconciliation'])->name('accounts.reconciliation.export');

        Route::get('/stores', [StoresController::class, 'index'])->name('stores.index');
        Route::post('/stores/department-purchase-lists/batch-submit', [StoresController::class, 'batchSubmitDepartmentPurchaseLists'])
            ->name('stores.department-purchase-lists.batch-submit');
        Route::post('/stores/department-purchase-lists/{purchaseList}/return', [StoresController::class, 'returnDepartmentPurchaseList'])
            ->name('stores.department-purchase-lists.return');
        Route::post('/stores/department-purchase-lists/{purchaseList}/submit-to-accounts', [StoresController::class, 'submitDepartmentPurchaseListToAccounts'])
            ->name('stores.department-purchase-lists.submit-to-accounts');
        Route::get('/stores/reports/download', [StoresController::class, 'downloadStoreReport'])->name('stores.reports.download');
        Route::post('/stores/purchase-orders', [StoresController::class, 'storePurchaseOrder'])->name('stores.purchase-orders.store');
        Route::put('/stores/purchase-orders/{order}', [StoresController::class, 'updatePurchaseOrder'])->name('stores.purchase-orders.update');
        Route::post('/stores/purchase-orders/{order}/approve', [StoresController::class, 'approvePurchaseOrder'])->name('stores.purchase-orders.approve');
        Route::post('/stores/purchase-orders/{order}/submit-to-accounts', [StoresController::class, 'submitStorePurchaseOrderToAccounts'])->name('stores.purchase-orders.submit-to-accounts');
        Route::post('/stores/purchase-orders/{order}/invoice', [StoresController::class, 'invoicePurchaseOrder'])->name('stores.purchase-orders.invoice');
        Route::get('/stores/purchase-orders/{order}/print', [StoresController::class, 'printStorePurchaseOrder'])->name('stores.purchase-orders.print');
        Route::get('/stores/purchase-orders/{order}/invoice-print', [StoresController::class, 'printStorePurchaseOrderInvoice'])->name('stores.purchase-orders.invoice-print');
        Route::post('/stores/purchase-orders/{order}/invoice-email', [StoresController::class, 'emailStorePurchaseOrderInvoice'])->name('stores.purchase-orders.invoice-email');
        Route::get('/stores/purchase-orders/{order}/receive', [StoresController::class, 'showPurchaseOrderGoodsReceive'])->name('stores.purchase-orders.receive');
        Route::post('/stores/purchase-orders/{order}/receive-draft', [StoresController::class, 'savePurchaseOrderReceiveDraft'])->name('stores.purchase-orders.receive-draft');
        Route::post('/stores/invoices/{order}/goods-receive', [StoresController::class, 'storeGoodsReceive'])->name('stores.invoices.goods-receive');
        Route::get('/stores/consumptions/{consumption}', [StoresController::class, 'showConsumption'])->name('stores.consumptions.show');
        Route::get('/stores/indents/{indent}', [StoresController::class, 'showIndent'])->name('stores.indents.show');
        Route::post('/stores/consumptions', [StoresController::class, 'storeConsumption'])->name('stores.consumptions.store');
        Route::post('/stores/indents', [StoresController::class, 'storeIndent'])->name('stores.indents.store');
        Route::put('/stores/indents/{indent}', [StoresController::class, 'updateIndent'])->name('stores.indents.update');
        Route::post('/stores/indents/{indent}/approve', [StoresController::class, 'approveIndent'])->name('stores.indents.approve');
        Route::post('/stores/indents/{indent}/receive', [StoresController::class, 'receiveIndent'])->name('stores.indents.receive');
        Route::delete('/stores/indents/{indent}', [StoresController::class, 'destroyIndent'])->name('stores.indents.destroy');
        Route::delete('/stores/purchase-orders/{order}', [StoresController::class, 'destroyPurchaseOrder'])->name('stores.purchase-orders.destroy');
        Route::get('/stores/items/sell-price-recommendations', [StoresController::class, 'itemSellPriceRecommendations'])->name('stores.items.sell-price-recommendations');
        Route::post('/stores/items', [StoresController::class, 'storeItem'])->name('stores.items.store');
        Route::put('/stores/items/{item}', [StoresController::class, 'updateItem'])->name('stores.items.update');
        Route::delete('/stores/items/{item}', [StoresController::class, 'destroyItem'])->name('stores.items.destroy');
        Route::post('/stores/categories', [StoresController::class, 'storeCategory'])->name('stores.categories.store');
        Route::get('/stores/categories/{category}/catalog-options', [StoresController::class, 'categoryCatalogOptions'])->name('stores.categories.catalog-options');
        Route::put('/stores/categories/{category}', [StoresController::class, 'updateCategory'])->name('stores.categories.update');
        Route::delete('/stores/categories/{category}', [StoresController::class, 'destroyCategory'])->name('stores.categories.destroy');
        Route::post('/stores/tax-groups', [StoresController::class, 'storeTaxGroup'])->name('stores.tax-groups.store');
        Route::put('/stores/tax-groups/{taxGroup}', [StoresController::class, 'updateTaxGroup'])->name('stores.tax-groups.update');
        Route::delete('/stores/tax-groups/{taxGroup}', [StoresController::class, 'destroyTaxGroup'])->name('stores.tax-groups.destroy');
        Route::post('/stores/packages', [StoresController::class, 'storePackage'])->name('stores.packages.store');
        Route::put('/stores/packages/{package}', [StoresController::class, 'updatePackage'])->name('stores.packages.update');
        Route::delete('/stores/packages/{package}', [StoresController::class, 'destroyPackage'])->name('stores.packages.destroy');
        Route::post('/stores/vendors', [StoresController::class, 'storeVendor'])->name('stores.vendors.store');
        Route::put('/stores/vendors/{vendor}', [StoresController::class, 'updateVendor'])->name('stores.vendors.update');
        Route::delete('/stores/vendors/{vendor}', [StoresController::class, 'destroyVendor'])->name('stores.vendors.destroy');
        Route::post('/stores/departments', [StoresController::class, 'storeDepartment'])->name('stores.departments.store');
        Route::put('/stores/departments/{department}', [StoresController::class, 'updateDepartment'])->name('stores.departments.update');
        Route::delete('/stores/departments/{department}', [StoresController::class, 'destroyDepartment'])->name('stores.departments.destroy');

        Route::get('/department-stores', [DepartmentStoresController::class, 'index'])->name('department-stores.index');
        Route::post('/department-stores/store-requests', [DepartmentStoresController::class, 'storeStoreRequest'])->name('department-stores.store-requests.store');
        Route::put('/department-stores/store-requests/{indent}', [DepartmentStoresController::class, 'updateStoreRequest'])->name('department-stores.store-requests.update');
        Route::delete('/department-stores/store-requests/{indent}', [DepartmentStoresController::class, 'destroyStoreRequest'])->name('department-stores.store-requests.destroy');
        Route::post('/department-stores/store-requests/{indent}/receive', [DepartmentStoresController::class, 'receiveStoreRequest'])->name('department-stores.store-requests.receive');
        Route::get('/department-stores/store-requests/{indent}', [DepartmentStoresController::class, 'showStoreRequest'])->name('department-stores.store-requests.show');
        Route::post('/department-stores/consumptions', [DepartmentStoresController::class, 'storeConsumption'])->name('department-stores.consumptions.store');
        Route::get('/department-stores/consumptions/{consumption}', [DepartmentStoresController::class, 'showConsumption'])->name('department-stores.consumptions.show');
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
        Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
        Route::post('/hr/departments', [HrController::class, 'storeDepartment'])->name('hr.departments.store');
        Route::put('/hr/departments/{department}', [HrController::class, 'updateDepartment'])->name('hr.departments.update');
        Route::delete('/hr/departments/{department}', [HrController::class, 'destroyDepartment'])->name('hr.departments.destroy');
        Route::post('/hr/attendance', [HrController::class, 'storeAttendance'])->name('hr.attendance.store');

        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('staff', StaffController::class)->except(['show']);
    });
});

Route::middleware(['auth', 'platform.owner'])->group(function () {
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
