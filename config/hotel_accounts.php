<?php

return [
    'title' => 'Accounts',

    'tabs' => [
        'receivables' => 'Receivables',
        'payables' => 'payables',
        'taxes' => 'Taxes',
        'reconciliation' => 'Reconciliation',
    ],

    'receivables' => [
        'columns' => [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'contact_person', 'label' => 'Contact Person'],
            ['key' => 'gst_vat', 'label' => 'GST No.'],
            ['key' => 'total_billed', 'label' => 'Total Billed'],
            ['key' => 'total_outstanding', 'label' => 'Outstanding'],
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
        ],
        'vendor_columns' => [
            ['key' => 'name', 'label' => 'Vendor Name'],
            ['key' => 'contact_person', 'label' => 'Contact Person'],
            ['key' => 'gst_num', 'label' => 'GST Num'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'state', 'label' => 'State'],
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
