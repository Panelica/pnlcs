<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A free domain with hosting: the first term of a domain ordered with the
 * product is free when its extension and the product's billing cycle
 * qualify; it renews at the normal price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'free_domain')) {
                $table->boolean('free_domain')->default(false)->after('show_domain_options');
                $table->string('free_domain_tlds', 500)->nullable()->after('free_domain');
                $table->string('free_domain_cycles', 255)->nullable()->after('free_domain_tlds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['free_domain', 'free_domain_tlds', 'free_domain_cycles']);
        });
    }
};
