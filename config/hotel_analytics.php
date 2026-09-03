<?php

return [
    'hotel_performance' => [
        'title' => 'Hotel Performance',
        'parent_label' => 'Analytics',
        'default_view' => 'monthly',
        'views' => [
            'monthly' => 'Monthly',
            'weekly' => 'Weekly',
            'daily' => 'Daily',
        ],
        'filter_options' => [
            'checkout' => 'Checkout Date',
            'checkin' => 'Checkin Date',
            'booking' => 'Booking Date',
        ],
        'default_filter' => 'checkout',
        'default_from_months' => 6,
    ],

    'daily_report' => [
        'title' => 'Daily Report',
        'parent_label' => 'Analytics',
    ],

    'trend_analysis' => [
        'title' => 'Trend Analysis',
        'parent_label' => 'Analytics',
        'default_view' => 'monthly',
        'views' => [
            'monthly' => 'Monthly',
            'weekly' => 'Weekly',
            'daily' => 'Daily',
        ],
        'filter_options' => [
            'checkout' => 'Checkout Date',
            'checkin' => 'Checkin Date',
            'booking' => 'Booking Date',
        ],
        'default_filter' => 'checkout',
        'default_from_months' => 6,
        'lead_time_buckets' => ['0', '1', '2-10', '10-30', '30-60', '60-90', '90+'],
        'length_of_stay_labels' => ['0', '1', '2', '3', '4', '5', '6', '7'],
        'occupancy_guest_labels' => ['1', '2', '3', '4', '5'],
        'dow_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'historical_days' => 10,
        'future_days' => 30,
    ],

    'dynamic_pricing' => [
        'title' => 'Dynamic Pricing',
        'parent_label' => 'Analytics',
        'past_days' => 10,
        'default_future_days' => 30,
        'override_months' => 13,
    ],

    'pickup_report' => [
        'title' => 'Pick Up Report',
        'parent_label' => 'Analytics',
        'default_mode' => 'by_date',
        'modes' => [
            'by_date' => 'Pick Up By Date',
            'by_range' => 'Pick Up By Date Range',
        ],
        'default_report_type' => 'date_wise',
        'report_types' => [
            'date_wise' => 'Date Wise',
            'week_wise' => 'Week Wise',
            'month_wise' => 'Month Wise',
        ],
        'forward_days' => 30,
    ],
];
