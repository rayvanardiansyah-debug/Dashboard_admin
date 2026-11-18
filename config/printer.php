<?php

/*
 | Konfigurasi printer thermal ESC/POS
 | Library: mike42/escpos-php  (composer require mike42/escpos-php)
 */
return [

    // true  = struk dicetak otomatis oleh server (backend PHP) setelah transaksi lunas,
    //         dan kasir TIDAK lagi mencetak lewat browser.
    // false = perilaku lama (cetak lewat browser).
    'auto_print' => (bool) env('PRINTER_AUTO_PRINT', false),


    'driver' => env('PRINTER_DRIVER', 'file'),

    // Nama printer (Control Panel > Devices and Printers > nama share), untuk driver windows / cups.
    'name' => env('PRINTER_NAME', 'POS-58'),

    // Untuk driver network.
    'host' => env('PRINTER_HOST', '192.168.1.100'),
    'port' => (int) env('PRINTER_PORT', 9100),

    // Untuk driver file. Kosongkan untuk mode simulasi:
    // hasil disimpan di storage/app/receipts/*.escpos (+ pratinjau *.txt).
    'file_path' => env('PRINTER_FILE_PATH'),

    // Jumlah karakter per baris. Kosongkan = otomatis (58 mm = 32, 80 mm = 48).
    'chars' => env('PRINTER_CHARS') ? (int) env('PRINTER_CHARS') : null,

    // Cetak QR code nomor transaksi di bagian bawah struk (tidak semua printer mendukung).
    'qr' => (bool) env('PRINTER_QR', false),
];
