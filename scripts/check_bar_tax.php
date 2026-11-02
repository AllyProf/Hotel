<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hotel = App\Models\Hotel::first();
$bar = $hotel->pmsServices()->where('template_key', 'bar')->first();
$pos = app(App\Services\Pos\PosOrderService::class);
echo "Bar tax_category: ".($bar->tax_category ?? 'null')."\n";
echo "Bar tax_inclusive: ".($bar->tax_inclusive ? 'Y' : 'N')."\n";
echo "POS taxPercentForService: ".$pos->taxPercentForService($bar)."\n";
echo "config default: ".config('hotel_pos.default_tax_percent')."\n";
