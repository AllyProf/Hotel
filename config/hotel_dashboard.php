<?php

return [
    'channel_manager' => [
        'title' => 'Channel Manager',
        'intro' => 'Keep your rates and room availability in sync across all major booking channels — automatically.',
        'status' => [
            'active' => 'Your property is connected and syncing with OTAs.',
            'pending' => 'Complete the steps below to start syncing with OTAs.',
            'platform_pending' => 'Your account is ready. Our team is finishing the connection — you can set up rooms and mapping in the meantime.',
        ],
        'steps' => [
            [
                'title' => 'Set up your rooms & rate plans',
                'body' => 'Add room types, counts, and rate plans under Settings so channels know what you sell.',
                'route' => 'hotel.settings.index',
                'params' => ['tab' => 'rooms'],
                'label' => 'Open Settings',
            ],
            [
                'title' => 'Map your OTAs',
                'body' => 'Link your room types and rate plans to each OTA you sell on.',
                'route' => 'hotel.channel-manager.ota-mapping',
                'label' => 'Mapping Setup',
            ],
            [
                'title' => 'Update availability & rates',
                'body' => 'Push daily room counts and rate changes so OTAs always show correct inventory.',
                'route' => 'hotel.channel-manager.update-rooms',
                'label' => 'Update Rooms',
            ],
        ],
        'quick_links' => [
            ['label' => 'Update Rates', 'route' => 'hotel.channel-manager.update-rates', 'icon' => 'fa fa-dollar'],
            ['label' => 'Update Rooms', 'route' => 'hotel.channel-manager.update-rooms', 'icon' => 'fa fa-bed'],
            ['label' => 'Live Bookings', 'route' => 'hotel.channel-manager.live-bookings', 'icon' => 'fa fa-columns'],
            ['label' => 'OTA Mapping', 'route' => 'hotel.channel-manager.ota-mapping', 'icon' => 'fa fa-cogs'],
            ['label' => 'Hotel Settings', 'route' => 'hotel.settings.index', 'icon' => 'fa fa-cog'],
        ],
    ],

    'booking_engine' => [
        'title' => 'Direct Booking',
        'intro' => 'Let guests book directly on your website — no OTA commission.',
        'steps' => [
            [
                'title' => 'Configure your booking page',
                'body' => 'Set policies, amenities, photos, and tracking under Booking Engine settings.',
                'route' => 'hotel.settings.index',
                'params' => ['tab' => 'be'],
                'label' => 'BE Settings',
            ],
            [
                'title' => 'Share your booking link',
                'body' => 'Add the link to your website, Google Business profile, social media, or WhatsApp.',
                'anchor' => '#be-booking-link',
                'label' => 'Copy link',
            ],
        ],
    ],
];
