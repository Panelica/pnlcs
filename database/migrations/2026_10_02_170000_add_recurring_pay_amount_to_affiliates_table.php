<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A separate commission for renewals. Null keeps one rate for everything,
 * as before; the pay type (percentage or flat) is shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'recurring_pay_amount')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->decimal('recurring_pay_amount', 10, 2)->nullable()->after('pay_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('affiliates', 'recurring_pay_amount')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->dropColumn('recurring_pay_amount');
            });
        }
    }
};
