<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_departments', function (Blueprint $table) {
            $table->boolean('import_active')->default(false)->after('feedback_request');
            $table->string('import_protocol')->default('imap')->after('import_active'); // imap, pop3
            $table->string('import_host')->nullable()->after('import_protocol');
            $table->unsignedInteger('import_port')->nullable()->after('import_host');
            $table->string('import_encryption')->default('ssl')->after('import_port'); // ssl, tls, none
            $table->string('import_username')->nullable()->after('import_encryption');
            $table->text('import_password')->nullable()->after('import_username'); // encrypted cast
            $table->string('import_folder')->default('INBOX')->after('import_password');
            $table->boolean('import_delete')->default(false)->after('import_folder');
            $table->boolean('import_allow_unknown')->default(false)->after('import_delete');
            $table->timestamp('last_import_at')->nullable()->after('import_allow_unknown');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_departments', function (Blueprint $table) {
            $table->dropColumn([
                'import_active', 'import_protocol', 'import_host', 'import_port',
                'import_encryption', 'import_username', 'import_password',
                'import_folder', 'import_delete', 'import_allow_unknown', 'last_import_at',
            ]);
        });
    }
};
