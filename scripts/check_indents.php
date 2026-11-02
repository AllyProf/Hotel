<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$h = \App\Models\Hotel::query()->first();
echo "Bar category: ";
$bar = \App\Models\HotelStoreCategory::query()->where('hotel_id', $h->id)->where('name', 'Bar')->first();
echo $bar ? "id={$bar->id} dept_link={$bar->hotel_department_id}\n" : "missing\n";

foreach (\App\Models\HotelStoreIndent::query()->where('hotel_id', $h->id)->orderByDesc('id')->get() as $i) {
    $i->load('department', 'storeCategory');
    echo "{$i->indent_number} dept={$i->department?->name} cat={$i->storeCategory?->name}\n";
}
