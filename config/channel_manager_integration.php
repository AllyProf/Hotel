<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Channel Manager integration (provider-agnostic)
    |--------------------------------------------------------------------------
    | Credentials are stored at platform level in platform_settings.integrations.
    | Per-hotel settings only store hotel_code and sandbox mappings.
    */
    'provider_name' => env('CHANNEL_MANAGER_PROVIDER_NAME', 'Channel Manager'),

    'default_base_url' => env('CHANNEL_MANAGER_BASE_URL', 'https://live.aiosell.com/api/v2/cm'),

    'sandbox' => [
        'hotel_code' => env('CHANNEL_MANAGER_SANDBOX_HOTEL_CODE', 'sandbox-pms'),
        'partner_id' => env('CHANNEL_MANAGER_SANDBOX_PARTNER_ID', 'sample-pms'),
        'api_username' => env('CHANNEL_MANAGER_SANDBOX_API_USERNAME'),
        'api_password' => env('CHANNEL_MANAGER_SANDBOX_API_PASSWORD'),
    ],

    'docs_url' => 'https://apidocs.aiosell.com/',

    'getting_started' => [
        'intro' => 'If you want to integrate your software with our Channel Manager and sync rates, inventory, and reservations from all major and regional OTAs — follow the steps below.',
        'steps' => [
            [
                'number' => 1,
                'title' => 'Get property / mapping details, test & integrate the APIs',
                'body' => 'The Channel Manager exposes Inventory + Inventory Restrictions, Rate + Rate Restrictions APIs (PMS → CM) for your software to send rates, inventory and restrictions. In return, expose a Reservation webhook (CM → PMS) to receive bookings. Get room and rateplan codes from the Get Property Details API, read each spec in the API reference below, then push sample inventory and rate updates using your credentials. Once they work, wire real-time pushes whenever rates, inventory, or restrictions change. Use Basic Auth on every call.',
                'actions' => [
                    ['label' => 'Browse APIs', 'anchor' => '#cm-api-endpoints'],
                    ['label' => 'Open Mapping Setup', 'route' => 'hotel.channel-manager.ota-mapping'],
                ],
            ],
            [
                'number' => 2,
                'title' => 'Build & test your reservation webhook',
                'body' => 'Expose a single POST endpoint on your PMS that handles book, modify, and cancel events. Validate the Basic Auth header to confirm the caller is your Channel Manager partner, then test with sample OTA booking payloads before going live.',
                'actions' => [
                    ['label' => 'Webhook URL', 'anchor' => '#cm-webhook-settings'],
                ],
            ],
        ],
    ],

    'overview' => [
        'mapping_note' => 'Call Get Property / Mapping Details first to pull the hotel id, room codes, and rateplan codes you pass into every other API.',
        'auth_note' => 'Every API uses HTTP Basic Auth. Each request must carry an Authorization: Basic <base64(user:pass)> header. Credentials are configured per hotel below — not in source code.',
        'conventions' => [
            ['term' => 'Hotel code', 'description' => 'Unique identifier of a single property. One account can manage many hotels; the hotel code tells the Channel Manager which property a request applies to.'],
            ['term' => 'Partner id {pms}', 'description' => 'Identifier for your PMS / software integration, assigned at onboarding. Used in URL paths, e.g. /update/{pms}.'],
            ['term' => 'Room code', 'description' => 'A room type / category (not a single physical room). Availability is counted per room type.'],
            ['term' => 'Rate plan code', 'description' => 'A sellable rate plan attached to a room type. Format {room}-{occupancy}-{mealplan}, e.g. executive-s-ep.'],
            ['term' => 'Occupancy letter', 'description' => 'Guests the plan is priced for: s single, d double, t triple, q quad, and so on.'],
        ],
        'meal_plans' => [
            ['code' => 'EP', 'name' => 'European Plan', 'includes' => 'Room only, no meals'],
            ['code' => 'CP', 'name' => 'Continental Plan', 'includes' => 'Room + breakfast'],
            ['code' => 'MAP', 'name' => 'Modified American Plan', 'includes' => 'Room + breakfast + one more meal (half board)'],
            ['code' => 'AP', 'name' => 'American Plan', 'includes' => 'Room + all three meals (full board)'],
        ],
    ],

    'endpoints' => [
        ['method' => 'GET', 'name' => 'Get Property / Mapping Details', 'path' => '/property_details/{hotelCode}?partnerId={partnerId}', 'group' => 'mapping'],
        ['method' => 'POST', 'name' => 'Inventory Push', 'path' => '/update/{partnerId}', 'group' => 'push'],
        ['method' => 'POST', 'name' => 'Rate Push', 'path' => '/update-rates/{partnerId}', 'group' => 'push'],
        ['method' => 'POST', 'name' => 'Inventory Restrictions Push', 'path' => '/update/{partnerId}', 'group' => 'restrictions'],
        ['method' => 'POST', 'name' => 'Rate Restrictions Push', 'path' => '/update-rates/{partnerId}', 'group' => 'restrictions'],
        ['method' => 'POST', 'name' => 'Mark No Show', 'path' => '/marknoshow/{partnerId}', 'group' => 'bookings'],
        ['method' => 'POST', 'name' => 'Fetch Inventory', 'path' => '/data/{partnerId}', 'group' => 'fetch'],
        ['method' => 'POST', 'name' => 'Fetch Rates', 'path' => '/data/{partnerId}', 'group' => 'fetch'],
        ['method' => 'POST', 'name' => 'Fetch Reservations', 'path' => '/data/{partnerId}', 'group' => 'fetch'],
        ['method' => 'POST', 'name' => 'Reservation: Book / Modify / Cancel', 'path' => '{webhookUrl}', 'group' => 'webhook', 'inbound' => true],
        ['method' => 'POST', 'name' => 'Channel Multiplier', 'path' => '/channel_multiplier/{partnerId}', 'group' => 'pricing'],
        ['method' => 'POST', 'name' => 'Fetch Messages', 'path' => '/message/{partnerId}', 'group' => 'advanced'],
        ['method' => 'POST', 'name' => 'Fetch Reviews', 'path' => '/message/{partnerId}', 'group' => 'advanced', 'note' => 'Same endpoint with type=review in body'],
    ],

    'sync_channels' => [
        ['label' => 'Booking.com', 'value' => 'booking.com'],
        ['label' => 'Whatsapp', 'value' => 'whatsapp'],
        ['label' => 'AirBnb', 'value' => 'airbnb'],
        ['label' => 'Expedia', 'value' => 'expedia'],
    ],
];
