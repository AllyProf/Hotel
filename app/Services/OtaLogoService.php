<?php

namespace App\Services;

class OtaLogoService
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return collect(config('otas', []))->map(function (array $ota) {
            $slug = $ota['slug'];
            $svgPath = public_path('panel-assets/img/otas/'.$ota['logo']);
            $pngPath = public_path('panel-assets/img/otas/'.$slug.'.png');

            if (file_exists($pngPath)) {
                $ota['logo_url'] = asset('panel-assets/img/otas/'.$slug.'.png');
            } elseif (file_exists($svgPath)) {
                $ota['logo_url'] = asset('panel-assets/img/otas/'.$ota['logo']);
            } else {
                $ota['logo_url'] = 'https://www.google.com/s2/favicons?domain='.$ota['domain'].'&sz=128';
            }

            return $ota;
        })->all();
    }
}
