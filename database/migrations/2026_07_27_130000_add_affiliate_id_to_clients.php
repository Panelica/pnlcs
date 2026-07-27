<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The affiliate referral chain was broken at the schema level: Client listed
 * affiliate_id as fillable and AffiliateService wrote to it, but the column
 * never existed — any attempt threw "Unknown column 'affiliate_id'", and
 * commission processing read a value that was always null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'affiliate_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('group_id')
                ->constrained('affiliates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clients', 'affiliate_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });
    }
};
