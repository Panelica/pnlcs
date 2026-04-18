<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 50);
            $table->string('native_name', 50);
            $table->string('flag_code', 10)->nullable();
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->decimal('translation_progress', 5, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
