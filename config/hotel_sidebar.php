<?php

/**
 * Hotel sidebar menu — grouped like Aiosell Live.
 * Each group/item lists plan feature keys; visible when the hotel plan includes any of them.
 */
return [
    'account_group' => [
        'key' => 'my_hotel',
        'label' => 'My Hotel SaaS',
        'icon' => 'fa fa-user',
        'always_show' => true,
        'items' => [
            ['label' => 'Settings', 'icon' => 'fa fa-cog', 'route' => 'hotel.settings.index', 'route_is' => 'hotel.settings.*'],
            ['label' => 'Rooms', 'icon' => 'fa fa-bed', 'route' => 'hotel.rooms.index', 'route_is' => 'hotel.rooms.*'],
            ['label' => 'Branches', 'icon' => 'fa fa-sitemap', 'features' => ['groups_chain_hotels_system'], 'route' => 'hotel.branches.index', 'route_is' => 'hotel.branches.*'],
            ['label' => 'Roles & Permissions', 'icon' => 'fa fa-shield', 'features' => ['hr_employee_management_system'], 'route' => 'hotel.roles.index', 'route_is' => 'hotel.roles.*'],
            ['label' => 'Staff', 'icon' => 'fa fa-users', 'features' => ['hr_employee_management_system'], 'route' => 'hotel.staff.index', 'route_is' => 'hotel.staff.*'],
            ['label' => 'Invoices', 'icon' => 'fa fa-file-text-o', 'route' => null, 'route_is' => null],
        ],
    ],

    'groups' => [
        [
            'key' => 'channel_manager_rms',
            'label' => 'Channel Manager & RMS',
            'icon' => 'fa fa-server',
            'features' => ['channel_manager', 'revenue_management_software', 'reviews_reputation_management'],
            'items' => [
                ['label' => 'Update Rates', 'icon' => 'fa fa-dollar', 'features' => ['channel_manager', 'revenue_management_software'], 'route' => 'hotel.channel-manager.update-rates', 'route_is' => 'hotel.channel-manager.update-rates*'],
                ['label' => 'Update Rooms', 'icon' => 'fa fa-bed', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.update-rooms', 'route_is' => 'hotel.channel-manager.update-rooms*'],
                ['label' => 'Bulk Update', 'icon' => 'fa fa-dot-circle-o', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.bulk-update', 'route_is' => 'hotel.channel-manager.bulk-update*'],
                ['label' => 'Live Bookings', 'icon' => 'fa fa-columns', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.live-bookings', 'route_is' => 'hotel.channel-manager.live-bookings*'],
                ['label' => 'OTA Commissions', 'icon' => 'fa fa-percent', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.ota-commission', 'route_is' => 'hotel.channel-manager.ota-commission'],
                ['label' => 'Messages', 'icon' => 'fa fa-envelope-o', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.messages.index', 'route_is' => 'hotel.channel-manager.messages.*'],
                ['label' => 'OTA Content', 'icon' => 'fa fa-th', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.ota-content.index', 'route_is' => 'hotel.channel-manager.ota-content.*'],
                ['label' => 'Reviews', 'icon' => 'fa fa-star', 'features' => ['reviews_reputation_management'], 'route' => 'hotel.channel-manager.reviews.index', 'route_is' => 'hotel.channel-manager.reviews.*'],
                ['label' => 'Mapping Setup', 'icon' => 'fa fa-cogs', 'features' => ['channel_manager'], 'route' => 'hotel.channel-manager.ota-mapping', 'route_is' => 'hotel.channel-manager.ota-mapping'],
                ['label' => 'Payment Gateway', 'icon' => 'fa fa-link', 'features' => ['channel_manager'], 'route' => 'hotel.payment-gateway.index', 'route_is' => 'hotel.payment-gateway.*'],
                ['label' => 'Logs', 'icon' => 'fa fa-file-text-o', 'features' => ['channel_manager', 'revenue_management_software'], 'route' => 'hotel.logs.index', 'route_is' => 'hotel.logs.*'],
            ],
        ],
        [
            'key' => 'pms',
            'label' => 'PMS',
            'icon' => 'fa fa-adjust',
            'features' => ['property_management_system', 'crm_leads_management_system', 'accounting_expenses_system'],
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'fa fa-line-chart', 'features' => ['property_management_system'], 'route' => 'hotel.dashboard', 'route_is' => 'hotel.dashboard'],
                ['label' => 'Stay View', 'icon' => 'fa fa-bed', 'features' => ['property_management_system'], 'route' => 'hotel.stay-view', 'route_is' => 'hotel.stay-view*'],
                ['label' => 'Rooms View', 'icon' => 'fa fa-calendar', 'features' => ['property_management_system'], 'route' => 'hotel.room-view', 'route_is' => 'hotel.room-view*'],
                ['label' => 'Check-in', 'icon' => 'fa fa-sign-in', 'features' => ['property_management_system'], 'route' => 'hotel.check-in.index', 'route_is' => 'hotel.check-in.*'],
                ['label' => 'Check-out', 'icon' => 'fa fa-sign-out', 'features' => ['property_management_system'], 'route' => 'hotel.check-out.index', 'route_is' => 'hotel.check-out.*'],
                ['label' => 'Lost & Found', 'icon' => 'fa fa-search', 'features' => ['property_management_system'], 'route' => 'hotel.lost-found.index', 'route_is' => 'hotel.lost-found.*'],
                ['label' => 'Reservation', 'icon' => 'fa fa-plus', 'features' => ['property_management_system'], 'route' => 'hotel.reservations.index', 'route_is' => 'hotel.reservations.*'],
                ['label' => 'Guests', 'icon' => 'fa fa-id-card-o', 'features' => ['property_management_system'], 'route' => 'hotel.guests.index', 'route_is' => 'hotel.guests.*'],
                ['label' => 'Companies', 'icon' => 'fa fa-building-o', 'features' => ['property_management_system'], 'route' => 'hotel.companies.index', 'route_is' => 'hotel.companies.*'],
                ['label' => 'Services', 'icon' => 'fa fa-th-large', 'features' => ['property_management_system'], 'route' => 'hotel.services.index', 'route_is' => 'hotel.services.*'],
                ['label' => 'Laundry', 'icon' => 'fa fa-shirtsinbulk', 'features' => ['property_management_system'], 'route' => 'hotel.laundry.index', 'route_is' => 'hotel.laundry.*'],
                ['label' => 'Swimming Pool', 'icon' => 'fa fa-tint', 'features' => ['property_management_system'], 'route' => 'hotel.swimming.index', 'route_is' => 'hotel.swimming.*'],
                ['label' => 'Conference', 'icon' => 'fa fa-users', 'features' => ['property_management_system'], 'route' => 'hotel.conference.index', 'route_is' => 'hotel.conference.*'],
                ['label' => 'Reports', 'icon' => 'fa fa-file-text-o', 'features' => ['property_management_system'], 'route' => 'hotel.reports.index', 'route_is' => 'hotel.reports.*'],
                ['label' => 'Expenses', 'icon' => 'fa fa-dollar', 'features' => ['accounting_expenses_system'], 'route' => 'hotel.expenses.index', 'route_is' => 'hotel.expenses.*'],
                ['label' => 'Leads', 'icon' => 'fa fa-bars', 'features' => ['crm_leads_management_system']],
                ['label' => 'Payment Gateway', 'icon' => 'fa fa-link', 'features' => ['property_management_system'], 'route' => 'hotel.payment-gateway.index', 'route_is' => 'hotel.payment-gateway.*'],
                ['label' => 'Logs', 'icon' => 'fa fa-file-text-o', 'features' => ['property_management_system'], 'route' => 'hotel.logs.index', 'route_is' => 'hotel.logs.*'],
            ],
        ],
        [
            'key' => 'erp',
            'label' => 'ERP',
            'icon' => 'fa fa-line-chart',
            'features' => [
                'point_of_sale_system',
                'housekeeping_maintenance_system',
                'purchase_inventory_management',
                'accounting_expenses_system',
                'hr_employee_management_system',
                'banquets_management_system',
            ],
            'items' => [
                ['label' => 'POS', 'icon' => 'fa fa-money', 'features' => ['point_of_sale_system'], 'route' => 'hotel.pos.index', 'route_is' => 'hotel.pos.*'],
                ['label' => 'Bar', 'icon' => 'fa fa-glass', 'features' => ['point_of_sale_system'], 'route' => 'hotel.bar.index', 'route_is' => 'hotel.bar.*'],
                ['label' => 'Maintenance & HK', 'icon' => 'fa fa-asterisk', 'features' => ['housekeeping_maintenance_system'], 'route' => 'hotel.maintenance-hk.index', 'route_is' => 'hotel.maintenance-hk.*'],
                ['label' => 'Reception', 'icon' => 'fa fa-desktop', 'features' => ['accounting_expenses_system'], 'route' => 'hotel.reception.index', 'route_is' => 'hotel.reception.*'],
                ['label' => 'Stores', 'icon' => 'fa fa-archive', 'features' => ['purchase_inventory_management'], 'route' => 'hotel.stores.index', 'route_is' => 'hotel.stores.*'],
                ['label' => 'Department Stores', 'icon' => 'fa fa-inbox', 'features' => ['purchase_inventory_management'], 'route' => 'hotel.department-stores.index', 'route_is' => 'hotel.department-stores.*'],
                ['label' => 'Accounts', 'icon' => 'fa fa-calculator', 'features' => ['accounting_expenses_system'], 'route' => 'hotel.accounts.index', 'route_is' => 'hotel.accounts.*'],
                ['label' => 'HR', 'icon' => 'fa fa-id-badge', 'features' => ['hr_employee_management_system'], 'route' => 'hotel.hr.index', 'route_is' => 'hotel.hr.*'],
                ['label' => 'Banquets', 'icon' => 'fa fa-university', 'features' => ['banquets_management_system']],
            ],
        ],
        [
            'key' => 'analytics',
            'label' => 'Analytics',
            'icon' => 'fa fa-money',
            'features' => ['revenue_management_software'],
            'items' => [
                ['label' => 'Hotel Performance', 'icon' => 'fa fa-line-chart', 'features' => ['revenue_management_software'], 'route' => 'hotel.analytics.hotel-performance', 'route_is' => 'hotel.analytics.*'],
                ['label' => 'Daily Report', 'icon' => 'fa fa-columns', 'features' => ['revenue_management_software'], 'route' => 'hotel.analytics.daily-report', 'route_is' => 'hotel.analytics.daily-report'],
                ['label' => 'Trend Analysis', 'icon' => 'fa fa-bar-chart', 'features' => ['revenue_management_software'], 'route' => 'hotel.analytics.trend-analysis', 'route_is' => 'hotel.analytics.trend-analysis'],
                ['label' => 'Dynamic Pricing', 'icon' => 'fa fa-area-chart', 'features' => ['revenue_management_software'], 'route' => 'hotel.analytics.dynamic-pricing', 'route_is' => 'hotel.analytics.dynamic-pricing'],
                ['label' => 'Pick Up Report', 'icon' => 'fa fa-chevron-circle-up', 'features' => ['revenue_management_software'], 'route' => 'hotel.analytics.pickup-report', 'route_is' => 'hotel.analytics.pickup-report'],
            ],
        ],
        [
            'key' => 'booking_engine',
            'label' => 'Booking Engine',
            'icon' => 'fa fa-cogs',
            'features' => ['booking_engine_website'],
            'items' => [
                ['label' => 'Live Link', 'icon' => 'fa fa-globe', 'features' => ['booking_engine_website'], 'route' => 'hotel.booking-engine.live-link', 'route_is' => 'hotel.booking-engine.live-link'],
                ['label' => 'BE Coupons', 'icon' => 'fa fa-tag', 'features' => ['booking_engine_website'], 'route' => 'hotel.booking-engine.coupons', 'route_is' => 'hotel.booking-engine.coupons*'],
                ['label' => 'Photos', 'icon' => 'fa fa-picture-o', 'features' => ['booking_engine_website'], 'route' => 'hotel.booking-engine.photos', 'route_is' => 'hotel.booking-engine.photos*'],
                ['label' => 'Website Config', 'icon' => 'fa fa-cog', 'features' => ['booking_engine_website'], 'route' => 'hotel.booking-engine.website-config', 'route_is' => 'hotel.booking-engine.website-config*'],
            ],
        ],
    ],
];
