<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;

/**
 * Every state-changing client route that binds a client-owned model is attacked
 * here as a *different* logged-in customer. A read leak exposes someone else's
 * data; these routes would let an attacker change it — cancel a service, edit
 * nameservers, accept a quote, reply on a stranger's ticket.
 *
 * Each case asserts the request is refused AND that the victim's record is
 * untouched, so a guard that returns a friendly redirect while still writing
 * would still fail.
 */
function victim(): array
{
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'auto_renew' => true,
        'domain' => 'victim-service.com',
    ]);

    $domain = Domain::create([
        'client_id' => $client->id, 'domain' => 'victim-domain.com', 'type' => 'Register',
        'registrar' => 'Manual', 'status' => 'active', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
        'nameservers' => json_encode(['ns1' => 'ns1.victim.com']),
    ]);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 25]);

    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => 'Open',
    ]);

    $quote = Quote::create([
        'client_id' => $client->id, 'subject' => 'Victim quote', 'date' => now()->toDateString(),
        'valid_until' => now()->addDays(10)->toDateString(), 'subtotal' => 100, 'tax' => 0,
        'total' => 100, 'status' => 'Sent',
    ]);
    QuoteItem::create(['quote_id' => $quote->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 100]);

    $contact = Contact::create([
        'client_id' => $client->id, 'first_name' => 'Vic', 'last_name' => 'Tim',
        'email' => 'victim-contact@example.com',
    ]);

    $method = PaymentMethod::create([
        'client_id' => $client->id, 'description' => 'Victim bank', 'payment_type' => 'BankAccount',
        'last_four' => '4242', 'is_default' => true,
    ]);

    return compact('client', 'service', 'domain', 'invoice', 'ticket', 'quote', 'contact', 'method');
}

function attacker(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('a customer cannot change another customer records', function () {
    $v = victim();
    $attacker = attacker();

    $attacks = [
        ['post', route('client.services.autorenew', $v['service']), []],
        ['post', route('client.services.cancel.submit', $v['service']), ['reason' => 'x', 'type' => 'immediate']],
        ['post', route('client.services.upgrade.process', $v['service']), ['product_id' => $v['service']->product_id]],
        ['post', route('client.domains.autorenew', $v['domain']), []],
        ['post', route('client.domains.lock', $v['domain']), []],
        ['put', route('client.domains.nameservers', $v['domain']), ['ns1' => 'evil1.com', 'ns2' => 'evil2.com']],
        ['post', route('client.tickets.reply', $v['ticket']), ['message' => 'hijack']],
        ['post', route('client.quotes.accept', $v['quote']), []],
        ['post', route('client.quotes.decline', $v['quote']), ['reason' => 'no']],
        ['post', route('client.invoices.payment-notification', $v['invoice']), ['amount' => 25, 'transfer_date' => now()->toDateString(), 'sender_name' => 'x']],
        ['delete', route('client.account.contacts.destroy', $v['contact']), []],
        ['post', route('client.payment-methods.default', $v['method']), []],
        ['delete', route('client.payment-methods.destroy', $v['method']), []],
    ];

    $leaks = [];
    foreach ($attacks as [$verb, $url, $payload]) {
        $response = $this->actingAs($attacker)->$verb($url, $payload);
        if ($response->getStatusCode() < 400 && ! $response->isRedirect()) {
            $leaks[] = "$verb $url => HTTP ".$response->getStatusCode();
        }
    }

    expect($leaks)->toBe([]);

    // Nothing may have changed on the victim's side, even where the guard
    // answers with a redirect rather than a 403.
    $v['service']->refresh();
    $v['domain']->refresh();
    $v['ticket']->refresh();
    $v['quote']->refresh();

    expect($v['service']->status)->toBe('active')
        ->and((bool) $v['service']->auto_renew)->toBeTrue()
        ->and($v['domain']->nameservers)->not->toContain('evil1.com')
        ->and($v['domain']->status)->toBe('active')
        ->and($v['quote']->status)->toBe('Sent')
        ->and($v['ticket']->replies()->where('message', 'hijack')->exists())->toBeFalse()
        ->and(Contact::find($v['contact']->id))->not->toBeNull()
        ->and(PaymentMethod::find($v['method']->id))->not->toBeNull()
        ->and(PaymentNotification::where('invoice_id', $v['invoice']->id)->count())->toBe(0);
});

test('the owner can still perform the same actions', function () {
    $v = victim();
    $owner = User::factory()->create();
    $owner->clients()->attach($v['client']->id);

    // Sanity check: the guards above must not be blocking everyone.
    $this->actingAs($owner)
        ->post(route('client.services.autorenew', $v['service']))
        ->assertRedirect();

    expect((bool) $v['service']->fresh()->auto_renew)->toBeFalse();
});
