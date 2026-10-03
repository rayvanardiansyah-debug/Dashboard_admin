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
    ];

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
