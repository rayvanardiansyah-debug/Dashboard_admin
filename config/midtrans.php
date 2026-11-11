<?php

return [
    'serverKey' => env('MIDTRANS_SERVER_KEY'),
    'clientKey' => env('MIDTRANS_CLIENT_KEY'),
    'isProduction' => env('MIDTRANS_IS_PRODUCTION', false),
    'isSanitized' => env('MIDTRANS_SANITIZE', true),
    'is3ds' => env('MIDTRANS_3DS', true),
    // Opsional: path file cacert.pem, mis. D:/laragon/etc/ssl/cacert.pem
    'cacert' => env('MIDTRANS_CACERT'),
];
