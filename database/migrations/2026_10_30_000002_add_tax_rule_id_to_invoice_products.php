<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_products', function (Blueprint $table) {
            $table->foreignId('tax_rule_id')->nullable()->after('tax_label')->constrained('tax_rules')->nullOnDelete();
        });

        // Point existing catalog items at the rule their stored label names.
        // The catalog is company-wide, so a name is matched against the
        // company's own country first; names repeat across countries (a 23%
        // rule in PL and one in IE), and only the company's is the right one.
        $country = (string) Setting::get('Country', '');

        DB::statement('
            UPDATE invoice_products ip
            JOIN (
                SELECT name, MIN(id) AS id
                FROM tax_rules
                WHERE name IS NOT NULL AND name <> \'\' AND country = ?
                GROUP BY name
            ) tr ON tr.name = ip.tax_label
            SET ip.tax_rule_id = tr.id
            WHERE ip.tax_label IS NOT NULL AND ip.tax_label <> \'\'
        ', [$country]);
    }

    public function down(): void
    {
        Schema::table('invoice_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rule_id');
        });
    }
};
