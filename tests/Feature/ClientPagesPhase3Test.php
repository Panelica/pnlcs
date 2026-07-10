<?php

use App\Models\Client;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\NetworkIssue;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;

function phase3Client(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function makeSentQuote(Client $client, array $attrs = []): Quote
{
    $quote = Quote::create(array_merge([
        'client_id'   => $client->id,
        'subject'     => 'Hosting Proposal',
        'date'        => now()->toDateString(),
        'valid_until' => now()->addDays(15)->toDateString(),
        'subtotal'    => 100.00,
        'tax'         => 0,
        'total'       => 100.00,
        'status'      => 'Sent',
    ], $attrs));

    QuoteItem::create([
        'quote_id'    => $quote->id,
        'description' => 'Web Hosting — 1 year',
        'quantity'    => 1,
        'unit_price'  => 100.00,
        'discount'    => 0,
        'taxable'     => false,
    ]);

    return $quote;
}

// ---------------------------------------------------------------------------
// Quotes
// ---------------------------------------------------------------------------

test('client can list and view a sent quote', function () {
    [$user, $client] = phase3Client();
    $quote = makeSentQuote($client);

    $this->actingAs($user)->get(route('client.quotes.index'))
        ->assertStatus(200)
        ->assertSee('Hosting Proposal');

    $this->actingAs($user)->get(route('client.quotes.show', $quote))
        ->assertStatus(200)
        ->assertSee('Web Hosting');
});

test('client cannot view another clients quote', function () {
    [$user] = phase3Client();
    $other = Client::factory()->create();
    $quote = makeSentQuote($other);

    $this->actingAs($user)->get(route('client.quotes.show', $quote))
        ->assertStatus(403);
});

test('draft quotes are hidden from the client', function () {
    [$user, $client] = phase3Client();
    $quote = makeSentQuote($client, ['status' => 'Draft']);

    $this->actingAs($user)->get(route('client.quotes.show', $quote))
        ->assertStatus(404);
});

test('accepting a quote creates an invoice and redirects to it', function () {
    [$user, $client] = phase3Client();
    $quote = makeSentQuote($client);

    $response = $this->actingAs($user)->post(route('client.quotes.accept', $quote));

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();

    expect($quote->fresh()->status)->toBe('Accepted')
        ->and($invoice)->not->toBeNull()
        ->and((float) $invoice->total)->toBe(100.0);
    $response->assertRedirect(route('client.invoices.show', $invoice));
});

test('declining a quote records the decision', function () {
    [$user, $client] = phase3Client();
    $quote = makeSentQuote($client);

    $this->actingAs($user)->post(route('client.quotes.decline', $quote), ['reason' => 'Too expensive']);

    expect($quote->fresh()->status)->toBe('Declined')
        ->and($quote->fresh()->customer_notes)->toBe('Too expensive');
});

test('an expired quote cannot be accepted', function () {
    [$user, $client] = phase3Client();
    $quote = makeSentQuote($client, ['valid_until' => now()->subDay()->toDateString()]);

    $this->actingAs($user)->post(route('client.quotes.accept', $quote));

    expect($quote->fresh()->status)->toBe('Sent')
        ->and(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Payment methods
// ---------------------------------------------------------------------------

test('client can add, default and remove a bank account payment method', function () {
    [$user, $client] = phase3Client();

    $this->actingAs($user)->post(route('client.payment-methods.store'), [
        'description'  => 'John Doe — Example Bank',
        'payment_type' => 'BankAccount',
        'last_four'    => '4242',
    ]);

    $method = PaymentMethod::where('client_id', $client->id)->first();
    expect($method)->not->toBeNull()
        ->and($method->last_four)->toBe('4242');

    $this->actingAs($user)->post(route('client.payment-methods.default', $method));
    expect($method->fresh()->is_default)->toBeTrue();

    $this->actingAs($user)->delete(route('client.payment-methods.destroy', $method));
    expect(PaymentMethod::where('client_id', $client->id)->count())->toBe(0);
});

test('client cannot manage another clients payment method', function () {
    [$user] = phase3Client();
    $other = Client::factory()->create();
    $method = PaymentMethod::create([
        'client_id' => $other->id, 'description' => 'Other', 'payment_type' => 'BankAccount',
    ]);

    $this->actingAs($user)->delete(route('client.payment-methods.destroy', $method))
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Network status
// ---------------------------------------------------------------------------

test('network status page shows active and resolved issues', function () {
    NetworkIssue::create([
        'title' => 'Core switch degradation', 'description' => 'Investigating packet loss.',
        'type' => 'network', 'status' => 'open', 'priority' => 'high', 'start_date' => now(),
    ]);
    NetworkIssue::create([
        'title' => 'Old maintenance', 'description' => 'Done.',
        'type' => 'server', 'status' => 'resolved', 'end_date' => now()->subDay(),
    ]);

    $this->get(route('client.network-status'))
        ->assertStatus(200)
        ->assertSee('Core switch degradation')
        ->assertSee('Old maintenance');
});

test('network status page shows all-operational when no issues', function () {
    $this->get(route('client.network-status'))
        ->assertStatus(200)
        ->assertSee('All Systems Operational');
});

// ---------------------------------------------------------------------------
// Email history
// ---------------------------------------------------------------------------

test('client can list and view own emails only', function () {
    [$user, $client] = phase3Client();
    $other = Client::factory()->create();

    $mine = Email::create([
        'client_id' => $client->id, 'subject' => 'Welcome aboard',
        'message' => '<p>Hello</p>', 'date' => now(), 'to' => $client->email,
    ]);
    $theirs = Email::create([
        'client_id' => $other->id, 'subject' => 'Secret email',
        'message' => '<p>Hi</p>', 'date' => now(), 'to' => $other->email,
    ]);

    $this->actingAs($user)->get(route('client.emails.index'))
        ->assertStatus(200)
        ->assertSee('Welcome aboard')
        ->assertDontSee('Secret email');

    $this->actingAs($user)->get(route('client.emails.show', $mine))->assertStatus(200);
    $this->actingAs($user)->get(route('client.emails.show', $theirs))->assertStatus(403);
});

test('sent mails are logged into the emails table for the client', function () {
    [$user, $client] = phase3Client();

    \Illuminate\Support\Facades\Mail::mailer('array')->raw('Test body', function ($m) use ($client) {
        $m->to($client->email)->subject('Log me');
    });

    $email = Email::where('subject', 'Log me')->first();

    expect($email)->not->toBeNull()
        ->and($email->client_id)->toBe($client->id)
        ->and($email->to)->toContain($client->email);
});
