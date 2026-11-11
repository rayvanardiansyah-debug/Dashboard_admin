<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Events\OrderPlaced;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class MidtransController extends Controller
{
    public function __construct()
    {
        self::configure();
    }

    /**
     * Konfigurasi SDK Midtrans dari config/midtrans.php
     */
    public static function configure(): void
    {
        Config::$serverKey    = config('midtrans.serverKey');
        Config::$isProduction = filter_var(config('midtrans.isProduction'), FILTER_VALIDATE_BOOLEAN);
        Config::$isSanitized  = filter_var(config('midtrans.isSanitized'), FILTER_VALIDATE_BOOLEAN);
        Config::$is3ds        = filter_var(config('midtrans.is3ds'), FILTER_VALIDATE_BOOLEAN);

        // Prioritas: MIDTRANS_CACERT di .env, lalu cacert.pem bawaan paket midtrans-php.
        $cacert = config('midtrans.cacert') ?: base_path('vendor/midtrans/midtrans-php/data/cacert.pem');

        if ($cacert && is_file($cacert)) {
            // CURLOPT_HTTPHEADER wajib ada (kosong) agar SDK tidak memunculkan notice.
            Config::$curlOptions = [
                CURLOPT_HTTPHEADER => [],
                CURLOPT_CAINFO => $cacert,
            ];
        }
    }

    /**
     * Demo checkout produk digital (Acara 38) - tabel "orders".
     * Pembayaran kasir yang sebenarnya ada di KasirController::midtransCharge().
     */
    public function checkout(Request $request)
    {
        $orderId = 'ORDER-' . time();
        $price   = 50000;

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $price,
            ],
            'customer_details' => [
                'first_name' => 'John',
                'email'      => 'john@example.com',
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

        Order::create([
            'order_id'     => $orderId,
            'gross_amount' => $price,
            'snap_token'   => $snapToken,
            'status'       => 'pending',
        ]);

        return response()->json([
            'snap_token' => $snapToken,
            'order_id'   => $orderId,
        ]);
    }

    /**
     * Webhook Midtrans. Menangani dua jenis order:
     *  - transaksi kasir (order_id = transaction_number, tabel "transactions")
     *  - demo produk digital (order_id = ORDER-xxxx, tabel "orders")
     */
    public function notification(Request $request)
    {
        $notif = new Notification();

        $transaction = $notif->transaction_status;
        $orderId     = $notif->order_id;
        $fraud       = $notif->fraud_status ?? null;

        // Validasi signature agar callback tidak bisa dipalsukan
        $signature = hash(
            'sha512',
            $orderId . $notif->status_code . $notif->gross_amount . config('midtrans.serverKey')
        );

        if (! hash_equals($signature, (string) $notif->signature_key)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // 1) Transaksi kasir
        $trx = Transaction::where('transaction_number', $orderId)->first();

        if ($trx) {
            if ($trx->applyMidtransStatus($transaction, $fraud) && $trx->user) {
                event(new OrderPlaced($trx, $trx->user));
            }

            return response()->json(['message' => 'Notification handled']);
        }

        // 2) Demo produk digital
        $order = Order::where('order_id', $orderId)->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($transaction == 'capture') {
            $order->status = ($fraud == 'challenge') ? 'challenge' : 'paid';
        } elseif ($transaction == 'settlement') {
            $order->status = 'paid';
        } elseif ($transaction == 'pending') {
            $order->status = 'pending';
        } elseif (in_array($transaction, ['deny', 'expire', 'cancel'])) {
            $order->status = 'failed';
        }

        $order->save();

        return response()->json(['message' => 'Notification handled']);
    }
}
