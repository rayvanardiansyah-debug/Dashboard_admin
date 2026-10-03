<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_type')->default('Umum');
            $table->string('customer_phone', 30)->nullable();

            $table->unsignedBigInteger('subtotal');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('discount')->default(0); // total diskon (Rp)
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('other_fee')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->unsignedBigInteger('paid_amount');
            $table->unsignedBigInteger('change_amount')->default(0);

            $table->string('payment_method');
            $table->string('status')->default('paid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
