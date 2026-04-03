<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('homepage_content', function (Blueprint $table) {
            $table->id();
            $table->string('section_slug');
            $table->string('content_key');
            $table->text('content_value')->nullable();
            $table->string('content_type', 20)->default('text'); // text, html, json
            $table->timestamps();

            $table->unique(['section_slug', 'content_key']);
            $table->foreign('section_slug')->references('slug')->on('homepage_sections')->cascadeOnDelete();
        });

        Schema::create('theme_presets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('colors');
            $table->boolean('is_dark')->default(false);
            $table->string('source', 20)->default('builtin'); // builtin, custom, theme
            $table->string('theme_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_content');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('theme_presets');
    }
};
