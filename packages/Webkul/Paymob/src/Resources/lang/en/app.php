<?php

return [
    'admin' => [
        'system' => [
            'paymob'                  => 'Paymob',
            'paymob_info'            => 'Paymob Unified Checkout (Accept API)',
            
            'status'                 => 'Status',
            'title'                  => 'Title',
            'description'            => 'Description',
            
            'public_key'             => 'Public Key',
            'public_key_info'        => 'Required. Used to open Paymob Unified Checkout.',
            
            'secret_key'             => 'Secret Key',
            'secret_key_info'        => 'Required. Used for Intention creation and refunds.',
            
            'integration_ids'        => 'Integration IDs (comma separated)',
            'integration_ids_info'   => 'Example: 2172510,4553310',
            
            'hmac'                   => 'HMAC Secret',
            'hmac_info'              => 'Used to verify Paymob callbacks.',
            
            'debug_mode'             => 'Debug Mode',
            'debug_mode_info'        => 'Enable logging of all Paymob API requests and responses to storage/logs/paymob.log',
            
            'order_min_total'        => 'Minimum Order Total (EGP)',
            'order_min_total_info'   => 'Orders below this amount will not show Paymob as payment option.',
            
            'sort'                   => 'Sort Order',
        ],
    ],

    'shop' => [
        'checkout' => [
            'paymob_title'       => 'Paymob',
            'paymob_description' => 'Pay securely using cards, wallets, and installments via Paymob.',
        ],
    ],
];