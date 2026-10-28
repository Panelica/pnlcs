<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A text the operator keeps the same as English on purpose (#324): a brand
 * name such as Stripe, a term the language borrows. It then counts as
 * translated, as the shipped database/data/same_as_english.php list does for
 * the texts PNLCS translates itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('dynamic_translations', 'same_as_english')) {
            return;
        }

        Schema::table('dynamic_translations', function (Blueprint $table) {
            $table->boolean('same_as_english')->default(false)->after('is_reviewed');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dynamic_translations', 'same_as_english')) {
            return;
        }

        Schema::table('dynamic_translations', function (Blueprint $table) {
            $table->dropColumn('same_as_english');
        });
    }
};
