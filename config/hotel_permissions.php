<?php

return [
    'groups' => [
        'General' => [
            'dashboard.view' => 'View dashboard',
            'settings.view' => 'View hotel settings',
            'settings.manage' => 'Manage hotel settings',
        ],
        'Branches' => [
            'branches.view' => 'View branches',
            'branches.manage' => 'Create & edit branches',
        ],
        'Channel Manager' => [
            'channel_manager.view' => 'View channel manager pages',
            'channel_manager.manage' => 'Update rooms, rates & mapping',
        ],
        'PMS' => [
            'pms.view' => 'View reservations & guests',
            'pms.manage' => 'Manage reservations & guests',
        ],
        'Staff & Roles' => [
            'staff.view' => 'View staff list',
            'staff.manage' => 'Create & manage staff',
            'roles.manage' => 'Manage roles & permissions',
        ],
        'Booking Engine' => [
            'booking_engine.view' => 'View booking engine settings',
            'booking_engine.manage' => 'Manage direct booking',
        ],
        'Maintenance & HK' => [
            'hk.store_request.create' => 'Request items from store',
            'hk.store_request.edit' => 'Edit store requests',
            'hk.store_request.delete' => 'Delete store requests',
            'hk.store_request.receive' => 'Receive issued stock into department',
            'hk.dept_stock.view' => 'View department stock',
            'hk.consumption.view' => 'View consumption history',
            'hk.consumption.create' => 'Record consumption',
        ],
        'Department stores' => [
            'dept_stores.access_all_departments' => 'View all departments (not only assigned)',
            'dept_stores.store_request.create' => 'Request items from store',
            'dept_stores.store_request.edit' => 'Edit store requests',
            'dept_stores.store_request.delete' => 'Delete store requests',
            'dept_stores.store_request.receive' => 'Receive issued stock into department',
            'dept_stores.dept_stock.view' => 'View department stock',
            'dept_stores.consumption.view' => 'View consumption history',
            'dept_stores.consumption.create' => 'Record consumption',
        ],
        'Stores' => [
            'stores.view' => 'View stores & purchase orders',
            'stores.po.create' => 'Create purchase orders',
            'stores.po.approve' => 'Approve purchase orders',
            'stores.po.invoice' => 'Invoice purchase orders',
            'stores.po.edit' => 'Edit purchase orders',
            'stores.po.delete' => 'Delete purchase orders',
            'stores.indent.create' => 'Create indents',
            'stores.indent.approve' => 'Approve indents (issue stock)',
            'stores.indent.edit' => 'Edit indents',
            'stores.indent.delete' => 'Delete indents',
            'stores.indent.receive' => 'Receive indents into department stock',
            'stores.consumption.create' => 'Record consumption',
        ],
    ],
];
