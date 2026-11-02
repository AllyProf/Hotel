<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hotel = App\Models\Hotel::first();
$pos = app(App\Services\Pos\PosOrderService::class);
foreach ($pos->posDepartmentsForHotel($hotel) as $d) {
    echo $d->name.' -> '.($d->posCatalogService?->name ?? '?')."\n";
}
