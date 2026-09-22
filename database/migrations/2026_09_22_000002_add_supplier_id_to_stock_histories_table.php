<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('product_id');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_histories', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
