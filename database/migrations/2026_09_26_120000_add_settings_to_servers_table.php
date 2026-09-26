<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Settings a server type needs beyond the shared columns.
 *
 * Proxmox has no nameservers but does have a node, a resource pool, a range of
 * VM ids it may use and addresses to hand out. The node used to be read from
 * nameserver1, which the form labelled "Nameservers" - nobody could have known.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('servers', 'settings')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->json('settings')->nullable()->after('nameserver5');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('servers', 'settings')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('settings');
            });
        }
    }
};
