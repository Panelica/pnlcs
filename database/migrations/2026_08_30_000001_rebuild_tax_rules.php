<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tax_rules', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('tax_rate');
            $table->dropColumn('level');
        });

        // Preserve a working global default after upgrade: the old catch-all
        // rule (empty country) becomes the default so tax keeps applying until
        // an operator picks a specific one.
        $default = DB::table('tax_rules')->where('country', '')->orderBy('id')->first()
            ?? DB::table('tax_rules')->orderBy('id')->first();

        if ($default) {
            DB::table('tax_rules')->where('id', $default->id)->update(['is_default' => true]);
        }
    }

    public function down(): void {
        Schema::table('tax_rules', function (Blueprint $table) {
            $table->integer('level')->default(1)->after('id');
            $table->dropColumn('is_default');
        });
    }
};
