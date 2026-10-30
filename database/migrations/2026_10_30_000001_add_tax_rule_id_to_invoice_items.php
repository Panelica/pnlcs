<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('tax_rule_id')->nullable()->after('tax_label')->constrained('tax_rules')->nullOnDelete();
        });

        // Link legacy items to the rule their stored label names, and only by
        // label: "same rate" is not "same rule", and matching on rate alone
        // could hang an old Polish line on another country's rule. The line's
        // own label keeps rendering either way, so nothing historical changes.
        // One set-based statement, not one query per row.
        DB::statement('
            UPDATE invoice_items ii
            JOIN (
                SELECT name, MIN(id) AS id
                FROM tax_rules
                WHERE name IS NOT NULL AND name <> \'\'
                GROUP BY name
            ) tr ON tr.name = ii.tax_label
            SET ii.tax_rule_id = tr.id
            WHERE ii.tax_label IS NOT NULL AND ii.tax_label <> \'\'
        ');
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rule_id');
        });
    }
};
