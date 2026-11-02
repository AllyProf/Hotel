<?php

return [
    'title' => 'Stores',

    'main_tabs' => [
        'purchase-orders' => 'Purchase Orders',
        'invoices' => 'Invoices',
        'indent' => 'Indent',
        'stock' => 'Stock',
        'consumption' => 'Consumption',
        'reports' => 'Reports',
        'settings' => 'Settings',
    ],

    'settings_tabs' => [
        'master-items' => 'Master Items',
        'item-category' => 'Department',
        'tax-groups' => 'Tax groups',
        'units-packaging' => 'Unit & Packaging',
        'department' => 'Issue to',
        'vendor' => 'Vendor',
    ],

    'usage_types' => [
        'sale' => 'For sale (profit)',
        'internal' => 'Internal use only',
    ],

    /** Seeded once per hotel; manage names under Hotel Settings → Store categories. */
    'default_item_categories' => [
        'Front Office',
        'Main Kitchen',
        'Banquet Service',
        'Computer Accessories',
        'Fuel/Charcoal',
        'F & B',
        'House keeping',
    ],

    /** Seeded once per hotel when no tax groups exist. */
    'default_tax_groups' => [
        ['name' => 'VAT 18%', 'rate_percent' => 18],
        ['name' => 'VAT 6%', 'rate_percent' => 6],
        ['name' => 'GST5', 'rate_percent' => 5],
        ['name' => 'GST12', 'rate_percent' => 12],
        ['name' => 'GST18', 'rate_percent' => 18],
        ['name' => 'GST28', 'rate_percent' => 28],
        ['name' => 'Exempt', 'rate_percent' => 0],
    ],

    /** Receiving / stock units (like Bar → Register items). */
    'default_packages' => [
        ['name' => 'Bottle', 'abbreviation' => 'BTL'],
        ['name' => 'Carton', 'abbreviation' => 'CTN'],
        ['name' => 'Litre', 'abbreviation' => 'LTR'],
        ['name' => 'Kilogram', 'abbreviation' => 'KG'],
        ['name' => 'Piece', 'abbreviation' => 'PCS'],
        ['name' => 'Box', 'abbreviation' => 'BOX'],
        ['name' => 'Bag', 'abbreviation' => 'BAG'],
    ],

    /** Permission keys for PO row actions (Hotel → Roles). */
    'po_permission_keys' => [
        'create' => 'stores.po.create',
        'approve' => 'stores.po.approve',
        'invoice' => 'stores.po.invoice',
        'edit' => 'stores.po.edit',
        'delete' => 'stores.po.delete',
    ],

    'indent_permission_keys' => [
        'create' => 'stores.indent.create',
        'approve' => 'stores.indent.approve',
        'edit' => 'stores.indent.edit',
        'delete' => 'stores.indent.delete',
    ],

    'indent_number_start' => 10400,

    'indent_status_filters' => [
        'all' => 'All',
        'applied' => 'Applied',
        'issued' => 'Awaiting department',
        'received' => 'Received',
    ],

    'indent_receive_permission_keys' => [
        'receive' => 'stores.indent.receive',
    ],

    /** When false, only departments receive issued indents (e.g. Maintenance & HK → Request from store). */
    'indent_store_can_receive' => false,

    'consumption_permission_keys' => [
        'create' => 'stores.consumption.create',
    ],

    'consumption_number_start' => 10500,

    'report_types' => [
        'indent' => 'Indent',
        'consumption' => 'Consumption',
    ],

    'report_sort_options' => [
        'department' => 'Department Wise',
        'item' => 'Item Wise',
        'category' => 'Category Wise',
        'sub_category' => 'Sub-category Wise',
    ],

    'po_status_filters' => [
        'all' => 'All',
        'applied' => 'Applied',
        'open' => 'Open',
        'paid' => 'Paid',
    ],

    'invoice_number_start' => 700,

    /** Shown next to PO / invoice line quantities (e.g. 10 Pkts). */
    'po_line_qty_unit' => 'Pkts',

    'goods_receive_statuses' => [
        'full' => 'All received',
        'partial' => 'Partial (few received)',
        'missing' => 'Missing',
    ],

    'ui' => [
        'category' => 'Department',
        'department_store_label' => 'Department',
        'service_offered' => 'Service offered',
        'add_store_department' => 'Add Department',
        'edit_store_department' => 'Edit Department',
        'store_department_search_placeholder' => 'Search department…',
        'store_department_name' => 'Department name',
        'store_department_service_hint' => 'Categories and units come from this service (PMS → Services).',
        'item_service_category' => 'Category',
        'item_price' => 'Buying price',
        'sub_category' => 'Sub Category',
        'all' => 'All',
        'add_item' => 'Add Item',
        'search_placeholder' => 'Search Item…',
        'total_value' => 'Total Value',
        'configure_user' => 'Configure User',
        'coming_soon' => 'This section will be available in a later update.',
        'add_vendor' => 'Add Vendor',
        'edit_vendor' => 'Edit Vendor',
        'vendor_search_placeholder' => 'Search vendor…',
        'submit' => 'Submit',
        'add_department' => 'Add Department',
        'edit_department' => 'Edit Department',
        'department_search_placeholder' => 'Search Department…',
        'category_name_placeholder' => 'Category name',
        'sub_category_name_placeholder' => 'Sub category name',
        'add_category' => 'Add',
        'add_sub_category' => 'Sub Category',
        'category_search_placeholder' => 'Search category…',
        'edit_category' => 'Edit Category',
    ],

    /** Phone codes for vendor form (no external CDN — avoids blocking page load). */
    'vendor_phone_codes' => [
        ['code' => '+255', 'flag' => '🇹🇿'],
        ['code' => '+254', 'flag' => '🇰🇪'],
        ['code' => '+256', 'flag' => '🇺🇬'],
        ['code' => '+250', 'flag' => '🇷🇼'],
        ['code' => '+257', 'flag' => '🇧🇮'],
        ['code' => '+243', 'flag' => '🇨🇩'],
        ['code' => '+251', 'flag' => '🇪🇹'],
        ['code' => '+27', 'flag' => '🇿🇦'],
        ['code' => '+971', 'flag' => '🇦🇪'],
        ['code' => '+1', 'flag' => '🇺🇸'],
        ['code' => '+44', 'flag' => '🇬🇧'],
        ['code' => '+91', 'flag' => '🇮🇳'],
        ['code' => '+86', 'flag' => '🇨🇳'],
    ],

    'vendor_columns' => [
        ['key' => 'name', 'label' => 'Vendor Name'],
        ['key' => 'contact_person', 'label' => 'Contact Person'],
        ['key' => 'gst_num', 'label' => 'TIN Number'],
        ['key' => 'phone', 'label' => 'Phone'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'state', 'label' => 'Region'],
        ['key' => 'account_no', 'label' => 'Account Number'],
        ['key' => 'bank_name', 'label' => 'Bank Name'],
    ],
];
