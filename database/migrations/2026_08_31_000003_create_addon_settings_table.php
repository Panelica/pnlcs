<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_settings', function (Blueprint $table) {
            $table->id();
            $table->string('addon');
            $table->string('setting');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['addon', 'setting']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_settings');
    }
};
