<?php

return [
    'title' => 'Accounts',

    'tabs' => [
        'receivables' => 'Receivables',
        'payables' => 'Payables',
        'bar_budgets' => 'Purchase budgets',
        'taxes' => 'Taxes',
        'reconciliation' => 'Reconciliation',
    ],

    'receivables' => [
        'columns' => [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'contact_person', 'label' => 'Contact Person'],
            ['key' => 'gst_vat', 'label' => 'GST No.'],
            ['key' => 'total_billed', 'label' => 'Total Billed'],
            ['key' => 'collected', 'label' => 'Collected'],
            ['key' => 'total_outstanding', 'label' => 'Outstanding (AR)'],
            ['key' => 'bookings', 'label' => 'Company bookings'],
        ],
    ],

    'payables' => [
        'columns' => [
            ['key' => 'po_number', 'label' => 'PO No.'],
            ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'pre_tax', 'label' => 'Pre tax'],
            ['key' => 'tax', 'label' => 'Tax'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Actions'],
        ],
        'vendor_columns' => [
            ['key' => 'name', 'label' => 'Vendor Name'],
            ['key' => 'contact_person', 'label' => 'Contact Person'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'state', 'label' => 'Region'],
        ],
    ],

    'taxes' => [
        'types' => [
            'sales' => 'Sales',
            'purchases' => 'Purchases',
        ],
    ],

    'reconciliation' => [
        'columns' => [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'pos_name', 'label' => 'POS Name'],
            ['key' => 'invoice_no', 'label' => 'Invoice no.'],
            ['key' => 'party', 'label' => 'Vendor / Company'],
            ['key' => 'comments', 'label' => 'Comments'],
            ['key' => 'image', 'label' => 'Image'],
            ['key' => 'user', 'label' => 'User'],
            ['key' => 'paid_in', 'label' => 'Paid In'],
            ['key' => 'paid_out', 'label' => 'Paid Out'],
        ],
        'default_pos_name' => 'Front Desk',
    ],
];
