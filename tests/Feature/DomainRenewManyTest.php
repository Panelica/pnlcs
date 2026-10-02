<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\User;

/*
 * A customer renews several domains at once, on one invoice.
 *
 * Renewing from the panel was one domain, one invoice at a time.
 */

function rmCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function rmDomain(Client $client, string $name, array $attrs = []): Domain
{
    return Domain::create(array_merge([
        'client_id' => $client->id, 'domain' => $name, 'type' => 'Register', 'registrar' => '', 'status' => 'active',
        'registration_period' => 1, 'expiry_date' => now()->addMonths(3)->toDateString(), 'next_due_date' => now()->addMonths(3)->toDateString(),
        'first_payment_amount' => 10, 'recurring_amount' => 15,
    ], $attrs));
}

it('renews the selected domains on one invoice', function () {
    [$user, $client] = rmCustomer();
    $a = rmDomain($client, 'one-renew.com');
    $b = rmDomain($client, 'two-renew.net', ['recurring_amount' => 20]);

    test()->actingAs($user)->get(route('client.domains.index'))->assertOk()->assertSee('form="renew-many"', false);

    $response = test()->actingAs($user)->post(route('client.domains.renew-many'), ['domain_ids' => [$a->id, $b->id]]);

    $invoice = Invoice::where('client_id', $client->id)->sole();
    $response->assertRedirect(route('client.invoices.show', $invoice));
    expect($invoice->items()->where('type', 'Domain')->pluck('rel_id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and((float) $invoice->subtotal)->toBe(35.0);
});

it('leaves out a domain already on an open invoice, and one that cannot be renewed', function () {
    [$user, $client] = rmCustomer();
    $a = rmDomain($client, 'billed-renew.com');
    $b = rmDomain($client, 'fresh-renew.com');
    $c = rmDomain($client, 'gone-renew.com', ['status' => 'expired']);
    test()->actingAs($user)->post(route('client.domains.renew', $a));

    test()->actingAs($user)->post(route('client.domains.renew-many'), ['domain_ids' => [$a->id, $b->id, $c->id]])->assertSessionHas('success');

    expect(Invoice::where('client_id', $client->id)->count())->toBe(2);
    $latest = Invoice::where('client_id', $client->id)->latest('id')->first();
    expect($latest->items()->where('type', 'Domain')->pluck('rel_id')->all())->toBe([$b->id]);
});

it('sends the customer to their invoices when everything is already billed', function () {
    [$user, $client] = rmCustomer();
    $a = rmDomain($client, 'only-renew.com');
    test()->actingAs($user)->post(route('client.domains.renew', $a));

    test()->actingAs($user)->post(route('client.domains.renew-many'), ['domain_ids' => [$a->id]])->assertRedirect(route('client.invoices.index'));
    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});

it('refuses another customer\'s domain and bills nothing', function () {
    [$user, $client] = rmCustomer();
    [, $other] = rmCustomer();
    $mine = rmDomain($client, 'mine-renew.com');
    $theirs = rmDomain($other, 'theirs-renew.com');

    test()->actingAs($user)->post(route('client.domains.renew-many'), ['domain_ids' => [$mine->id, $theirs->id]])->assertForbidden();
    expect(Invoice::count())->toBe(0);
});
