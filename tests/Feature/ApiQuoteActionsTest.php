<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Database\Factories\ApiCredentialFactory;

/**
 * Sending and accepting a quote through the API.
 *
 * Both endpoints wrote the status themselves, in lower case, while everything
 * else in the application writes and reads it capitalised - and the customer
 * area compares it exactly. A quote sent through the API was therefore
 * invisible in the customer's list, 404 on its own page, and impossible to
 * accept. Accepting through the API left no invoice behind either, so there
 * was nothing for the customer to pay.
 */
function quoteActionApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function quoteForApiAction(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $quote = Quote::create([
        'client_id' => $client->id,
        'subject' => 'Migration work',
        'date' => now()->toDateString(),
        'status' => 'Draft',
        'valid_until' => now()->addMonth(),
        'subtotal' => 100,
        'total' => 100,
    ]);

    $quote->items()->create([
        'description' => 'Migration work',
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'taxable' => false,
    ]);

    return [$user, $quote];
}

test('a quote sent through the api reaches the customer', function () {
    [$user, $quote] = quoteForApiAction();

    $this->withHeaders(quoteActionApiHeaders())
        ->postJson('/api/v1/sendquote', ['quoteid' => $quote->id])
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('client.quotes.show', $quote))
        ->assertOk();
});

test('a quote accepted through the api leaves an invoice to pay', function () {
    [$user, $quote] = quoteForApiAction();

    $this->withHeaders(quoteActionApiHeaders())
        ->postJson('/api/v1/sendquote', ['quoteid' => $quote->id])
        ->assertSuccessful();

    $this->withHeaders(quoteActionApiHeaders())
        ->postJson('/api/v1/acceptquote', ['quoteid' => $quote->id])
        ->assertSuccessful();

    expect($quote->fresh()->status)->toBe('Accepted')
        ->and(Invoice::where('client_id', $quote->client_id)->count())->toBe(1);
});
