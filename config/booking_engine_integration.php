<?php

return [
    'getting_started' => [
        'intro' => 'Enable direct bookings on your hotel website. Guests book without OTAs — rates and inventory sync from your PMS settings.',
        'steps' => [
            [
                'number' => 1,
                'title' => 'Configure rooms & rate plans',
                'body' => 'Set up room types, rate plans, amenities, and policies under Live Hotel Settings. These feed your public booking engine.',
                'route' => 'hotel.settings.index',
                'route_params' => ['tab' => 'be'],
                'label' => 'Open BE Settings',
            ],
            [
                'number' => 2,
                'title' => 'Publish your direct booking link',
                'body' => 'Copy the booking URL below and embed it on your website, social channels, or WhatsApp. Optionally add tracking IDs (GTM, Facebook Pixel) in BE settings.',
                'anchor' => '#be-direct-link',
                'label' => 'View booking link',
            ],
        ],
    ],
];
