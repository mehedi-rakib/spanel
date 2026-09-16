<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('type'); // purchase, adjustment, bulk_edit, bulk_import, order, order_cancel, return
            $table->integer('quantity_change'); // positive = stock in, negative = stock out
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->decimal('unit_cost', 18, 4)->nullable(); // purchase price at the time, when applicable
            $table->string('reference_no')->nullable(); // e.g. purchase batch reference
            $table->text('note')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_histories');
    }
};
