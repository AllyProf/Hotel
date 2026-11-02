<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$h = \App\Models\Hotel::query()->first();
echo 'Hotel currency: '.$h->currency."\n";
echo 'Bar budget currency: '.app(\App\Services\Bar\BarPurchaseListService::class)->budgetCurrencyForHotel($h)."\n";
$svc = \App\Models\HotelPmsService::query()->where('hotel_id', $h->id)->where('template_key', 'bar')->first();
echo 'Bar service currency: '.($svc->currency ?? 'null')."\n";
foreach (['House Wine (Glass)', 'Imported Beer'] as $name) {
    $i = \App\Models\HotelPmsServiceItem::query()
        ->where('hotel_id', $h->id)
        ->where('item_name', $name)
        ->first();
    if ($i) {
        echo "{$name}: item currency=".($i->currency ?? 'null')." unit_price={$i->unit_price}\n";
    }
}
