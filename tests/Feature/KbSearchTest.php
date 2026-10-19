<?php

use App\Models\KbArticle;
use App\Models\KbCategory;

/*
 * The knowledge base page has a search box. It sent ?q= and the page came
 * back with every article, whatever was typed.
 */

function kbSearchArticles(): void
{
    // The installer seeds help articles of its own; these tests read the page alone.
    KbArticle::query()->delete();
    KbCategory::query()->delete();

    $start = KbCategory::factory()->create(['name' => 'Getting Started', 'hidden' => false, 'parent_id' => null]);
    $billing = KbCategory::factory()->create(['name' => 'Billing', 'hidden' => false, 'parent_id' => null]);
    KbArticle::factory()->create(['category_id' => $start->id, 'title' => 'Opening a ticket', 'article' => 'Use the ticket form.', 'private' => false]);
    KbArticle::factory()->create(['category_id' => $billing->id, 'title' => 'How invoices work', 'article' => 'An invoice is raised before renewal.', 'private' => false]);
    KbArticle::factory()->create(['category_id' => $billing->id, 'title' => 'Draft about invoices', 'article' => 'Not ready.', 'private' => true]);
}

test('a search lists only the articles it matches, by title or text', function () {
    kbSearchArticles();

    $this->get(route('client.kb.index', ['q' => 'INVOICE']))->assertOk()
        ->assertSee('How invoices work')->assertDontSee('Opening a ticket')
        ->assertDontSee('Getting Started')->assertDontSee('Draft about invoices');

    $this->get(route('client.kb.index', ['q' => 'ticket form']))->assertOk()
        ->assertSee('Opening a ticket')->assertDontSee('How invoices work');
});

test('a search with no match says so', function () {
    kbSearchArticles();

    $this->get(route('client.kb.index', ['q' => 'nothing-like-this']))->assertOk()
        ->assertSee(__('client.kb.no_results'))->assertSee('nothing-like-this');
});

test('the wildcards of a search are taken literally', function () {
    kbSearchArticles();

    $this->get(route('client.kb.index', ['q' => '%']))->assertOk()
        ->assertSee(__('client.kb.no_results'))->assertDontSee('Opening a ticket');
});

test('without a search the page lists every published article', function () {
    kbSearchArticles();

    $this->get(route('client.kb.index'))->assertOk()
        ->assertSee('Opening a ticket')->assertSee('How invoices work')->assertDontSee('Draft about invoices');
});
