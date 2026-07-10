<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. Normalize status values to lowercase enum values. Mixed-case literals
     *    ('Unpaid', 'Active', ...) were written by some code paths; MySQL's
     *    ci collation hid this in WHERE clauses but PHP strict comparisons
     *    against the lowercase enums silently failed.
     * 2. Re-map products.auto_setup to the enforced semantics:
     *    order   = provision as soon as the order is placed
     *    payment = provision when the first payment is received
     *    manual  = provision only when an admin accepts the order
     *    Legacy values: 'order' rows predate enforcement, and the effective
     *    behavior has always been provision-on-payment, so they become
     *    'payment' (keeps existing products safe). 'on' (labelled "always")
     *    becomes 'order'; 'off' (labelled "never") becomes 'manual'.
     */
    public function up(): void
    {
        foreach (['invoices', 'services', 'orders'] as $tableName) {
            DB::table($tableName)->update(['status' => DB::raw('LOWER(status)')]);
        }
        DB::table('domains')->update(['status' => DB::raw("LOWER(REPLACE(status, ' ', '_'))")]);

        DB::table('products')->where('auto_setup', 'order')->update(['auto_setup' => 'payment']);
        DB::table('products')->where('auto_setup', 'on')->update(['auto_setup' => 'order']);
        DB::table('products')->whereIn('auto_setup', ['off', ''])->orWhereNull('auto_setup')->update(['auto_setup' => 'manual']);

        Schema::table('products', function (Blueprint $table) {
            $table->string('auto_setup')->default('payment')->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('auto_setup')->default('order')->change();
        });
        // Status lowercasing is not reversed — lowercase is the canonical form.
    }
};
