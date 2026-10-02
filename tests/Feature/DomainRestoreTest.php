<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Invoice;
use App\Models\RegistrarSettings;
use App\Models\TodoItem;
use App\Models\User;
use App\Services\DomainRestoreService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Http;

/*
 * A domain in redemption is restored at the price the operator set.
 *
 * domain_pricing.restore_price was never billed: the customer could not
 * restore the domain, and a restore done by hand cost the operator the
 * registry's fee on top of a renewal at the ordinary price.
 */

function drFixture(string $status = 'redemption', float $restorePrice = 80.0, string $registrar = 'domainnameapi'): array
{
    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 10, 'transfer_price' => 10, 'renew_price' => 12, 'restore_price' => $restorePrice,
        'min_years' => 1, 'max_years' => 10, 'enabled' => true, 'grace_period' => 30, 'redemption_grace_period' => 30,
    ]);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $domain = Domain::factory()->create([
        'client_id' => $client->id, 'domain' => 'lapsed-shop.com', 'registrar' => $registrar, 'status' => $status,
        'recurring_amount' => 12, 'expiry_date' => now()->subDays(40), 'next_due_date' => now()->subDays(40),
    ]);

    return [$user, $client, $domain];
}

it('offers the restore with its price on the domain page', function () {
    [$user, , $domain] = drFixture();

    test()->actingAs($user)->get(route('client.domains.show', $domain))
        ->assertOk()->assertSee(route('client.domains.restore', $domain), false)->assertSee(money_fmt(92));
});

it('raises one invoice for the renewal plus the restore fee, and reopens it', function () {
    [$user, $client, $domain] = drFixture();

    test()->actingAs($user)->post(route('client.domains.restore', $domain))->assertRedirect();
    test()->actingAs($user)->post(route('client.domains.restore', $domain))->assertRedirect();

    $invoices = Invoice::where('client_id', $client->id)->whereHas('items', fn ($q) => $q->where('type', DomainRestoreService::ITEM_TYPE))->get();
    expect($invoices)->toHaveCount(1)
        ->and((float) $invoices[0]->items()->where('type', DomainRestoreService::ITEM_TYPE)->value('amount'))->toBe(92.0);
});

it('offers nothing without a restore price, or outside redemption', function () {
    [$user, , $domain] = drFixture('redemption', 0.0);
    test()->actingAs($user)->post(route('client.domains.restore', $domain))->assertSessionHas('error');

    [$user2, , $active] = drFixture('active');
    test()->actingAs($user2)->get(route('client.domains.show', $active))->assertOk()->assertDontSee(route('client.domains.restore', $active), false);
    test()->actingAs($user2)->post(route('client.domains.restore', $active))->assertSessionHas('error');

    expect(Invoice::whereHas('items', fn ($q) => $q->where('type', DomainRestoreService::ITEM_TYPE))->count())->toBe(0);
});

it('restores the domain at DomainNameAPI once the invoice is paid', function () {
    Http::fake([
        '*domains/restore*' => Http::response(null, 200),
        '*domains/info*' => Http::response(['domainName' => 'lapsed-shop.com', 'expirationDate' => now()->addYear()->toIso8601String(), 'status' => 'Active']),
        '*' => Http::response([], 200),
    ]);
    [$user, $client, $domain] = drFixture();
    test()->actingAs($user)->post(route('client.domains.restore', $domain));
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-restore-1', null);

    expect($domain->fresh()->status)->toBe('active')
        ->and(TodoItem::count())->toBe(0);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'domains/restore') && $r['domainName'] === 'lapsed-shop.com');
});

it('leaves a to-do for an admin when the registrar refuses or cannot restore', function () {
    Http::fake(['*domains/restore*' => Http::response(['error' => ['message' => 'Not in redemption']], 400), '*' => Http::response([], 200)]);
    [$user, $client, $domain] = drFixture();
    test()->actingAs($user)->post(route('client.domains.restore', $domain));
    app(PaymentService::class)->applyPayment(Invoice::where('client_id', $client->id)->latest('id')->first(), 'banktransfer', 'tx-restore-2', null);

    expect($domain->fresh()->status)->toBe('redemption')
        ->and(TodoItem::where('title', 'like', '%lapsed-shop.com%')->count())->toBe(1);

    [$user2, $client2, $manual] = drFixture('redemption', 80.0, 'manual');
    test()->actingAs($user2)->post(route('client.domains.restore', $manual));
    app(PaymentService::class)->applyPayment(Invoice::where('client_id', $client2->id)->latest('id')->first(), 'banktransfer', 'tx-restore-3', null);
    expect(TodoItem::where('title', 'like', '%lapsed-shop.com%')->count())->toBe(2);
});

it('does not let another customer restore it', function () {
    [, , $domain] = drFixture();
    $stranger = User::factory()->create();
    $stranger->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    test()->actingAs($stranger)->post(route('client.domains.restore', $domain))->assertForbidden();
});
