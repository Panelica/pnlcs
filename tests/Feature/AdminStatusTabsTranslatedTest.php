<?php

use App\Models\Admin;

/*
 * The status filter tabs on the admin order, service and ticket lists.
 *
 * They were written as English literals inside the @foreach that draws them,
 * so they stayed English in every language - and the hardcoded-text gate does
 * not see them, because it reads text between tags and these sit inside a
 * directive's expression. Reported by @hestiacn.
 *
 * What the tab FILTERS by (the query value: "pending", "customer-reply",
 * "on hold", ...) is not a label and must not change: these tests pin the
 * values as well as the words.
 */

function statusTabsPage(string $route, string $locale): string
{
    app()->setLocale($locale);

    return test()->actingAs(Admin::factory()->create(), 'admin')
        ->get(route($route, ['_locale_probe' => 1]))
        ->assertOk()
        ->getContent();
}

dataset('status tab pages', [
    'orders' => ['admin.orders.index', ['pending', 'active', 'fraud', 'cancelled'], ['status=pending', 'status=fraud']],
    'services' => ['admin.services.index', ['active', 'suspended', 'terminated', 'pending', 'cancelled'], ['status=suspended', 'status=terminated']],
    'tickets' => ['admin.tickets.index', ['open', 'answered', 'customer-reply', 'closed', 'on-hold'], ['status=open', 'status=customer-reply', 'status=on%20hold']],
]);

it('shows the status tabs in the active language', function (string $route, array $statusKeys, array $expectedQueries) {
    foreach (['tr', 'de', 'zh'] as $locale) {
        $html = statusTabsPage($route, $locale);

        app()->setLocale($locale);
        foreach (array_merge(['common.form.all'], array_map(fn ($k) => "common.status.{$k}", $statusKeys)) as $key) {
            $label = __($key);
            // Pest's toContain takes more needles, not a message: one each.
            expect($label)->not->toBe($key);
            expect($html)->toContain(e($label));
        }
    }
})->with('status tab pages');

it('filters by the same values as before, whatever the language', function (string $route, array $statusKeys, array $expectedQueries) {
    $html = statusTabsPage($route, 'tr');

    foreach ($expectedQueries as $query) {
        expect($html)->toContain($query);
    }
})->with('status tab pages');
