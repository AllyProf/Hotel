<?php

return [
    'title' => 'Department Stores',

    'tabs' => [
        'store-requests' => 'Request from store',
        'dept-stock' => 'Department stock',
        'consumption' => 'Consumption',
    ],

    'store_request_permission_keys' => [
        'create' => 'dept_stores.store_request.create',
        'edit' => 'dept_stores.store_request.edit',
        'delete' => 'dept_stores.store_request.delete',
        'receive' => 'dept_stores.store_request.receive',
    ],

    'dept_stock_permission_keys' => [
        'view' => 'dept_stores.dept_stock.view',
    ],

    'consumption_permission_keys' => [
        'view' => 'dept_stores.consumption.view',
        'create' => 'dept_stores.consumption.create',
    ],

    /** See every department; otherwise scope to the user’s assigned department when set. */
    'access_all_departments_permission' => 'dept_stores.access_all_departments',
];
