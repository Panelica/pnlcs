<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * marketing_consents existed from the start (client_id, email_opt_in) and
 * nothing read or wrote it. A consent has to be shown when asked (KVKK,
 * GDPR, Turkey's IYS): when it was given or withdrawn, where, and from which
 * address. One row per account, holding its current state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_consents', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_consents', 'source')) {
                $table->string('source', 20)->nullable()->after('email_opt_in');
            }
            if (! Schema::hasColumn('marketing_consents', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('source');
            }
            if (! Schema::hasColumn('marketing_consents', 'consented_at')) {
                $table->timestamp('consented_at')->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('marketing_consents', 'withdrawn_at')) {
                $table->timestamp('withdrawn_at')->nullable()->after('consented_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_consents', function (Blueprint $table) {
            foreach (['source', 'ip_address', 'consented_at', 'withdrawn_at'] as $column) {
                if (Schema::hasColumn('marketing_consents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
