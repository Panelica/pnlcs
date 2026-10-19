<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A knowledge base in more than one language.
 *
 * An article and a category held one text, so a shop open in two languages
 * showed every visitor the same one: its Turkish customers read English help,
 * or its English ones Turkish. The article's own text stays the text of the
 * default language; a translation row holds another language's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('title');
            $table->text('article');
            $table->timestamps();
            $table->unique(['kb_article_id', 'language']);
        });

        Schema::create('kb_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_category_id')->constrained('kb_categories')->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['kb_category_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_category_translations');
        Schema::dropIfExists('kb_article_translations');
    }
};
