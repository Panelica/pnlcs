<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give orders.invoice_id an index.
 *
 * The column has been in the schema since 2026_03_31_060004 and has never had
 * one (SHOW INDEX FROM orders on a freshly migrated schema returns PRIMARY,
 * order_num_unique, client_id_foreign and status_index, and nothing else), so
 * every question of the form "is there an order attached to this invoice?" is a
 * full scan of orders. The charger asks exactly that, once per run, for the
 * whole candidate window — an order-tied invoice is a checkout, not a renewal,
 * and paying one provisions an account rather than settling a bill
 * (AutoChargeService::candidates) — and it is the only clause in that query
 * with nothing to stand on.
 *
 * It is not only the charger's: OrderService and the admin order screens join
 * the same way. An index on a foreign-key-shaped column that is queried by
 * value is the ordinary answer, and on a table of orders it is small.
 *
 * Not a foreign key, deliberately. A constraint would decide what happens to an
 * order when its invoice is deleted, which is a behaviour change on a table
 * that is already in production; an index changes nothing but the plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'invoice_id')) {
            return;
        }

        // Installations that added one by hand must not fail the migration.
        foreach (Schema::getIndexes('orders') as $index) {
            if (($index['columns'] ?? []) === ['invoice_id']) {
                return;
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index('invoice_id', 'orders_invoice_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        foreach (Schema::getIndexes('orders') as $index) {
            if (($index['name'] ?? '') === 'orders_invoice_id_index') {
                Schema::table('orders', function (Blueprint $table) {
                    $table->dropIndex('orders_invoice_id_index');
                });

                return;
            }
        }
    }
};
