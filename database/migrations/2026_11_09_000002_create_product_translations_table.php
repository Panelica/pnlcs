<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product description in more than one language.
 *
 * The product's own description stays the default language's text; a
 * translation row holds another active language's, and a visitor without one
 * in their language reads the default text. Same shape as the knowledge base
 * and announcement translations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_translations')) {
            return;
        }

        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('language', 10);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
