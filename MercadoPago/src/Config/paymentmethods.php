<?php

return [
    'mercadopago_standard' => [
        'code'             => 'mercadopago_standard',
        'title'            => 'Mercado Pago',
        'description'      => 'Mercado Pago',
        'class'            => 'Midnight\MercadoPago\Payment\Standard',
        'sandbox'          => true,
        'active'           => true,
        'access_token'     => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        'public_key'       => env('MERCADOPAGO_PUBLIC_KEY', ''),
        'sort'             => 5,
    ],
];
