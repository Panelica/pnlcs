<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A download can be kept to the customers who own particular products.
 * No rows for a download means it is open to every signed-in customer, as
 * before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('download_product')) {
            Schema::create('download_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('download_id')->constrained('downloads')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unique(['download_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('download_product');
    }
};
