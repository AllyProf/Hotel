<?php

return [
    'pricing_units' => [
        'fixed' => 'Fixed amount',
        'per_day' => 'Per day',
        'per_person' => 'Per person',
        'per_use' => 'Per use',
    ],

    'templates' => [
        'restaurant' => [
            'name' => 'Restaurant',
            'icon' => 'fa fa-cutlery',
            'product_based' => true,
            'uses_packages' => true,
        ],
        'bar' => [
            'name' => 'Bar',
            'icon' => 'fa fa-glass',
            'product_based' => true,
            'uses_packages' => true,
        ],
        'swimming' => [
            'name' => 'Swimming',
            'icon' => 'fa fa-tint',
            'product_based' => true,
            'uses_packages' => false,
            'defaults' => ['pricing_unit' => 'per_person'],
        ],
        'parking' => [
            'name' => 'Parking',
            'icon' => 'fa fa-car',
            'product_based' => false,
            'defaults' => ['pricing_unit' => 'per_day'],
        ],
        'laundry' => [
            'name' => 'Laundry',
            'icon' => 'fa fa-shirtsinbulk',
            'product_based' => true,
            'uses_packages' => true,
            'defaults' => ['pricing_unit' => 'per_use'],
        ],
        'spa' => [
            'name' => 'Spa',
            'icon' => 'fa fa-leaf',
            'product_based' => true,
            'uses_packages' => false,
        ],
        'gym' => [
            'name' => 'Gym',
            'icon' => 'fa fa-heartbeat',
            'product_based' => true,
            'uses_packages' => false,
        ],
        'room_service' => [
            'name' => 'Room Service',
            'icon' => 'fa fa-bed',
            'product_based' => true,
            'uses_packages' => true,
        ],
        'conference' => [
            'name' => 'Conference',
            'icon' => 'fa fa-users',
            'product_based' => true,
            'uses_packages' => false,
            'defaults' => ['pricing_unit' => 'per_day'],
        ],
        'transport' => [
            'name' => 'Transport',
            'icon' => 'fa fa-plane',
            'product_based' => false,
            'defaults' => ['pricing_unit' => 'fixed'],
        ],
    ],

    'category_templates' => [
        'restaurant' => [
            'breakfast' => ['name' => 'Breakfast', 'icon' => 'fa fa-coffee'],
            'starters' => ['name' => 'Starters', 'icon' => 'fa fa-leaf'],
            'main_course' => ['name' => 'Main Course', 'icon' => 'fa fa-cutlery'],
            'desserts' => ['name' => 'Desserts', 'icon' => 'fa fa-birthday-cake'],
            'beverages' => ['name' => 'Beverages', 'icon' => 'fa fa-glass'],
        ],
        'bar' => [
            'beers' => ['name' => 'Beers', 'icon' => 'fa fa-beer'],
            'spirits' => ['name' => 'Spirits', 'icon' => 'fa fa-glass'],
            'wines' => ['name' => 'Wines', 'icon' => 'fa fa-glass'],
            'cocktails' => ['name' => 'Cocktails', 'icon' => 'fa fa-certificate'],
            'soft_drinks' => ['name' => 'Soft Drinks', 'icon' => 'fa fa-coffee'],
        ],
        'swimming' => [
            'day_pass' => ['name' => 'Day Pass', 'icon' => 'fa fa-ticket'],
            'membership' => ['name' => 'Membership', 'icon' => 'fa fa-id-card'],
            'equipment' => ['name' => 'Equipment Rental', 'icon' => 'fa fa-life-ring'],
        ],
        'parking' => [
            'self_parking' => ['name' => 'Self Parking', 'icon' => 'fa fa-car'],
            'valet' => ['name' => 'Valet', 'icon' => 'fa fa-key'],
            'long_stay' => ['name' => 'Long Stay', 'icon' => 'fa fa-calendar'],
        ],
        'laundry' => [
            'wash_and_fold' => ['name' => 'Wash & Fold', 'icon' => 'fa fa-shirtsinbulk'],
            'dry_cleaning' => ['name' => 'Dry Cleaning', 'icon' => 'fa fa-tag'],
            'pressing' => ['name' => 'Pressing', 'icon' => 'fa fa-magic'],
        ],
        'spa' => [
            'massage' => ['name' => 'Massage', 'icon' => 'fa fa-hand-paper-o'],
            'facial' => ['name' => 'Facial', 'icon' => 'fa fa-smile-o'],
            'body_treatment' => ['name' => 'Body Treatment', 'icon' => 'fa fa-leaf'],
            'sauna' => ['name' => 'Sauna & Steam', 'icon' => 'fa fa-fire'],
        ],
        'gym' => [
            'day_access' => ['name' => 'Day Access', 'icon' => 'fa fa-calendar-check-o'],
            'personal_training' => ['name' => 'Personal Training', 'icon' => 'fa fa-user'],
            'classes' => ['name' => 'Classes', 'icon' => 'fa fa-users'],
        ],
        'room_service' => [
            'food' => ['name' => 'Food', 'icon' => 'fa fa-cutlery'],
            'beverages' => ['name' => 'Beverages', 'icon' => 'fa fa-glass'],
            'snacks' => ['name' => 'Snacks', 'icon' => 'fa fa-shopping-basket'],
        ],
        'conference' => [
            'room_hire' => ['name' => 'Room Hire', 'icon' => 'fa fa-building'],
            'catering' => ['name' => 'Catering', 'icon' => 'fa fa-cutlery'],
            'av_equipment' => ['name' => 'AV Equipment', 'icon' => 'fa fa-television'],
        ],
        'transport' => [
            'airport_transfer' => ['name' => 'Airport Transfer', 'icon' => 'fa fa-plane'],
            'city_tour' => ['name' => 'City Tour', 'icon' => 'fa fa-map-marker'],
            'car_rental' => ['name' => 'Car Rental', 'icon' => 'fa fa-car'],
        ],
    ],

    'package_templates' => [
        'restaurant' => [
            'plate' => ['name' => 'Plate', 'abbreviation' => 'plate', 'icon' => 'fa fa-circle'],
            'portion' => ['name' => 'Portion', 'abbreviation' => 'ptn', 'icon' => 'fa fa-cutlery'],
            'set' => ['name' => 'Set', 'abbreviation' => 'set', 'icon' => 'fa fa-object-group'],
        ],
        'bar' => [
            'bottle' => ['name' => 'Bottle', 'abbreviation' => 'btl', 'icon' => 'fa fa-beer'],
            'can' => ['name' => 'Can', 'abbreviation' => 'can', 'icon' => 'fa fa-circle-o'],
            'glass' => ['name' => 'Glass', 'abbreviation' => 'gls', 'icon' => 'fa fa-glass'],
            'crate' => ['name' => 'Crate', 'abbreviation' => 'crt', 'icon' => 'fa fa-archive'],
            'box' => ['name' => 'Box', 'abbreviation' => 'box', 'icon' => 'fa fa-cubes'],
        ],
        'laundry' => [
            'kg' => ['name' => 'Kilogram', 'abbreviation' => 'kg', 'icon' => 'fa fa-balance-scale'],
            'piece' => ['name' => 'Piece', 'abbreviation' => 'pc', 'icon' => 'fa fa-tag'],
            'bag' => ['name' => 'Bag', 'abbreviation' => 'bag', 'icon' => 'fa fa-shopping-bag'],
        ],
        'room_service' => [
            'plate' => ['name' => 'Plate', 'abbreviation' => 'plate', 'icon' => 'fa fa-circle'],
            'tray' => ['name' => 'Tray', 'abbreviation' => 'tray', 'icon' => 'fa fa-inbox'],
            'item' => ['name' => 'Item', 'abbreviation' => 'item', 'icon' => 'fa fa-tag'],
        ],
    ],

    'swimming_currencies' => [
        'TZS' => 'TZ Shilling',
        'USD' => 'US Dollar',
    ],

    'conference_currencies' => [
        'TZS' => 'TZ Shilling',
        'USD' => 'US Dollar',
    ],

    'conference_pricing_units' => [
        'per_day' => 'Per day',
        'per_half_day' => 'Per half-day',
        'per_person' => 'Per delegate',
        'fixed' => 'Fixed package',
    ],

    'swimming_pricing_units' => [
        'per_person' => 'Per person',
        'per_hour' => 'Per hour',
        'per_day' => 'Per day',
        'per_use' => 'Per use / session',
        'fixed' => 'Fixed (flat fee)',
    ],

    'swimming_audiences' => [
        'adult' => 'Adult',
        'child' => 'Child',
        'infant' => 'Infant (free or custom)',
        'senior' => 'Senior',
        'guest' => 'General / any guest',
    ],

    'vehicle_types' => [
        'car' => 'Car',
        'moto' => 'Moto',
        'bus' => 'Bus',
    ],

    'facility_defaults' => [
        'parking' => [
            'complimentary_for_inhouse' => false,
            'grace_period_minutes' => 30,
            'require_plate_number' => true,
            'require_vehicle_type' => true,
            'collect_vehicle_notes' => true,
        ],
        'swimming' => [
            'open_time' => '07:00',
            'close_time' => '19:00',
            'complimentary_for_inhouse' => false,
            'allow_desk_complimentary_override' => true,
            'max_persons_per_entry' => 10,
            'require_room_for_inhouse' => true,
            'require_contact_for_walkin' => true,
            'post_to_folio' => true,
            'allow_walk_in' => true,
        ],
        'conference' => [
            'business_hours_start' => '08:00',
            'business_hours_end' => '22:00',
            'require_contact_for_external' => true,
            'require_room_for_inhouse' => false,
            'post_to_folio' => true,
            'allow_external_booking' => true,
            'default_booking_status' => 'confirmed',
        ],
        'bar' => [
            'post_to_folio' => true,
            'allow_walk_in' => true,
            'items' => [
                ['name' => 'Local Beer'],
                ['name' => 'Imported Beer'],
                ['name' => 'House Wine (Glass)'],
                ['name' => 'Soft Drink'],
                ['name' => 'Mineral Water'],
                ['name' => 'Cocktail of the Day'],
            ],
        ],
        'laundry' => [
            'turnaround_hours' => 24,
            'express_surcharge_percent' => 50,
            'complimentary_pieces' => 0,
            'require_room_number' => true,
            'post_to_folio' => true,
            'items' => [
                ['name' => 'Bath Towel'],
                ['name' => 'Hand Towel'],
                ['name' => 'Double Bedsheet'],
                ['name' => 'Single Bedsheet'],
                ['name' => 'Pillow Cover'],
                ['name' => 'Duvet'],
                ['name' => 'Shirt'],
                ['name' => 'Trousers'],
                ['name' => 'Dress'],
            ],
        ],
    ],
];
