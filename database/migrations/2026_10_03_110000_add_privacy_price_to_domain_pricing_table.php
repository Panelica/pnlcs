<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What WHOIS privacy costs per year for an extension. Null: not offered when
 * the domain is ordered (as before). Zero: offered free. More: sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('domain_pricing', 'privacy_price')) {
            Schema::table('domain_pricing', function (Blueprint $table) {
                $table->decimal('privacy_price', 15, 2)->nullable()->after('restore_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('domain_pricing', 'privacy_price')) {
            Schema::table('domain_pricing', function (Blueprint $table) {
                $table->dropColumn('privacy_price');
            });
        }
    }
};
