<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The billing periods a promotion applies to ("annually,biennially").
 *
 * A code could be limited to products but not to how long the customer pays
 * for: "60% off when you take a year" was also 60% off a single month. Empty
 * (every existing code) keeps today's behaviour: every period.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('promotions', 'billing_cycles')) {
            return;
        }

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('billing_cycles')->nullable()->after('cycles');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('promotions', 'billing_cycles')) {
            return;
        }

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('billing_cycles');
        });
    }
};
