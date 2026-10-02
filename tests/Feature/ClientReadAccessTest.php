<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;

/**
 * Reading someone else's records.
 *
 * The existing cross-account test covers the endpoints that change things.
 * These are the ones that only read — an invoice PDF, a ticket attachment, a
 * domain's EPP transfer code — which leak just as much.
 */
function victimRecords(): array
{
    $client = Client::factory()->create();

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'domain' => 'victim-site.com',
        'username' => 'victimuser',
        'password' => 'victimsecret',
    ]);

    $domain = Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'victim-domain.com',
        'status' => 'active',
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'invoice_num' => 'INV-VICTIM1',
    ]);

    // Draft quotes are deliberately hidden from the customer, so use one that
    // has actually been sent.
    $quote = Quote::factory()->sent()->create(['client_id' => $client->id]);

    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'title' => 'Victim private subject',
        'message' => 'Victim private message',
    ]);

    return compact('client', 'service', 'domain', 'invoice', 'quote', 'ticket');
}

function intruder(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('a customer cannot read another customer records', function () {
    $v = victimRecords();
    $intruder = intruder();

    $reads = [
        'invoice' => route('client.invoices.show', $v['invoice']),
        'invoice pdf' => route('client.invoices.pdf', $v['invoice']),
        'domain' => route('client.domains.show', $v['domain']),
        'domain epp' => route('client.domains.epp', $v['domain']),
        'quote' => route('client.quotes.show', $v['quote']),
        'ticket' => route('client.tickets.show', $v['ticket']),
        'service' => route('client.services.show', $v['service']),
        'service usage' => route('client.services.usage', $v['service']),
        'service upgrade' => route('client.services.upgrade', $v['service']),
        'service cancel' => route('client.services.cancel', $v['service']),
    ];

    $leaked = [];

    foreach ($reads as $label => $url) {
        $response = $this->actingAs($intruder)->get($url);

        if (! in_array($response->status(), [403, 404], true)) {
            $leaked[] = $label.' → HTTP '.$response->status();
        }
    }

    expect($leaked)->toBe([]);
});

test('the owner can still read their own records', function () {
    $v = victimRecords();
    $owner = User::factory()->create();
    $owner->clients()->attach($v['client']->id);

    $blocked = [];
    foreach ([
        'invoice' => route('client.invoices.show', $v['invoice']),
        'domain' => route('client.domains.show', $v['domain']),
        'quote' => route('client.quotes.show', $v['quote']),
        'ticket' => route('client.tickets.show', $v['ticket']),
        'service' => route('client.services.show', $v['service']),
    ] as $label => $url) {
        $code = $this->actingAs($owner)->get($url)->status();
        if ($code !== 200) {
            $blocked[] = $label.' => HTTP '.$code;
        }
    }

    expect($blocked)->toBe([]);
});
