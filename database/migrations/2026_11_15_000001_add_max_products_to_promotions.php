<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many products one order may have discounted by a promotion.
 *
 * A code limited to some products discounted every one of them in the basket:
 * a "20% off your first hosting plan" welcome code took 20% off two plans
 * ordered together (#439). 0 (every existing code) keeps today's behaviour:
 * no limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('promotions', 'max_products')) {
            return;
        }

        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_products')->default(0)->after('billing_cycles');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('promotions', 'max_products')) {
            return;
        }

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('max_products');
        });
    }
};
