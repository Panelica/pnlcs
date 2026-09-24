<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Staff two-factor recovery codes had nowhere to live: turning 2FA on showed
 * eight codes and stored none, while the verify screen checked a column the
 * admins table did not have. A member of staff who kept the codes and lost
 * the phone was locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admins', 'backup_codes')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->text('backup_codes')->nullable()->after('second_factor_secret');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'backup_codes')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('backup_codes');
            });
        }
    }
};
