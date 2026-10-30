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

        // Backfill legacy items from the rate/label they were stored with. An
        // item that already matches an existing rule by name or rate is linked
        // to it; anything else keeps a null rule and groups by its label as
        // before. Nothing is created, and nothing historical is deleted.
        foreach (DB::table('invoice_items')->get() as $item) {
            $ruleId = null;

            if ($item->tax_label !== null && $item->tax_label !== '') {
                $ruleId = DB::table('tax_rules')->where('name', $item->tax_label)->value('id');
            }

            if ($ruleId === null && $item->tax_rate !== null) {
                $ruleId = DB::table('tax_rules')->where('tax_rate', (float) $item->tax_rate)->value('id');
            }

            if ($ruleId !== null) {
                DB::table('invoice_items')->where('id', $item->id)->update(['tax_rule_id' => $ruleId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rule_id');
        });
    }
};
