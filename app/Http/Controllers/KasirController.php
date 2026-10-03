<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'other_fee' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['required', 'integer', 'min:0'],
            'payment_method' => ['required', Rule::in(config('kasir.payment_methods'))],
            'customer_type' => ['nullable', Rule::in(config('kasir.customer_types'))],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ], [
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
        ]);

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
        $grandTotal = max(0, $subtotal - $discount + $tax + $otherFee);
        $paid = (int) $data['paid_amount'];

        if ($paid < $grandTotal) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Uang pembayaran masih kurang.',
            ]);
        }

        $transaction = $this->saveWithUniqueNumber(function (string $number) use (
            $request, $data, $lines, $subtotal, $discountPercent, $discount, $tax, $otherFee, $grandTotal, $paid
        ) {
            return DB::transaction(function () use (
                $request, $data, $lines, $subtotal, $discountPercent, $discount, $tax, $otherFee, $grandTotal, $paid, $number
            ) {
                $transaction = Transaction::create([
                    'transaction_number' => $number,
                    'user_id' => $request->user()->id,
                    'customer_type' => $data['customer_type'] ?? 'Umum',
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'subtotal' => $subtotal,
                    'discount_percent' => $discountPercent,
                    'discount' => $discount,
                    'tax' => $tax,
                    'other_fee' => $otherFee,
                    'grand_total' => $grandTotal,
                    'paid_amount' => $paid,
                    'change_amount' => $paid - $grandTotal,
                    'payment_method' => $data['payment_method'],
                    'status' => 'paid',
                ]);

                $transaction->details()->createMany($lines);

                return $transaction;
            });
        });

        return response()->json([
            'message' => 'Pembayaran berhasil.',
            'transaction' => [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'grand_total' => $transaction->grand_total,
                'paid_amount' => $transaction->paid_amount,
                'change_amount' => $transaction->change_amount,
                'payment_method' => $transaction->payment_method,
            ],
            'receipt_url' => route('admin.kasir.struk', $transaction),
            'next_number' => self::nextTransactionNumber(),
        ], 201);
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

    // ------------------------------------------------------------------

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
