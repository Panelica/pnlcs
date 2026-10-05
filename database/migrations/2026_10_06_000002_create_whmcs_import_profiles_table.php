<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whmcs_import_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->string('name');
            $table->string('source_table')->default('tblclients');
            $table->string('target')->default('clients');
            $table->json('mapping')->nullable();
            $table->json('constants')->nullable();
            $table->string('match_key')->nullable();
            $table->string('import_mode')->default('add');
            $table->timestamps();

            $table->index('connection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whmcs_import_profiles');
    }
};
