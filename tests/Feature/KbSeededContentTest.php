<?php

use App\Models\KbArticle;
use App\Models\KbCategory;

/*
 * The knowledge base ships with its customer content, from the repository.
 *
 * Hand-entered articles die with the database - they did once, and there was
 * no backup to restore them from. Content that matters ships as a migration
 * and comes back with every install; an operator's edits to a seeded article
 * survive deploys, because the seed matches by title and only fills gaps.
 */

test('every customer-facing area of the panel has a category with articles', function () {
    $expected = [
        'Getting Started' => 5,
        'Hosting & Websites' => 12,
        'Apps & Docker' => 7,
        'Domains' => 5,
        'Billing' => 6,
        'SSL Certificates' => 2,
        'Support' => 3,
    ];

    foreach ($expected as $name => $minArticles) {
        $category = KbCategory::where('name', $name)->first();
        expect($category)->not->toBeNull()
            ->and($category->hidden)->toBeFalse()
            ->and(KbArticle::where('category_id', $category->id)->where('private', false)->count())
                ->toBeGreaterThanOrEqual($minArticles);
    }
});

test('the public page shows all of it', function () {
    $html = $this->get(route('client.kb.index'))->assertOk()->getContent();

    foreach (['Getting Started', 'Hosting & Websites', 'Domains', 'Billing', 'Support'] as $name) {
        expect($html)->toContain(e($name)); // the view escapes, so & arrives as &amp;
    }

    // The article the homepage's "How it works" visitor most needs.
    expect($html)->toContain('Ordering: you do not need an account first');
});

test('an operator edit to a seeded article survives a redeploy', function () {
    $article = KbArticle::where('title', 'Account credit')->firstOrFail();
    $article->update(['article' => 'Edited by the operator.']);

    // Re-running the seeder must not clobber the edit: it matches by title
    // and only inserts what is missing.
    (require base_path('database/migrations/2026_08_24_120000_seed_kb_customer_articles.php'))->up();

    expect($article->fresh()->article)->toBe('Edited by the operator.');
});
