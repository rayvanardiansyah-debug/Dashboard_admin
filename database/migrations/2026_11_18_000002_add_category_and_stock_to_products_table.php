<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Nullable agar produk lama (yang belum punya kategori) tetap aman.
            $table->foreignId('category_id')->nullable()->after('id')
                ->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('stock')->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('stock');
        });
    }
};
