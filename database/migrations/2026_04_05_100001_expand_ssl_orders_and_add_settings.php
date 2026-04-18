<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ssl_orders', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('module');
            $table->text('domains')->nullable()->after('domain');
            $table->string('webserver_type')->nullable()->after('domains');
            $table->string('validation_method')->nullable()->after('webserver_type');
            $table->text('csr')->nullable()->after('validation_method');
            $table->text('cert')->nullable()->after('csr');
            $table->text('ca_cert')->nullable()->after('cert');
            $table->text('fullchain')->nullable()->after('ca_cert');
            $table->text('private_key')->nullable()->after('fullchain');
            $table->string('admin_first_name')->nullable()->after('private_key');
            $table->string('admin_last_name')->nullable()->after('admin_first_name');
            $table->string('admin_email')->nullable()->after('admin_last_name');
            $table->string('admin_phone')->nullable()->after('admin_email');
            $table->string('admin_org')->nullable()->after('admin_phone');
            $table->string('admin_address')->nullable()->after('admin_org');
            $table->string('admin_city')->nullable()->after('admin_address');
            $table->string('admin_state')->nullable()->after('admin_city');
            $table->string('admin_zip')->nullable()->after('admin_state');
            $table->string('admin_country', 2)->nullable()->after('admin_zip');
            $table->timestamp('completion_date')->nullable()->after('admin_country');
            $table->string('approver_email')->nullable()->after('completion_date');
            $table->timestamp('order_date')->nullable()->after('approver_email');
            $table->timestamp('last_polled_at')->nullable()->after('order_date');

            // Rename certificate_expiry_date to crt_expires for WHMCS compat
            $table->renameColumn('certificate_expiry_date', 'crt_expires');

            // Change status default to match WHMCS
            $table->string('status')->default('Awaiting Configuration')->change();
        });

        Schema::create('ssl_module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('setting', 100);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['module', 'setting']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('ssl_module', 100)->nullable()->after('server_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ssl_module');
        });

        Schema::dropIfExists('ssl_module_settings');

        Schema::table('ssl_orders', function (Blueprint $table) {
            $table->renameColumn('crt_expires', 'certificate_expiry_date');
            $table->string('status')->default('pending')->change();
            $table->dropColumn([
                'domain', 'domains', 'webserver_type', 'validation_method',
                'csr', 'cert', 'ca_cert', 'fullchain', 'private_key',
                'admin_first_name', 'admin_last_name', 'admin_email', 'admin_phone',
                'admin_org', 'admin_address', 'admin_city', 'admin_state',
                'admin_zip', 'admin_country', 'completion_date', 'approver_email',
                'order_date', 'last_polled_at',
            ]);
        });
    }
};
