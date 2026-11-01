<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * A colleague invited onto an account with some of its permissions. The pages
 * behind each permission refused them; the dashboard listed every part of
 * every account, and the payment endpoints the invoice page calls asked only
 * that the invoice was on one of the login's accounts.
 */

function sudColleague(Client $client, array $permissions): User
{
    $user = User::factory()->create();
    $client->users()->attach($user->id, ['owner' => false, 'permissions' => json_encode($permissions)]);

    return $user;
}

test('the dashboard shows a colleague only the parts of the account they were given', function () {
    $client = Client::factory()->create(['credit' => 75]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'invoice_num' => 'INV-HIDDEN-1']);
    Service::factory()->create(['client_id' => $client->id, 'status' => 'active', 'domain' => 'hidden-service.example']);
    Ticket::factory()->create(['client_id' => $client->id, 'title' => 'Visible ticket subject']);

    $page = $this->actingAs(sudColleague($client, ['tickets']))->get(route('client.home'));

    $page->assertOk()
        ->assertViewHas('unpaidInvoices', 0)
        ->assertViewHas('serviceCount', 0)
        ->assertViewHas('accountCredit', 0.0)
        ->assertViewHas('openTickets', 1)
        ->assertDontSee('INV-HIDDEN-1')
        ->assertDontSee('hidden-service.example')
        ->assertSee('Visible ticket subject');
});

test('the owner still sees all of it', function () {
    $client = Client::factory()->create(['credit' => 75]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid']);
    $owner = User::factory()->create();
    $client->users()->attach($owner->id, ['owner' => true, 'permissions' => null]);

    $this->actingAs($owner)->get(route('client.home'))
        ->assertOk()->assertViewHas('unpaidInvoices', 1)->assertViewHas('accountCredit', 75.0);
});

test('a colleague without the invoices permission cannot start a payment', function () {
    foreach (['client_id' => 'cid', 'client_secret' => 'csec', 'sandbox' => '1'] as $k => $v) {
        GatewaySettings::create(['gateway' => 'paypal', 'setting' => $k, 'value' => $v]);
    }
    Http::fake();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);

    $this->actingAs(sudColleague($client, ['tickets']))
        ->postJson(route('gateway.paypal.capture', $invoice), ['capture_id' => 'CAP-1'])
        ->assertForbidden();
    $this->actingAs(sudColleague($client, ['tickets']))
        ->postJson(route('gateway.stripe.intent', $invoice))
        ->assertForbidden();
    Http::assertNothingSent();
});

test('a colleague with it reaches the payment', function () {
    Http::fake();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);

    $this->actingAs(sudColleague($client, ['invoices']))
        ->postJson(route('gateway.paypal.capture', $invoice), [])
        ->assertOk()->assertJson(['success' => false, 'message' => 'Missing capture_id.']);
});

test('the payment endpoints wait for the second factor like the invoice page', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);
    $user = User::factory()->create(['second_factor_type' => 'totp', 'second_factor_secret' => 'JBSWY3DPEHPK3PXP']);
    $client->users()->attach($user->id, ['owner' => true, 'permissions' => null]);

    $this->actingAs($user)->post(route('gateway.paypal.capture', $invoice), ['capture_id' => 'CAP-1'])
        ->assertRedirect(route('client.2fa.verify'));
    $this->actingAs($user)->withSession(['2fa_verified' => true])
        ->postJson(route('gateway.paypal.capture', $invoice), [])
        ->assertOk();
});
