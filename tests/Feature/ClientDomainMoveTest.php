<?php

use App\Mail\DomainMoveOfferedMail;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainMoveRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * A customer gives a domain to another client account (a site sold, a
 * business handed over). Only staff could move a domain between accounts.
 * The owner offers it; the other account accepts or declines; nothing moves
 * until it accepts.
 */

function cdmAccount(string $email): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['email' => $email]);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

it('moves the domain only when the other account accepts', function () {
    Mail::fake();
    [$seller, $from] = cdmAccount('seller@example.test');
    [$buyer, $to] = cdmAccount('buyer@example.test');
    $domain = Domain::factory()->create(['client_id' => $from->id, 'domain' => 'sold-site.com', 'status' => 'active']);

    test()->actingAs($seller)->post(route('client.domains.move', $domain), ['email' => 'Buyer@Example.test'])->assertSessionHas('success');
    $offer = DomainMoveRequest::firstOrFail();
    expect($domain->fresh()->client_id)->toBe($from->id);
    Mail::assertQueued(DomainMoveOfferedMail::class, fn ($m) => $m->hasTo('buyer@example.test'));

    test()->actingAs($buyer)->get(route('client.domains.index'))->assertSee('sold-site.com')->assertSee(route('client.domain-moves.accept', $offer), false);
    test()->actingAs($seller)->post(route('client.domain-moves.accept', $offer))->assertNotFound();

    test()->actingAs($buyer)->post(route('client.domain-moves.accept', $offer))->assertRedirect(route('client.domains.show', $domain));
    expect($domain->fresh()->client_id)->toBe($to->id)->and($offer->fresh()->status)->toBe('accepted');
});

it('lets the other account decline, and the owner withdraw', function () {
    Mail::fake();
    [$seller, $from] = cdmAccount('s2@example.test');
    [$buyer] = cdmAccount('b2@example.test');
    $domain = Domain::factory()->create(['client_id' => $from->id, 'domain' => 'kept-site.com', 'status' => 'active']);

    test()->actingAs($seller)->post(route('client.domains.move', $domain), ['email' => 'b2@example.test']);
    test()->actingAs($buyer)->post(route('client.domain-moves.decline', DomainMoveRequest::latest('id')->first()));
    expect(DomainMoveRequest::latest('id')->first()->status)->toBe('declined');

    test()->actingAs($seller)->post(route('client.domains.move', $domain), ['email' => 'b2@example.test']);
    test()->actingAs($seller)->post(route('client.domain-moves.cancel', DomainMoveRequest::latest('id')->first()));
    expect(DomainMoveRequest::latest('id')->first()->status)->toBe('cancelled')->and($domain->fresh()->client_id)->toBe($from->id);
});

it('refuses an unknown account, someone else\'s domain, a domain with an unpaid invoice, and an expired offer', function () {
    Mail::fake();
    [$seller, $from] = cdmAccount('s3@example.test');
    [$buyer, $to] = cdmAccount('b3@example.test');
    [$stranger] = cdmAccount('x3@example.test');
    $domain = Domain::factory()->create(['client_id' => $from->id, 'domain' => 'owed-site.com', 'status' => 'active']);

    test()->actingAs($seller)->post(route('client.domains.move', $domain), ['email' => 'nobody@example.test'])->assertSessionHasErrors('email');
    test()->actingAs($stranger)->post(route('client.domains.move', $domain), ['email' => 'b3@example.test'])->assertForbidden();

    $invoice = Invoice::factory()->create(['client_id' => $from->id, 'status' => 'Unpaid', 'total' => 10]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $from->id, 'type' => 'Domain', 'rel_id' => $domain->id, 'description' => 'Renewal', 'amount' => 10, 'taxed' => false]);
    test()->actingAs($seller)->post(route('client.domains.move', $domain), ['email' => 'b3@example.test'])->assertSessionHasErrors('email');
    $invoice->update(['status' => 'Cancelled']);

    test()->actingAs($seller)->post(route('client.domains.move', $domain), ['email' => 'b3@example.test']);
    $offer = DomainMoveRequest::latest('id')->first();
    $offer->update(['expires_at' => now()->subMinute()]);
    test()->actingAs($buyer)->post(route('client.domain-moves.accept', $offer))->assertNotFound();
    expect($domain->fresh()->client_id)->toBe($from->id);
});
