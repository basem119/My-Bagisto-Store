<?php

return [
    [
        'key'   => 'sales.payment_methods.paymob',
        'name'  => 'paymob::app.admin.system.paymob',
        'info'  => 'paymob::app.admin.system.paymob_info',
        'sort'  => 4,

        'fields'=> [

            // ======================
            // Enable / Disable
            // ======================
            [
                'name'          => 'active',
                'title'         => 'paymob::app.admin.system.status',
                'type'          => 'boolean',
                'default_value' => true,
                'channel_based' => true,
            ],

            // ======================
            // Display
            // ======================
            [
                'name'          => 'title',
                'title'         => 'paymob::app.admin.system.title',
                'type'          => 'text',
                'default_value' => 'Paymob',
                'channel_based' => true,
                'locale_based'  => true,
            ],

            [
                'name'          => 'description',
                'title'         => 'paymob::app.admin.system.description',
                'type'          => 'textarea',
                'default_value' => 'Pay securely using cards, wallets, and installments via Paymob.',
                'channel_based' => true,
                'locale_based'  => true,
            ],

            // ======================
            // REQUIRED – Unified Checkout
            // ======================
            [
                'name'          => 'public_key',
                'title'         => 'paymob::app.admin.system.public_key',
                'type'          => 'password',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'paymob::app.admin.system.public_key_info',
            ],

            [
                'name'          => 'secret_key',
                'title'         => 'paymob::app.admin.system.secret_key',
                'type'          => 'password',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'paymob::app.admin.system.secret_key_info',
            ],

            [
                'name'          => 'integration_ids',
                'title'         => 'paymob::app.admin.system.integration_ids',
                'type'          => 'text',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'paymob::app.admin.system.integration_ids_info',
            ],

            [
                'name'          => 'hmac',
                'title'         => 'paymob::app.admin.system.hmac',
                'type'          => 'text',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'paymob::app.admin.system.hmac_info',
            ],

            // ======================
            // Debug Mode
            // ======================
            [
                'name'          => 'debug_mode',
                'title'         => 'paymob::app.admin.system.debug_mode',
                'type'          => 'boolean',
                'default_value' => false,
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'paymob::app.admin.system.debug_mode_info',
            ],

            // ======================
            // Optional
            // ======================
            [
                'name'          => 'order_min_total',
                'title'         => 'paymob::app.admin.system.order_min_total',
                'type'          => 'text',
                'default_value' => '0',
                'channel_based' => false,
                'locale_based'  => false,
                'info'          => 'paymob::app.admin.system.order_min_total_info',
            ],

            [
                'name'          => 'sort',
                'title'         => 'paymob::app.admin.system.sort',
                'type'          => 'text',
                'default_value' => '4',
                'channel_based' => false,
                'locale_based'  => false,
            ],
        ]
    ]
];