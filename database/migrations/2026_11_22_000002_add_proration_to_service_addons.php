<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The first period of an addon added to a running service is charged for the
 * days that service is already paid for; the next renewal is the full price.
 * The factor is kept so a pending invoice can be recalculated after an edit
 * and so the first invoice can still explain what it covered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_addons', 'prorated')) {
            Schema::table('service_addons', function (Blueprint $table) {
                $table->boolean('prorated')->default(false)->after('amount');
            });
        }

        if (! Schema::hasColumn('service_addons', 'proration_days')) {
            Schema::table('service_addons', function (Blueprint $table) {
                $table->unsignedSmallInteger('proration_days')->nullable()->after('prorated');
            });
        }

        if (! Schema::hasColumn('service_addons', 'proration_cycle_days')) {
            Schema::table('service_addons', function (Blueprint $table) {
                $table->unsignedSmallInteger('proration_cycle_days')->nullable()->after('proration_days');
            });
        }

        if (! Schema::hasColumn('service_addons', 'first_invoice_date')) {
            Schema::table('service_addons', function (Blueprint $table) {
                $table->date('first_invoice_date')->nullable()->after('next_due_date');
            });
        }
    }

    public function down(): void
    {
        foreach (['first_invoice_date', 'proration_cycle_days', 'proration_days', 'prorated'] as $column) {
            if (Schema::hasColumn('service_addons', $column)) {
                Schema::table('service_addons', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
