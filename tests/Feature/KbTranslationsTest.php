<?php

use App\Models\Admin;
use App\Models\KbArticle;
use App\Models\KbArticleTranslation;
use App\Models\KbCategory;
use App\Models\KbCategoryTranslation;
use App\Models\Language;

/*
 * A knowledge base in more than one language.
 *
 * An article and a category held one text, so a shop open in Turkish and
 * English showed every visitor the same one. The article's own text is the
 * default language's; a translation holds another language's, and a visitor
 * without one in their language reads the default text.
 */

function kbLanguages(): void
{
    Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => true]);
    Language::updateOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'native_name' => 'Türkçe', 'is_active' => true, 'is_default' => false]);
}

function kbTranslatedArticle(): KbArticle
{
    $category = KbCategory::factory()->create(['name' => 'Getting Started', 'hidden' => false, 'parent_id' => null]);
    KbCategoryTranslation::create(['kb_category_id' => $category->id, 'language' => 'tr', 'name' => 'Başlarken']);
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'title' => 'Opening a ticket', 'article' => 'Use the ticket form.', 'private' => false]);
    KbArticleTranslation::create(['kb_article_id' => $article->id, 'language' => 'tr', 'title' => 'Destek talebi açmak', 'article' => 'Talep formunu kullanın.']);

    return $article;
}

test('a visitor reads an article and its category in their own language', function () {
    kbLanguages();
    $article = kbTranslatedArticle();

    $this->get(route('client.kb.show', $article).'?lang=tr')->assertOk()
        ->assertSee('Destek talebi açmak')->assertSee('Talep formunu kullanın.')->assertSee('Başlarken')
        ->assertDontSee('Use the ticket form.');

    $this->get(route('client.kb.index').'?lang=tr')->assertOk()
        ->assertSee('Destek talebi açmak')->assertSee('Başlarken');
});

test('without a translation in their language a visitor reads the default text', function () {
    kbLanguages();
    $article = kbTranslatedArticle();

    $this->get(route('client.kb.show', $article).'?lang=en')->assertOk()
        ->assertSee('Opening a ticket')->assertSee('Use the ticket form.')->assertDontSee('Talep formunu kullanın.');

    expect($article->fresh()->inLocale('de')->title)->toBe('Opening a ticket');
});

test('reading a translation does not change the article', function () {
    kbLanguages();
    $article = kbTranslatedArticle();

    $copy = $article->inLocale('tr');

    expect($copy->title)->toBe('Destek talebi açmak')
        ->and($article->fresh()->title)->toBe('Opening a ticket')
        ->and($copy->isDirty('title'))->toBeTrue();
});

test('the admin saves, changes and removes an article\'s translation', function () {
    kbLanguages();
    $article = kbTranslatedArticle();
    $admin = Admin::factory()->create();
    $base = ['category_id' => $article->category_id, 'title' => 'Opening a ticket', 'article' => 'Use the ticket form.', 'published' => '1'];

    $this->actingAs($admin, 'admin')->put(route('admin.config.knowledge-base.articles.update', $article), $base + [
        'translations' => ['tr' => ['title' => 'Talep açmak', 'article' => 'Formu doldurun.']],
    ])->assertRedirect();
    expect(KbArticleTranslation::where('kb_article_id', $article->id)->where('language', 'tr')->value('title'))->toBe('Talep açmak');

    $this->actingAs($admin, 'admin')->put(route('admin.config.knowledge-base.articles.update', $article), $base + [
        'translations' => ['tr' => ['title' => '', 'article' => 'left over']],
    ])->assertRedirect();
    expect(KbArticleTranslation::where('kb_article_id', $article->id)->count())->toBe(0);
});

test('a translation for the default or an inactive language is not stored', function () {
    kbLanguages();
    $category = KbCategory::factory()->create();
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.config.knowledge-base.articles.store'), [
        'category_id' => $category->id, 'title' => 'New', 'article' => 'Body', 'published' => '1',
        'translations' => ['tr' => ['title' => 'Yeni', 'article' => 'Metin'], 'en' => ['title' => 'Dup', 'article' => 'x'], 'xx' => ['title' => 'No', 'article' => 'y']],
    ])->assertRedirect();

    $article = KbArticle::where('title', 'New')->firstOrFail();
    expect($article->translations()->pluck('language')->all())->toBe(['tr']);
});

test('a category can be renamed and translated', function () {
    kbLanguages();
    $category = KbCategory::factory()->create(['name' => 'Biling']);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.config.knowledge-base'))->assertOk()
        ->assertSee(route('admin.config.knowledge-base.categories.update', $category), false);

    $this->actingAs($admin, 'admin')->put(route('admin.config.knowledge-base.categories.update', $category), [
        'name' => 'Billing', 'description' => 'Invoices',
        'translations' => ['tr' => ['name' => 'Faturalar', 'description' => 'Faturalar ve ödemeler']],
    ])->assertRedirect()->assertSessionHas('success');

    expect($category->fresh()->name)->toBe('Billing')
        ->and($category->fresh()->inLocale('tr')->name)->toBe('Faturalar')
        ->and($category->fresh()->inLocale('tr')->description)->toBe('Faturalar ve ödemeler');
});

test('deleting an article removes its translations', function () {
    kbLanguages();
    $article = kbTranslatedArticle();

    $article->delete();

    expect(KbArticleTranslation::count())->toBe(0);
});
