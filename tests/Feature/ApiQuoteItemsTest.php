<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Quote;
use App\Services\QuoteService;
use Database\Factories\ApiCredentialFactory;

/**
 * A quote raised through the API.
 *
 * Its lines are written with the keys amount and taxed. The quote_items table
 * has neither: the columns are unit_price and taxable, and the model does not
 * accept the other two, so both were dropped without a word. Every line came
 * out priced at nothing while the quote header carried the total the caller
 * had asked for.
 *
 * Accepting such a quote reads the lines, so the customer agreed to a quote
 * for five hundred and was handed an invoice for zero.
 */
function quoteApiHeaders(): array
{
    return [
        'X-API-Key' => ApiCredential::factory()->create()->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

test('a line keeps the price it was given', function () {
    $client = Client::factory()->create();

    $this->postJson('/api/v1/createquote', [
        'clientid' => $client->id,
        'subject' => 'Migration work',
        'items' => [
            ['description' => 'Migration', 'amount' => 400, 'quantity' => 1, 'taxed' => true],
            ['description' => 'Training', 'amount' => 50, 'quantity' => 2, 'taxed' => false],
        ],
    ], quoteApiHeaders())->assertOk();

    $quote = Quote::latest('id')->firstOrFail()->load('items');

    expect((float) $quote->items[0]->unit_price)->toBe(400.0)
        ->and((float) $quote->items[1]->unit_price)->toBe(50.0)
        ->and((int) $quote->items[1]->quantity)->toBe(2)
        ->and((bool) $quote->items[0]->taxable)->toBeTrue()
        ->and((bool) $quote->items[1]->taxable)->toBeFalse();
});

test('the header agrees with the lines', function () {
    $client = Client::factory()->create();

    $this->postJson('/api/v1/createquote', [
        'clientid' => $client->id,
        'items' => [['description' => 'Work', 'amount' => 400, 'quantity' => 1]],
    ], quoteApiHeaders())->assertOk();

    $quote = Quote::latest('id')->firstOrFail()->load('items');
    $lines = $quote->items->sum(fn ($i) => $i->quantity * $i->unit_price - $i->discount);

    expect((float) $quote->total)->toBe((float) $lines);
});

test('accepting it bills what was quoted', function () {
    $client = Client::factory()->create();

    $this->postJson('/api/v1/createquote', [
        'clientid' => $client->id,
        'items' => [['description' => 'Migration', 'amount' => 500, 'quantity' => 1]],
    ], quoteApiHeaders())->assertOk();

    $quote = Quote::latest('id')->firstOrFail();
    $invoice = app(QuoteService::class)->convertToInvoice($quote);

    expect((float) $invoice->fresh()->total)->toBe(500.0);
});
