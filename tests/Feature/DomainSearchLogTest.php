<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhoisLog;
use App\Services\DomainAvailability;

/*
 * Storefront domain searches are recorded and shown to staff.
 *
 * whois_logs existed and nothing wrote to it or read it: what visitors looked
 * for, and which free names nobody went on to buy, was nowhere to be seen.
 */

function dslFake(array $answers): void
{
    $fake = Mockery::mock(DomainAvailability::class);
    $fake->shouldReceive('checkMany')->andReturnUsing(fn (array $names) => collect($names)->mapWithKeys(fn ($n) => [strtolower($n) => $answers[strtolower($n)] ?? ['available' => false, 'checked' => true]])->all());
    $fake->shouldReceive('check')->andReturnUsing(fn ($n) => $answers[strtolower($n)] ?? ['available' => false, 'checked' => true]);
    app()->instance(DomainAvailability::class, $fake);
    foreach (['.com', '.net'] as $tld) {
        DomainPricing::updateOrCreate(['extension' => $tld], ['register_price' => 10, 'transfer_price' => 10, 'renew_price' => 12, 'enabled' => true]);
    }
}

it('records the name searched, its answer and the signed-in account, not the suggestions', function () {
    dslFake(['free-shop.com' => ['available' => true, 'checked' => true], 'gone-shop.com' => ['available' => false, 'checked' => true]]);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    test()->get(route('client.domain.search', ['domain' => 'free-shop.com']))->assertOk();
    test()->actingAs($user)->postJson(route('client.domain.check'), ['domain' => 'gone-shop', 'tld' => '.com'])->assertOk();

    $rows = WhoisLog::orderBy('id')->get();
    expect($rows->pluck('domain')->all())->toBe(['free-shop.com', 'gone-shop.com'])
        ->and($rows[0]->available)->toBeTrue()->and($rows[0]->client_id)->toBeNull()
        ->and($rows[1]->available)->toBeFalse()->and($rows[1]->client_id)->toBe($client->id);
});

it('records an unanswered lookup as unanswered, and nothing when switched off', function () {
    dslFake(['slow-shop.com' => ['available' => false, 'checked' => false]]);

    test()->get(route('client.domain.search', ['domain' => 'slow-shop.com']))->assertOk();
    expect(WhoisLog::sole()->available)->toBeNull();

    Setting::set('DomainSearchLog', '0', 'general');
    test()->get(route('client.domain.search', ['domain' => 'slow-shop.com']))->assertOk();
    expect(WhoisLog::count())->toBe(1);
});

it('shows staff the totals, the extensions and the free names nobody bought', function () {
    foreach ([['wanted-shop.com', true], ['wanted-shop.com', true], ['bought-shop.com', true], ['taken-shop.net', false]] as [$d, $a]) {
        WhoisLog::create(['domain' => $d, 'available' => $a, 'source' => 'search', 'date' => now()]);
    }
    Domain::factory()->create(['domain' => 'bought-shop.com', 'client_id' => Client::factory()->create()->id]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['list_domains']])->id]);

    $page = test()->actingAs($admin, 'admin')->get(route('admin.domains.searches'))->assertOk();
    expect($page->viewData('total'))->toBe(4)
        ->and($page->viewData('missed')->all())->toBe(['wanted-shop.com' => 2])
        ->and($page->viewData('extensions')->all())->toBe(['.com' => 3, '.net' => 1]);
    test()->actingAs($admin, 'admin')->get(route('admin.domains.index'))->assertSee(route('admin.domains.searches'), false);
});

it('is pruned with the other logs', function () {
    WhoisLog::create(['domain' => 'old-shop.com', 'available' => true, 'source' => 'search', 'date' => now()]);
    WhoisLog::query()->update(['created_at' => now()->subDays(100)]);
    WhoisLog::create(['domain' => 'new-shop.com', 'available' => true, 'source' => 'search', 'date' => now()]);

    test()->artisan('pnlcs:prune-logs')->assertSuccessful();

    expect(WhoisLog::pluck('domain')->all())->toBe(['new-shop.com']);
});
