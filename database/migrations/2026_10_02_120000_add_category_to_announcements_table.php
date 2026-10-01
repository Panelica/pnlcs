<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An optional category on an announcement ("Maintenance", "Product",
 * "Pricing" - the operator's own words), for the client list's filter and
 * the RSS feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('announcements', 'category')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('category', 60)->nullable()->after('title')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('announcements', 'category')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            });
        }
    }
};
