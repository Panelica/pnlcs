<?php

use App\Models\KbArticle;
use App\Models\KbCategory;

/**
 * The knowledge base is public — no login, no client account.
 *
 * Articles carry a published switch in the admin screen, stored inverted as
 * `private`. The public page never looked at it, so an article taken down, or
 * one still being written, stayed readable to anyone with the URL, and its
 * view counter went up while they read it.
 */
function kbArticle(bool $private): KbArticle
{
    $category = KbCategory::factory()->create(['hidden' => false]);

    return KbArticle::factory()->create([
        'category_id' => $category->id,
        'title' => $private ? 'Internal only draft' : 'How to reset your password',
        'article' => $private ? 'Root password is hunter2' : 'Click forgot password.',
        'private' => $private,
    ]);
}

test('an unpublished article is not readable by the public', function () {
    $article = kbArticle(true);

    $this->get(route('client.kb.show', $article))->assertNotFound();
});

test('a published article is readable by the public', function () {
    $article = kbArticle(false);

    $this->get(route('client.kb.show', $article))
        ->assertOk()
        ->assertSee('Click forgot password.');
});

test('an unpublished article is not listed either', function () {
    $private = kbArticle(true);
    $public = kbArticle(false);

    $this->get(route('client.kb.index'))
        ->assertOk()
        ->assertSee($public->title)
        ->assertDontSee($private->title);
});

test('reading is not counted for an article nobody should reach', function () {
    $article = kbArticle(true);
    $before = (int) $article->views;

    $this->get(route('client.kb.show', $article));

    expect((int) $article->fresh()->views)->toBe($before);
});
