<?php

return [
    'title' => 'POS',

    'default_tax_percent' => 18,

    /** When hotel / rate plan currency is unset (3-letter code, not USD). */
    'fallback_currency' => env('HOTEL_POS_FALLBACK_CURRENCY', 'TZS'),

    /** When true, New Order requires a waiter from the team list. */
    'require_waiter_on_order' => (bool) env('HOTEL_POS_REQUIRE_WAITER', false),

    /** Screen labels (change wording here, not in views). */
    'ui' => [
        'pos_name' => 'Department',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'report_type' => 'Report',
        'run_report' => 'Report',
        'current_orders' => 'Current Orders',
        'billed_orders' => 'Billed Orders',
        'create_order' => 'Create',
        'complimentary_order' => 'Complimentary',
        'search_placeholder' => 'Search…',
        'new_order' => 'New Order',
        'complimentary_order_title' => 'Complimentary order',
        'add_new_service' => 'Add New Service',
        'guest_type' => 'Guest',
        'guest_walk_in' => 'Walk-in',
        'guest_in_house' => 'In-house guest',
        'guest_walk_in_name' => 'Name',
        'guest_walk_in_name_hint' => 'Optional',
        'guest_walk_in_name_placeholder' => 'Walk-in guest name',
        'guest_select_room' => 'Guest',
        'guest_select_room_hint' => 'Search checked-in',
        'guest_select_room_placeholder' => 'Search room or guest name…',
        'details' => 'Details',
        'details_placeholder' => 'Table 10, extra notes…',
        'waiter' => 'Waiter',
        'select_waiter' => 'Select waiter',
        'waiter_optional_hint' => 'Optional',
        'table_waiter' => 'Waiter',
        'services_category' => 'Category',
        'select_category' => 'Select category',
        'no_categories_hint' => 'Add bar categories and in-stock items with sell prices on the bar register.',
        'service' => 'Item',
        'service_search_placeholder' => 'Search item in bar stock…',
        'qty' => 'Qty',
        'amount' => 'Amount',
        'tax' => 'Tax',
        'total' => 'Total',
        'cancel' => 'Cancel',
        'submit' => 'Submit',
        'table_order_no' => 'Order #',
        'table_items' => 'Items',
        'table_service_name' => 'Item',
        'table_rate' => 'Rate',
        'table_qty' => 'Qty',
        'table_amt' => 'Amt',
        'table_tax' => 'Tax',
        'table_total' => 'Total',
        'table_grand_total' => 'Total :',
        'action_view' => 'View',
        'action_edit' => 'Edit',
        'action_delete' => 'Delete',
        'action_bill' => 'Bill',
        'action_payment' => 'Payment',
        'status_paid' => 'Paid',
        'status_unpaid' => 'Unpaid',
        'pay_before_bill_hint' => 'Pay the full amount before billing.',
        'bill_moves_to_billed' => 'This order will move to Billed Orders.',
        'edit_order_title' => 'Edit order',
        'update_order' => 'Update',
        'tax_id_label' => 'GST',
        'view_service' => 'Service',
        'view_qty' => 'Qty',
        'view_print' => 'Print',
        'view_close' => 'Close',
        'view_no_items' => 'No items on this order.',
        'view_order_no' => 'Order No:',
        'view_details' => 'Details:',
        'view_date' => 'Date:',
        'view_invoice_no' => 'Invoice No:',
        'invoice_title_prefix' => 'Invoice',
        'view_bill' => 'View Bill',
        'view_type_of_payment' => 'Type of Payment',
        'view_payment_comments' => 'Comments',
        'view_payment_amount' => 'Amount',
        'select_printer' => 'Select printer',
    ],

    /**
     * Decimal places when showing amounts (not hardcoded .00 everywhere).
     * East African shillings are usually whole numbers; USD/EUR use 2.
     */
    'currency_decimals' => [
        'default' => 2,
        'TZS' => 0,
        'UGX' => 0,
        'RWF' => 0,
        'BIF' => 0,
        'KES' => 0,
    ],

    /** Optional prefix when showing money (falls back to the hotel currency code). */
    'currency_symbols' => [
        'TZS' => 'TSh ',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'KES' => 'KSh ',
    ],

    /**
     * Template keys that default to “Show in POS” when added from PMS → Services.
     * New services from these templates get a matching HR department linked when POS is configured.
     */
    'outlet_template_keys' => [
        'restaurant',
        'bar',
        'room_service',
        'spa',
        'gym',
    ],

    /** Short code embedded in order / invoice numbers. */
    'outlet_codes' => [
        'restaurant' => 'REST',
        'bar' => 'BAR',
        'room_service' => 'RS',
        'spa' => 'SPA',
        'gym' => 'GYM',
        'laundry' => 'LAU',
        'conference' => 'CONF',
    ],

    'statuses' => [
        'open' => 'Current',
        'billed' => 'Billed',
        'cancelled' => 'Cancelled',
    ],

    'report_types' => [
        'item_consumption' => 'Item Consumption',
        'payments' => 'Payments',
    ],

    'payment_types' => [
        'cash' => 'Cash',
        'credit_card' => 'Credit Card',
        'wallet' => 'Wallet',
        'bill_to_company' => 'Bill to Company',
        'room_bill' => 'Add to Room Bill',
    ],

    /** Summary cards on the POS dashboard (labels kept plain for staff). */
    'dashboard_kpis' => [
        [
            'key' => 'sales',
            'label' => 'Total sales',
            'subtitle' => 'period',
            'format' => 'money',
            'alert' => 'info',
        ],
        [
            'key' => 'mtd_sales',
            'label' => 'Sales this month',
            'subtitle' => 'mtd',
            'format' => 'money',
            'alert' => 'success',
        ],
        [
            'key' => 'order_count',
            'label' => 'Orders billed',
            'subtitle' => null,
            'format' => 'count',
            'alert' => 'warning',
        ],
        [
            'key' => 'average',
            'label' => 'Average per order',
            'subtitle' => null,
            'format' => 'money',
            'alert' => 'primary',
        ],
    ],
];
