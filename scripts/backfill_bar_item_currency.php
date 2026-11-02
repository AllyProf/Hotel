<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hotel = App\Models\Hotel::first();
if (! $hotel) {
    echo "No hotel\n";
    exit(1);
}

$bar = app(App\Services\Bar\BarCatalogService::class)->barServiceForHotel($hotel);
if (! $bar) {
    echo "No bar service\n";
    exit(1);
}

$currency = app(App\Services\Bar\BarPurchaseListService::class)->budgetCurrencyForHotel($hotel);
$updated = App\Models\HotelPmsServiceItem::query()
    ->where('hotel_pms_service_id', $bar->id)
    ->where(function ($q) use ($currency) {
        $q->whereNull('currency')->orWhere('currency', '!=', $currency);
    })
    ->update(['currency' => $currency]);

echo "Bar operating currency: {$currency}\n";
echo "Updated {$updated} catalog item(s).\n";
