<?php

return [
    'title' => 'Booking Engine',
    'max_coupons' => 50,
    'max_photos_per_room' => 8,
    'nav' => [
        [
            'label' => 'Live Link',
            'route' => 'hotel.booking-engine.live-link',
            'route_is' => 'hotel.booking-engine.live-link',
        ],
        [
            'label' => 'BE Coupons',
            'route' => 'hotel.booking-engine.coupons',
            'route_is' => 'hotel.booking-engine.coupons*',
        ],
        [
            'label' => 'Photos',
            'route' => 'hotel.booking-engine.photos',
            'route_is' => 'hotel.booking-engine.photos*',
        ],
        [
            'label' => 'Website Config',
            'route' => 'hotel.booking-engine.website-config',
            'route_is' => 'hotel.booking-engine.website-config*',
        ],
    ],
];
