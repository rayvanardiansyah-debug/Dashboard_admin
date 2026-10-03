<?php

return [

    'store_name' => env('KASIR_STORE_NAME', 'TOKO RETAIL MAKMUR'),
    'store_address' => env('KASIR_STORE_ADDRESS', 'Jl. Contoh No. 123 • Jember'),
    'store_phone' => env('KASIR_STORE_PHONE', '0812-xxxx-xxxx'),

 
    'paper_width' => (int) env('KASIR_PAPER_WIDTH', 80),

    'payment_methods' => ['Tunai', 'QRIS', 'Debit', 'Kredit', 'E-Wallet', 'Transfer'],
    'customer_types' => ['Umum', 'Pelanggan Member', 'Member VIP'],
];
