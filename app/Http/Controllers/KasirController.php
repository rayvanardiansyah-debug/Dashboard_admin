<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KasirController extends Controller
{
    /**
     * Halaman transaksi pembayaran kasir (POS).
     * Data barang diambil dari tabel products.
     */
    public function index()
    {
        $products = Product::orderBy('name')
            ->get(['id', 'code', 'barcode', 'name', 'price'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code ?: self::fallbackCode($product->id),
                'barcode' => $product->barcode ?? '',
                'name' => $product->name,
                'price' => (int) $product->price,
            ])
            ->values();

        $nextNumber = self::nextTransactionNumber();

        return view('admin.kasir.index', compact('products', 'nextNumber'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCart($request, true);
        $order = $this->prepareOrder($data);
        $paid = (int) $data['paid_amount'];

        if ($paid < $order['grand_total']) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Uang pembayaran masih kurang.',
            ]);
        }

        $transaction = $this->saveWithUniqueNumber(
            fn (string $number) => $this->createTransaction($request, $data, $order, $number, $paid, 'paid')
        );

        // Trigger Event OrderPlaced (BKPM Acara 32)
        event(new OrderPlaced($transaction, $request->user()));

        return $this->transactionResponse($transaction, 'Pembayaran berhasil.', 201);
    }

    /**
     * Pembayaran non-tunai via Midtrans Snap (Acara 37 & 38).
     * Membuat transaksi berstatus "pending", lalu meminta Snap token.
     * Status menjadi "paid" lewat webhook / cek status.
     */
    public function midtransCharge(Request $request): JsonResponse
    {
        MidtransController::configure();

        $data = $this->validateCart($request, false);
        $order = $this->prepareOrder($data);

        if ($order['grand_total'] < 1) {
            throw ValidationException::withMessages([
                'items' => 'Total pembayaran harus lebih dari Rp 0.',
            ]);
        }

        $transaction = $this->saveWithUniqueNumber(
            fn (string $number) => $this->createTransaction($request, $data, $order, $number, 0, 'pending')
        );

        $params = [
            'transaction_details' => [
                'order_id' => $transaction->transaction_number,
                'gross_amount' => $order['grand_total'],
            ],
            'customer_details' => array_filter([
                'first_name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone' => $data['customer_phone'] ?? null,
            ]),
        ];

        $itemDetails = $this->midtransItems($order);
        if ($itemDetails !== null) {
            $params['item_details'] = $itemDetails;
        }

        $enabled = self::enabledPayments($data['payment_method']);
        if ($enabled) {
            $params['enabled_payments'] = $enabled;
        }

        try {
            $snapToken = Snap::getSnapToken($params);
        } catch (\Throwable $e) {
            $transaction->update(['status' => 'failed']);

            return response()->json([
                'message' => 'Midtrans menolak permintaan: '.$e->getMessage(),
            ], 502);
        }

        $transaction->update(['snap_token' => $snapToken]);

        return response()->json([
            'message' => 'Snap token dibuat.',
            'snap_token' => $snapToken,
            'transaction' => [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'grand_total' => $transaction->grand_total,
            ],
            'status_url' => route('admin.kasir.midtrans.status', $transaction),
            'next_number' => self::nextTransactionNumber(),
        ], 201);
    }

    /**
     * Cek status pembayaran ke Midtrans (cadangan bila webhook belum sampai,
     * misalnya saat dijalankan di localhost).
     */
    public function midtransStatus(Request $request, Transaction $transaction): JsonResponse
    {
        MidtransController::configure();

        if ($transaction->status === 'pending' || $transaction->status === 'challenge') {
            try {
                $status = (object) MidtransTransaction::status($transaction->transaction_number);

                $becamePaid = $transaction->applyMidtransStatus(
                    (string) ($status->transaction_status ?? ''),
                    $status->fraud_status ?? null
                );

                if ($becamePaid) {
                    event(new OrderPlaced($transaction, $request->user()));
                }
            } catch (\Throwable $e) {
                // Belum ada transaksi di Midtrans (popup ditutup sebelum memilih metode): biarkan pending.
            }
        }

        return $this->transactionResponse($transaction->fresh(), 'Status diperbarui.');
    }

    public function struk(Request $request, Transaction $transaction)
    {
        $transaction->load(['details', 'user']);

        $width = (int) $request->query('w', config('kasir.paper_width'));
        $width = $width === 58 ? 58 : 80;

        return view('admin.kasir.struk', [
            'transaction' => $transaction,
            'width' => $width,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    /**
     * Unduh invoice dalam bentuk PDF (Acara 30 - barryvdh/laravel-dompdf).
     */
    public function invoice(Transaction $transaction)
    {
        $transaction = Transaction::with('details')->findOrFail($transaction->id);

        // normalize fields expected by the invoice view
        // invoice view expects $invoice->items[*]->total and $invoice->total
        // (disesuaikan dengan kolom transactions & transaction_details)
        $invoice = $transaction;
        $invoice->setRelation('items', $invoice->details);
        foreach ($invoice->items as $item) {
            $item->setRelation('product', new Product(['name' => $item->product_name]));
            $item->quantity = $item->qty;

            if (!isset($item->total)) {
                $item->total = $item->subtotal ?? ($item->price * $item->quantity);
            }
        }
        $invoice->total = $invoice->grand_total ?? 0;

        $pdf = Pdf::loadView('invoices.invoice', ['invoice' => $invoice]);

        return $pdf->download('invoice_' . $invoice->id . '.pdf');
    }


    private function validateCart(Request $request, bool $requirePaid): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'other_fee' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => [$requirePaid ? 'required' : 'nullable', 'integer', 'min:0'],
            'payment_method' => ['required', Rule::in(config('kasir.payment_methods'))],
            'customer_type' => ['nullable', Rule::in(config('kasir.customer_types'))],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ], [
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
        ]);
    }

    /**
     * Hitung baris barang dan total dari data keranjang (harga selalu dari database).
     */
    private function prepareOrder(array $data): array
    {
        // Gabungkan baris dengan produk yang sama.
        $qtyByProduct = [];
        foreach ($data['items'] as $item) {
            $qtyByProduct[$item['id']] = ($qtyByProduct[$item['id']] ?? 0) + $item['qty'];
        }

        $products = Product::whereIn('id', array_keys($qtyByProduct))->get()->keyBy('id');

        $lines = [];
        $subtotal = 0;
        foreach ($qtyByProduct as $productId => $qty) {
            $product = $products[$productId];
            $lineSubtotal = (int) $product->price * $qty;
            $subtotal += $lineSubtotal;

            $lines[] = [
                'product_id' => $product->id,
                'product_code' => $product->code ?: self::fallbackCode($product->id),
                'product_name' => $product->name,
                'price' => (int) $product->price,
                'qty' => $qty,
                'discount' => 0,
                'subtotal' => $lineSubtotal,
            ];
        }

        $discountPercent = (float) ($data['discount_percent'] ?? 0);
        $discount = (int) round($subtotal * $discountPercent / 100) + (int) ($data['discount_amount'] ?? 0);
        $tax = (int) ($data['tax'] ?? 0);
        $otherFee = (int) ($data['other_fee'] ?? 0);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount' => $discount,
            'tax' => $tax,
            'other_fee' => $otherFee,
            'grand_total' => max(0, $subtotal - $discount + $tax + $otherFee),
        ];
    }

    private function createTransaction(Request $request, array $data, array $order, string $number, int $paid, string $status): Transaction
    {
        return DB::transaction(function () use ($request, $data, $order, $number, $paid, $status) {
            $transaction = Transaction::create([
                'transaction_number' => $number,
                'user_id' => $request->user()->id,
                'customer_type' => $data['customer_type'] ?? 'Umum',
                'customer_phone' => $data['customer_phone'] ?? null,
                'subtotal' => $order['subtotal'],
                'discount_percent' => $order['discount_percent'],
                'discount' => $order['discount'],
                'tax' => $order['tax'],
                'other_fee' => $order['other_fee'],
                'grand_total' => $order['grand_total'],
                'paid_amount' => $paid,
                'change_amount' => $status === 'paid' ? max(0, $paid - $order['grand_total']) : 0,
                'payment_method' => $data['payment_method'],
                'status' => $status,
            ]);

            $transaction->details()->createMany($order['lines']);

            return $transaction;
        });
    }

    private function transactionResponse(Transaction $transaction, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'transaction' => [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'status' => $transaction->status,
                'grand_total' => $transaction->grand_total,
                'paid_amount' => $transaction->paid_amount,
                'change_amount' => $transaction->change_amount,
                'payment_method' => $transaction->payment_method,
            ],
            'receipt_url' => route('admin.kasir.struk', $transaction),
            'invoice_url' => route('admin.kasir.invoice', $transaction),
            'next_number' => self::nextTransactionNumber(),
        ], $code);
    }

    /**
     * item_details Midtrans (jumlahnya harus sama dengan gross_amount).
     * Mengembalikan null bila tidak cocok, agar Snap tidak menolak permintaan.
     */
    private function midtransItems(array $order): ?array
    {
        $items = [];
        foreach ($order['lines'] as $line) {
            $items[] = [
                'id' => $line['product_code'],
                'price' => $line['price'],
                'quantity' => $line['qty'],
                'name' => Str::limit($line['product_name'], 50, ''),
            ];
        }

        if ($order['discount'] > 0) {
            $items[] = ['id' => 'DISKON', 'price' => -$order['discount'], 'quantity' => 1, 'name' => 'Diskon'];
        }
        if ($order['tax'] > 0) {
            $items[] = ['id' => 'PAJAK', 'price' => $order['tax'], 'quantity' => 1, 'name' => 'Pajak / PPN'];
        }
        if ($order['other_fee'] > 0) {
            $items[] = ['id' => 'BIAYA', 'price' => $order['other_fee'], 'quantity' => 1, 'name' => 'Biaya lain'];
        }

        $sum = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));

        return $sum === $order['grand_total'] ? $items : null;
    }

    /**
     * Batasi kanal Snap sesuai tombol metode yang dipilih kasir.
     * Hapus pemetaan ini bila ingin semua kanal tampil di popup.
     */
    private static function enabledPayments(string $method): array
    {
        return match ($method) {
            'QRIS' => ['other_qris', 'gopay'],
            'Debit', 'Kredit' => ['credit_card'],
            'E-Wallet' => ['gopay', 'shopeepay'],
            'Transfer' => ['bca_va', 'bni_va', 'bri_va', 'permata_va', 'echannel', 'other_va'],
            default => [],
        };
    }

    private static function fallbackCode(int $id): string
    {
        return 'BRG'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    private static function numberPrefix(): string
    {
        return 'TRX-'.now()->format('Ymd').'-';
    }

    /**
     * Nomor berikutnya: TRX-yyyymmdd-001, 002, ... (reset tiap hari).
     */
    private static function nextTransactionNumber(): string
    {
        $prefix = self::numberPrefix();

        $last = Transaction::where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function saveWithUniqueNumber(callable $save): Transaction
    {
        $attempts = 0;

        while (true) {
            try {
                return $save(self::nextTransactionNumber());
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 5) {
                    throw $e;
                }
            }
        }
    }
}
