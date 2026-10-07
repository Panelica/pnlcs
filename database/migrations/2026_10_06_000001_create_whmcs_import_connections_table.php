<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Can run twice without harm (RELEASING.md).
        if (Schema::hasTable('whmcs_import_connections')) {
            return;
        }

        Schema::create('whmcs_import_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('host');
            $table->unsignedInteger('port')->default(3306);
            $table->string('database');
            $table->string('username');
            $table->text('password')->nullable();
            $table->string('prefix')->default('tbl');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whmcs_import_connections');
    }
};
