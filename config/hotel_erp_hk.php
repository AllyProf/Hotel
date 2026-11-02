<?php

return [
    'title' => 'Maintenance & HK',

    'tabs' => [
        'housekeeping' => 'Housekeeping',
        'maintenance' => 'Maintenance',
        'lost_found' => 'Lost & Found',
        'rooms' => 'Room board',
        'purchase-lists' => 'Purchase lists',
        'received' => 'Received',
        'stock-receive' => 'Receive stock',
        'store-requests' => 'Request from store',
        'dept-stock' => 'Stock',
        'consumption' => 'Consumption',
    ],

    'dept_stock_permission_keys' => [
        'view' => 'hk.dept_stock.view',
    ],

    'consumption_permission_keys' => [
        'view' => 'hk.consumption.view',
        'create' => 'hk.consumption.create',
    ],

    'store_request_permission_keys' => [
        'create' => 'hk.store_request.create',
        'edit' => 'hk.store_request.edit',
        'delete' => 'hk.store_request.delete',
        'receive' => 'hk.store_request.receive',
    ],

    'housekeeping' => [
        'task_types' => [
            'checkout_clean' => 'Checkout clean',
            'stayover' => 'Stayover service',
            'deep_clean' => 'Deep clean',
            'turndown' => 'Turndown',
        ],
        'statuses' => [
            'pending' => 'Pending',
            'in_progress' => 'In progress',
            'completed' => 'Cleaned',
        ],
        'priorities' => [
            'normal' => 'Normal',
            'high' => 'High',
            'rush' => 'Rush',
        ],
    ],

    'maintenance' => [
        'categories' => [
            'general' => 'General',
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'hvac' => 'HVAC',
            'furniture' => 'Furniture',
            'it' => 'IT / TV',
        ],
        'statuses' => [
            'open' => 'Open',
            'in_progress' => 'In progress',
            'waiting_parts' => 'Waiting parts',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'priorities' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'urgent' => 'Urgent',
        ],
    ],

    'lost_found' => [
        // Housekeeping logs items found on property; guest "lost" reports are handled at front desk.
        'entry_types' => [
            'found' => 'Found item',
        ],
        'categories' => [
            'electronics' => 'Electronics',
            'clothing' => 'Clothing',
            'documents' => 'Documents / ID',
            'jewelry' => 'Jewelry',
            'bags' => 'Bags & luggage',
            'keys' => 'Keys',
            'other' => 'Other',
        ],
        'statuses' => [
            'stored' => 'In storage',
            'claimed' => 'Claimed / returned',
            'disposed' => 'Disposed',
        ],
    ],

    'room_status_labels' => [
        'available' => 'Available',
        'dirty' => 'Dirty',
        'cleaned' => 'Available',
        'maintenance' => 'Maintenance',
        'inactive' => 'Inactive',
    ],
];
