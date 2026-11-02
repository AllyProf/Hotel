<?php

return [
    'room_view' => [
        'stats' => [
            'guests' => 'Guests',
            'occupied' => 'Occupied',
            'available' => 'Available',
            'dirty' => 'Dirty',
            'cleaned' => 'Available',
            'complimentary' => 'Complimentary',
            'maintenance' => 'Maintenance',
        ],
        'legend' => [
            ['state' => 'assigned', 'label' => 'Assigned'],
            ['state' => 'checked-in', 'label' => 'Checked in'],
            ['state' => 'checking-out', 'label' => 'Checking out'],
            ['state' => 'dirty', 'label' => 'Dirty'],
            ['state' => 'cleaned', 'label' => 'Available'],
            ['state' => 'maintenance', 'label' => 'Maintenance'],
            ['state' => 'complimentary', 'label' => 'Complimentary'],
        ],
        'views' => [
            [
                'key' => 'stay',
                'label' => 'Stay View',
                'route' => 'hotel.stay-view',
                'date_param' => 'start',
            ],
            [
                'key' => 'rooms',
                'label' => 'Rooms View',
                'route' => 'hotel.room-view',
                'date_param' => 'date',
            ],
        ],
        'floor_view_label' => 'Floor View',
        'card_icons' => [
            'available' => 'fa-star-o',
            'assigned' => 'fa-user',
            'checked-in' => 'fa-check',
            'checking-out' => 'fa-sign-out',
            'checked-out' => 'fa-check-circle',
            'dirty' => 'fa-bed',
            'cleaned' => 'fa-check-circle',
            'maintenance' => 'fa-wrench',
            'complimentary' => 'fa-gift',
        ],
        'status_labels' => [
            'available' => 'Available',
            'assigned' => 'Assigned',
            'checked-in' => 'Checked in',
            'checking-out' => 'Checking out',
            'checked-out' => 'Checked out',
            'dirty' => 'Dirty',
            'cleaned' => 'Available',
            'maintenance' => 'Maintenance',
            'complimentary' => 'Complimentary',
        ],
    ],

    'complimentary' => [
        'reasons' => [
            'staff' => 'Staff Stay',
            'vip' => 'VIP / Guest of Owner',
            'service_recovery' => 'Service Recovery',
            'marketing' => 'Marketing / Influencer',
            'owner' => 'Owner / Family',
            'other' => 'Other',
        ],
    ],

    'reservation_data' => [
        'title' => 'Reservation Data',
        'default_date_range_days' => 7,
        'default_per_page' => 20,
        'per_page_options' => [20, 50, 100],
        'filter_options' => [
            'booking_date' => 'Booking',
            'checkin' => 'CheckIn',
            'checkout' => 'CheckOut',
            'cancelled' => 'Cancelled',
        ],
        'header_actions' => [
            ['key' => 'multi_booking', 'label' => 'Multi Booking', 'route' => 'hotel.reservations.multi-booking'],
            ['key' => 'groups', 'label' => 'Groups', 'route' => 'hotel.reservations.group-booking'],
            ['key' => 'company_booking', 'label' => 'Company Booking', 'route' => 'hotel.reservations.company-booking'],
            ['key' => 'complimentary', 'label' => 'Complimentary', 'route' => 'hotel.reservations.complimentary'],
            ['key' => 'create', 'label' => 'Create Reservation', 'route' => 'hotel.reservations.create'],
        ],
        'columns' => [
            ['key' => 'booking_id', 'label' => 'Booking ID'],
            ['key' => 'customer_name', 'label' => 'Customer Name'],
            ['key' => 'booked_on', 'label' => 'Booked On'],
            ['key' => 'checkin', 'label' => 'CheckIn'],
            ['key' => 'checkout', 'label' => 'CheckOut'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'guests', 'label' => 'Guests'],
            ['key' => 'rooms', 'label' => 'Rooms'],
            ['key' => 'nights', 'label' => 'Nights'],
            ['key' => 'amount', 'label' => 'Amount'],
            ['key' => 'payment_mode', 'label' => 'Payment Mode'],
            ['key' => 'payment_link', 'label' => 'Payment Link'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'meal_plan', 'label' => 'MealPlan'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'action', 'label' => 'Action'],
        ],
    ],

    'check_out' => [
        'title' => 'Front Desk Check-out',
        'tabs' => [
            'departures' => 'Departures',
            'in_house' => 'In House',
            'checked_out' => 'Checked Out',
        ],
        'columns' => [
            ['key' => 'booking_id', 'label' => 'Booking ID'],
            ['key' => 'guest', 'label' => 'Guest'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'room_type', 'label' => 'Room Type'],
            ['key' => 'room_no', 'label' => 'Room No'],
            ['key' => 'checkin', 'label' => 'Check-in'],
            ['key' => 'checkout', 'label' => 'Check-out'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'late', 'label' => 'Late'],
            ['key' => 'action', 'label' => 'Action'],
        ],
    ],

    'create_reservation' => [
        'genders' => ['Male', 'Female', 'Other'],
    ],

    'check_in' => [
        'title' => 'Front Desk Check-in',
        'tabs' => [
            'arrivals' => 'Arrivals',
        ],
        'columns' => [
            ['key' => 'booking_id', 'label' => 'Booking ID'],
            ['key' => 'guest', 'label' => 'Guest'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'room_type', 'label' => 'Room Type'],
            ['key' => 'checkin', 'label' => 'Check-in'],
            ['key' => 'checkout', 'label' => 'Check-out'],
            ['key' => 'guests', 'label' => 'Guests'],
            ['key' => 'room_no', 'label' => 'Room No'],
            ['key' => 'pms_status', 'label' => 'Status'],
            ['key' => 'action', 'label' => 'Action'],
        ],
    ],

    'guests' => [
        'title' => 'Guests',
        'default_per_page' => 20,
        'per_page_options' => [20, 50, 100],
        'columns' => [
            ['key' => 'photo_id', 'label' => 'Photo ID'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'total_value', 'label' => 'Total Value'],
            ['key' => 'previous_stays', 'label' => 'Previous Stays'],
            ['key' => 'actions', 'label' => ''],
        ],
    ],

    'companies' => [
        'title' => 'Companies',
        'default_per_page' => 20,
        'per_page_options' => [20, 50, 100],
        'columns' => [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'gst_vat', 'label' => 'GST/ VAT'],
            ['key' => 'total_billed', 'label' => 'Total Billed'],
            ['key' => 'total_outstanding', 'label' => 'Total Outstanding'],
            ['key' => 'invoice', 'label' => 'Invoice'],
            ['key' => 'payments', 'label' => 'Payments'],
            ['key' => 'actions', 'label' => 'Action'],
        ],
    ],

    'services' => [
        'title' => 'Services',
    ],

    'laundry' => [
        'title' => 'Laundry',
        'statuses' => [
            'in_progress' => 'Progressing',
            'delivered' => 'Done',
            'received' => 'Progressing',
            'ready' => 'Progressing',
            'cancelled' => 'Cancelled',
        ],
    ],

    'swimming' => [
        'title' => 'Swimming Pool',
        'statuses' => [
            'scheduled' => 'Scheduled',
            'in_pool' => 'In pool',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
    ],

    'conference' => [
        'title' => 'Conference & Events',
        'statuses' => [
            'inquiry' => 'Inquiry',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
    ],

    'expenses' => [
        'title' => 'Expenses',
        'columns' => [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'entry_type', 'label' => 'Entry'],
            ['key' => 'payment_type', 'label' => 'Type'],
            ['key' => 'paid_type', 'label' => 'Paid Type'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'amount', 'label' => 'Amount'],
            ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'invoice_no', 'label' => 'Invoice No.'],
            ['key' => 'comments', 'label' => 'Comments'],
        ],
    ],

    'payment_gateway' => [
        'title' => 'Payment Status',
        'default_date_range_days' => 7,
        'default_per_page' => 20,
        'per_page_options' => [20, 50, 100],
        'header_actions' => [
            ['key' => 'auto_links', 'label' => 'Send Payment Links Automatically', 'modal' => 'pgAutoLinksModal'],
            ['key' => 'bank_details', 'label' => 'Bank Details', 'modal' => 'pgBankDetailsModal'],
            ['key' => 'send_link', 'label' => 'Send Payment Link', 'modal' => 'pgSendLinkModal'],
            ['key' => 'settlement_report', 'label' => 'Settlement Report', 'route' => 'hotel.payment-gateway.settlement'],
            ['key' => 'invoices', 'label' => 'Invoices', 'route' => 'hotel.payment-gateway.invoices'],
            ['key' => 'unsettled', 'label' => 'Unsettled Payments', 'route' => 'hotel.payment-gateway.unsettled'],
        ],
        'columns' => [
            ['key' => 'booking_id', 'label' => 'Booking ID'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'transaction_id', 'label' => 'Transaction ID'],
            ['key' => 'transaction_date', 'label' => 'Transaction Date'],
            ['key' => 'checkin', 'label' => 'Checkin'],
            ['key' => 'checkout', 'label' => 'Checkout'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'amount', 'label' => 'Amount'],
            ['key' => 'pg_charges', 'label' => 'PG Charges'],
            ['key' => 'tax', 'label' => 'Tax'],
            ['key' => 'net_amount', 'label' => 'Net Amount'],
            ['key' => 'confirmation_id', 'label' => 'Confirmation ID'],
            ['key' => 'payment_link', 'label' => 'Payment Link'],
            ['key' => 'settlement_date', 'label' => 'Settlement Date'],
        ],
        'settlement' => [
            'title' => 'Settlement Report',
            'columns' => [
                ['key' => 'date_of_settlement', 'label' => 'Date Of Settlement'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'no_of_transactions', 'label' => 'No Of Transactions'],
                ['key' => 'amount_transferred', 'label' => 'Amount Transferred'],
            ],
        ],
        'invoices' => [
            'title' => 'Invoice Report',
            'columns' => [
                ['key' => 'invoice_date', 'label' => 'Invoice Date'],
                ['key' => 'start_date', 'label' => 'Start Date'],
                ['key' => 'end_date', 'label' => 'End Date'],
                ['key' => 'invoice_num', 'label' => 'Invoice Num'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'invoice', 'label' => 'Invoice'],
            ],
        ],
        'unsettled' => [
            'title' => 'Unsettled Payments',
        ],
    ],

    'logs' => [
        'title' => 'Logs',
        'default_date_range_days' => 1,
        'default_per_page' => 20,
        'per_page_options' => [20, 50, 100],
        'columns' => [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'action_type', 'label' => 'Action Type'],
            ['key' => 'booking_id', 'label' => 'Booking Id'],
            ['key' => 'guest_name', 'label' => 'Guest Name'],
            ['key' => 'folio_no', 'label' => 'Folio No'],
            ['key' => 'room_no', 'label' => 'Room No'],
            ['key' => 'details', 'label' => 'Details'],
            ['key' => 'changed_by', 'label' => 'Changed By'],
        ],
    ],

    'reports' => [
        'title' => 'Reports',
        'default_report' => 'departure_report',
        'monthly_pl' => [
            'revenue_lines' => [
                ['key' => 'rooms', 'label' => 'Rooms'],
            ],
            'expense_fallback_label' => 'Other Expenses',
            'purchase_fallback_label' => 'Other Purchases',
        ],
        'categories' => [
            [
                'label' => 'Front Office',
                'reports' => [
                    ['key' => 'arrival_report', 'label' => 'Arrival Report', 'implemented' => true],
                    ['key' => 'departure_report', 'label' => 'Departure Report', 'implemented' => true],
                    ['key' => 'room_status_report', 'label' => 'Room Status Report', 'implemented' => true],
                    ['key' => 'night_audit_report', 'label' => 'Night Audit Report', 'implemented' => false],
                    ['key' => 'police_enquiry_report', 'label' => 'Police Enquiry Report', 'implemented' => true],
                    ['key' => 'complimentary_rooms', 'label' => 'Complimentary Rooms', 'implemented' => false],
                    ['key' => 'out_of_order_room_report', 'label' => 'Out of Order Room Report', 'implemented' => true],
                    ['key' => 'no_show_report', 'label' => 'No Show Report', 'implemented' => true],
                    ['key' => 'reviews_tracking_report', 'label' => 'Reviews Tracking Report', 'implemented' => true],
                    ['key' => 'iot_occupancy_issues_report', 'label' => 'IOT Occupancy Issues Report', 'implemented' => false],
                    ['key' => 'maintenance_report', 'label' => 'Maintenance Report', 'implemented' => true],
                ],
            ],
            [
                'label' => 'PMS Services',
                'reports' => [
                    ['key' => 'laundry_report', 'label' => 'Laundry Report', 'implemented' => true],
                    ['key' => 'swimming_report', 'label' => 'Swimming Pool Report', 'implemented' => true],
                    ['key' => 'conference_report', 'label' => 'Conference & Events Report', 'implemented' => true],
                ],
            ],
            [
                'label' => 'Accounting',
                'reports' => [
                    ['key' => 'room_wise_revenue_report', 'label' => 'Room Wise Revenue Report', 'implemented' => true],
                    ['key' => 'date_wise_revenue_report', 'label' => 'Date Wise Revenue Report', 'implemented' => true],
                    ['key' => 'checkout_accounting_report', 'label' => 'Checkout based Accounting Report', 'implemented' => true],
                    ['key' => 'item_consumption_report', 'label' => 'Item Consumption Report', 'implemented' => true],
                    ['key' => 'payments_report', 'label' => 'Payments Report', 'implemented' => true],
                    ['key' => 'pos_report', 'label' => 'POS Report', 'implemented' => true],
                    ['key' => 'payment_void_report', 'label' => 'Payment Void Report', 'implemented' => false],
                    ['key' => 'expense_report', 'label' => 'Expense Report', 'implemented' => true],
                    ['key' => 'city_ledger_payment_report', 'label' => 'City Ledger Payment Report', 'implemented' => true],
                    ['key' => 'cancelled_reports', 'label' => 'Cancelled Reports', 'implemented' => true],
                    ['key' => 'discount_reports', 'label' => 'Discount Reports', 'implemented' => true],
                ],
            ],
            [
                'label' => 'Management',
                'reports' => [
                    ['key' => 'hotel_sales_report', 'label' => 'Hotel Sales Report', 'implemented' => true],
                    ['key' => 'company_performance', 'label' => 'Company Performance', 'implemented' => true],
                    ['key' => 'room_category_sales_report', 'label' => 'Room Category Sales Report', 'implemented' => true],
                    ['key' => 'room_number_sales_report', 'label' => 'Room Number Sales Report', 'implemented' => true],
                    ['key' => 'monthly_performance_report', 'label' => 'Monthly Performance Report', 'implemented' => true],
                    ['key' => 'room_wise_forecast_report', 'label' => 'Room Wise Forecast Report', 'implemented' => true],
                    ['key' => 'daily_forecast_report', 'label' => 'Daily Forecast Report', 'implemented' => true],
                    ['key' => 'monthly_pl', 'label' => 'Monthly P&L', 'implemented' => true],
                    ['key' => 'contribution_report', 'label' => 'Contribution Report', 'implemented' => true],
                    ['key' => 'hotel_weekly_meeting_report', 'label' => 'Hotel Weekly Meeting Report', 'implemented' => false],
                    ['key' => 'manager_report', 'label' => 'Manager Report', 'implemented' => false],
                ],
            ],
            [
                'label' => 'Audit',
                'reports' => [
                    ['key' => 'same_day_checkouts_report', 'label' => 'Same Day Checkouts Report', 'implemented' => true],
                    ['key' => 'guest_phone_difference', 'label' => 'Guest Phone Number Difference', 'implemented' => true],
                    ['key' => 'late_checkout_report', 'label' => 'Late Checkout Report', 'implemented' => true],
                    ['key' => 'early_checkin_report', 'label' => 'Early Checkin Report', 'implemented' => true],
                    ['key' => 'room_upgrade_report', 'label' => 'Room Upgrade Report', 'implemented' => true],
                ],
            ],
        ],
    ],

    'live_bookings' => [
        'poll_interval_seconds' => 10,
        'poll_reload_delay_ms' => 1200,
    ],

    /** Fill Stay View with sample guests when no reservations (local only). */
    'stay_view' => [
        'demo_on_empty' => env('STAY_VIEW_DEMO', true),
        'allow_sample_bookings' => env('STAY_VIEW_SAMPLE', true),
    ],
];
