<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * whois_logs existed and nothing wrote to it. It now records the storefront's
 * domain searches: the name, what the search answered, and the account when
 * someone was signed in (no IP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whois_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('whois_logs', 'available')) {
                $table->boolean('available')->nullable()->after('domain');
            }
            if (! Schema::hasColumn('whois_logs', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('available');
            }
            if (! Schema::hasColumn('whois_logs', 'source')) {
                $table->string('source', 20)->nullable()->after('client_id');
            }
        });
        Schema::table('whois_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::table('whois_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['domain']);
            $table->dropColumn(['available', 'client_id', 'source']);
        });
    }
};
