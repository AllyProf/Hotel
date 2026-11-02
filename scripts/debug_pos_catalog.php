<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hotel = App\Models\Hotel::first();
$pos = app(App\Services\Pos\PosOrderService::class);
$dept = $pos->posDepartmentsForHotel($hotel)->first();
$service = $pos->serviceForPosDepartment($dept);
$payload = $pos->catalogPayload($service, $dept);
echo "Categories:\n";
foreach ($payload['categories'] as $c) {
    echo "  {$c['name']}\n";
}
echo 'Sellable lines: '.count($payload['items'])."\n";
