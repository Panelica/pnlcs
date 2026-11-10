<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A price for a term of months a billing cycle has no name for.
 *
 * The `pricing` table holds a product's price in a column named after its
 * cycle - monthly, quarterly, semiannually, annually, biennially,
 * triennially - so those six terms, and only those, could be sold. Panelica
 * Central sells a licence for any term of 1 to 12 months, and a partner who
 * wanted to sell seven had nowhere to put the price.
 *
 * The six named cycles stay exactly where they are: nothing is moved here and
 * nothing reads this table for them. A row exists only for a term that has no
 * name of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_terms')) {
            return;
        }

        Schema::create('product_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            // 1 to 12 today, because that is what Central sells. A tiny
            // integer leaves room without inviting a 500-month term.
            $table->unsignedTinyInteger('months');
            $table->decimal('price', 15, 2);
            $table->decimal('setup', 15, 2)->default(0);
            $table->timestamps();
            // One price per term, per currency, per product.
            $table->unique(['product_id', 'currency_id', 'months']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_terms');
    }
};
