<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number', 'user_id', 'customer_type', 'customer_phone',
        'subtotal', 'discount_percent', 'discount', 'tax', 'other_fee',
        'grand_total', 'paid_amount', 'change_amount', 'payment_method', 'status',
        'snap_token',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalAttribute(): mixed
    {
        return $this->attributes['grand_total'] ?? 0;
    }

    /**
     * Terapkan status dari Midtrans (webhook / cek status) ke transaksi ini.
     * Mengembalikan true hanya jika transaksi BARU SAJA menjadi "paid".
     */
    public function applyMidtransStatus(string $transactionStatus, ?string $fraudStatus = null): bool
    {
        // Jangan menurunkan status transaksi yang sudah lunas.
        if ($this->status === 'paid') {
            return false;
        }

        $newStatus = match (true) {
            $transactionStatus === 'capture' => $fraudStatus === 'challenge' ? 'challenge' : 'paid',
            $transactionStatus === 'settlement' => 'paid',
            $transactionStatus === 'pending' => 'pending',
            in_array($transactionStatus, ['deny', 'expire', 'cancel', 'failure'], true) => 'failed',
            default => $this->status,
        };

        $becamePaid = $newStatus === 'paid';
        $this->status = $newStatus;

        if ($becamePaid) {
            $this->paid_amount = $this->grand_total;
            $this->change_amount = 0;
        }

        $this->save();

        return $becamePaid;
    }
}
