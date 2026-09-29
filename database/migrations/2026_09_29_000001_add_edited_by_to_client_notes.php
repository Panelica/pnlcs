<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Who last edited a client note and when.
 *
 * `admin` keeps the original author; `edited_by` records the last editor's
 * full name (empty until the note is edited), and `updated_at` the timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_notes', 'edited_by')) {
            Schema::table('client_notes', function (Blueprint $table) {
                $table->string('edited_by')->nullable()->after('admin');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_notes', 'edited_by')) {
            Schema::table('client_notes', function (Blueprint $table) {
                $table->dropColumn('edited_by');
            });
        }
    }
};
