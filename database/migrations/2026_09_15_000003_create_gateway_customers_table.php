<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer record a client has at a gateway, written the moment it exists.
 *
 * Stripe hangs stored cards off a customer, and a client who collects a new
 * customer every time they open the card form ends up with their cards
 * scattered across records that nothing joins back together. Until now the only
 * place that id was kept was payment_methods, which is written when the card is
 * confirmed — minutes after the customer was created, and never at all if the
 * customer wandered off before finishing. Two tabs open at once therefore made
 * two customers, and so did anyone who came back a day later.
 *
 * One row per client per gateway, and the unique key is what enforces it: the
 * second writer of a pair loses on the key rather than on being slower, so the
 * race has an answer instead of an outcome.
 *
 * A table of its own rather than a column on clients, because the id belongs to
 * a particular gateway and a client can perfectly well have one at each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_customers', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // The gateway's own id for this client (Stripe: cus_...).
            $table->string('customer_id', 191);
            $table->timestamps();

            $table->unique(['gateway', 'client_id']);
            $table->index(['gateway', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_customers');
    }
};
