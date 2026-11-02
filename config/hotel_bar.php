<?php

return [
    /*
    | Optional fallback when hotel / rate plan currency is unset (3-letter code).
    | Prefer Settings → Bar → Budget currency, or Prices → Local currency.
    */
    'fallback_budget_currency' => env('HOTEL_BAR_BUDGET_CURRENCY'),

    'ui' => [
        'title' => 'Bar',
        'items_title' => 'Register items',
        'items_tab' => 'Register',
        'stock_tab' => 'Stock',
        'sell_tab' => 'Sell',
        'purchase_lists_tab' => 'Purchase lists',
        'received_tab' => 'Received',
        'market_tab' => 'Market',
        'stock_receive_tab' => 'Receive stock',
        /*
        | Main Bar navigation (procurement back-office tabs stay on ?tab= for links/emails).
        */
        'nav_tabs' => [
            'stock' => 'Stock',
            'store-indents' => 'Receive from store',
            'purchase-lists' => 'Purchase lists',
        ],
        'store_indents_tab' => 'Receive from store',
    ],
    'recipient_statuses' => [
        'awaiting' => 'Awaiting recipient',
        'in_progress' => 'In review',
        'budget_prepared' => 'Budget prepared',
    ],
    'accounting_statuses' => [
        'pending' => 'Awaiting accountant',
        'approved' => 'Approved for payment',
        'rejected' => 'Rejected by accountant',
    ],
    'market_statuses' => [
        'waiting' => 'Waiting for accountant',
        'released' => 'Go to market',
        'completed' => 'Purchased / done',
    ],
    'market_stock_destinations' => [
        'store' => 'Store',
        'bar_department' => 'Bar',
    ],
];
