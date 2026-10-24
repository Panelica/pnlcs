<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether customers on a product may move to another package (#275).
 *
 * The upgrade packages list could only narrow the choice: with none ticked, any
 * package of the same module was offered, so a product could not be kept out
 * of package changes. Every existing product keeps today's behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'allow_upgrades')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allow_upgrades')->default(true)->after('retired');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'allow_upgrades')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_upgrades');
        });
    }
};
