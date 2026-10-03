<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique()->after('id');
            $table->string('barcode', 50)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['code', 'barcode']);
        });
    }
};
