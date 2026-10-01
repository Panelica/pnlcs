<?php

use App\Models\Admin;
use App\Models\Announcement;

/*
 * Announcements carry an optional category and are published as RSS.
 *
 * An announcement had a title, a body and a published flag: a maintenance
 * notice and a new product looked the same, a customer could not list just
 * the maintenance ones, and nobody could follow them without visiting the site.
 */

function acrSeed(): void
{
    Announcement::create(['title' => 'Network maintenance on Sunday', 'category' => 'Maintenance', 'announcement' => '<p>Short downtime.</p>', 'published' => true]);
    Announcement::create(['title' => 'New VPS plans', 'category' => 'Product', 'announcement' => 'Bigger disks.', 'published' => true]);
    Announcement::create(['title' => 'Draft price change', 'category' => 'Pricing', 'announcement' => 'Not yet.', 'published' => false]);
}

it('lists the categories in use and filters by one', function () {
    acrSeed();

    $this->get(route('client.announcements.index'))
        ->assertOk()
        ->assertSee('Maintenance')->assertSee('Product')
        ->assertDontSee('Draft price change');

    $this->get(route('client.announcements.index', ['category' => 'Maintenance']))
        ->assertOk()
        ->assertSee('Network maintenance on Sunday')
        ->assertDontSee('New VPS plans');
});

it('ignores a category nobody uses', function () {
    acrSeed();

    $this->get(route('client.announcements.index', ['category' => 'Pricing']))
        ->assertOk()
        ->assertSee('Network maintenance on Sunday')->assertSee('New VPS plans');
});

it('publishes the announcements as an RSS feed', function () {
    acrSeed();

    $response = $this->get(route('client.announcements.rss'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml');
    $feed = simplexml_load_string($response->getContent());
    expect($feed)->not->toBeFalse()
        ->and(count($feed->channel->item))->toBe(2)
        ->and((string) $feed->channel->item[0]->category)->not->toBe('')
        ->and($response->getContent())->not->toContain('Draft price change')
        ->and($response->getContent())->toContain('Short downtime.')
        ->and($response->getContent())->not->toContain('<p>');
});

it('saves the category from the admin screen, and an empty one as none', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.config.announcements.store'), [
        'title' => 'Router upgrade', 'category' => '  Maintenance ', 'body' => 'Tonight.', 'published' => 1,
    ])->assertRedirect();
    $this->actingAs($admin, 'admin')->post(route('admin.config.announcements.store'), [
        'title' => 'Welcome', 'category' => '', 'body' => 'Hello.', 'published' => 1,
    ])->assertRedirect();

    expect(Announcement::where('title', 'Router upgrade')->value('category'))->toBe('Maintenance')
        ->and(Announcement::where('title', 'Welcome')->value('category'))->toBeNull();
});
