<?php

return [
    'paymob' => [
        'code'        => 'paymob',
       'title'       => 'Paymob by CartCan',
       'description' => 'Pay securely using cards, wallets, and installments via Paymob. Developed by CartCan.',
        'class'       => \Webkul\Paymob\Payment\Paymob::class,
        'active'      => true,
        'sort'        => 4,

        /**
         * Logo
         */
        'image' => '/vendor/paymob/images/paymob.png',

        /**
         * Used in Admin order view & emails
         */
        'additional_details' => [
            'title' => 'Payment Gateway',
            'value' => 'Paymob (Accept – Unified Checkout)',
        ],
    ],
];
