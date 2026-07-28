<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * service_config_options already exists from the original schema with the
 * WHMCS column names (config_id, option_id, qty). It only lacks the price that
 * was actually charged, which the panel needs to show a breakdown that matches
 * what the customer pays — the recurring total is snapshotted on the service,
 * so reading today's option price back would drift from the invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_config_options') || Schema::hasColumn('service_config_options', 'unit_price')) {
            return;
        }

        Schema::table('service_config_options', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('service_config_options') && Schema::hasColumn('service_config_options', 'unit_price')) {
            Schema::table('service_config_options', function (Blueprint $table) {
                $table->dropColumn('unit_price');
            });
        }
    }
};
