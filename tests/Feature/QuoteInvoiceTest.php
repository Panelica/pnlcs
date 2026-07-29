<?php

use App\Events\InvoiceCreated;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Accepting a quote.
 *
 * The invoice for an accepted quote was assembled by hand rather than through
 * InvoiceService, so it missed everything that happens to every other invoice:
 * the customer's credit balance is not touched, and nothing announces that an
 * invoice was raised, so no email goes out.
 */
function acceptedQuote(float $unit = 120.0, int $qty = 1, float $discount = 0.0): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => true, 'credit' => 0]);

    $quote = Quote::factory()->sent()->create([
        'client_id' => $client->id,
        'subtotal' => $unit * $qty - $discount,
        'tax' => 0,
        'total' => $unit * $qty - $discount,
    ]);

    QuoteItem::create([
        'quote_id' => $quote->id,
        'description' => 'Consulting',
        'quantity' => $qty,
        'unit_price' => $unit,
        'discount' => $discount,
        'taxable' => false,
    ]);

    return compact('client', 'quote', 'currency');
}

test('accepting a quote spends the credit the customer already has', function () {
    Mail::fake();
    $fx = acceptedQuote(120.0);
    $fx['client']->update(['credit' => 50]);

    $invoice = app(QuoteService::class)->convertToInvoice($fx['quote']);

    // 120 quoted, 50 already paid in: they owe 70, not 120.
    expect((float) $invoice->fresh()->total)->toBe(70.0)
        ->and((float) $fx['client']->fresh()->credit)->toBe(0.0);
});

test('accepting a quote announces the invoice so the customer is told about it', function () {
    Mail::fake();
    Event::fake([InvoiceCreated::class]);
    $fx = acceptedQuote();

    app(QuoteService::class)->convertToInvoice($fx['quote']);

    Event::assertDispatched(InvoiceCreated::class);
});

test('the invoice adds up to the same as the quote it came from', function () {
    Mail::fake();
    $fx = acceptedQuote(100.0, 2, 30.0);

    $invoice = app(QuoteService::class)->convertToInvoice($fx['quote'])->fresh();
    $items = InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');

    // 2 x 100 less 30 discount.
    expect((float) $items)->toBe(170.0)
        ->and((float) $invoice->total)->toBe(170.0);
});

test('an accepted quote is marked accepted and cannot be accepted twice', function () {
    Mail::fake();
    $fx = acceptedQuote();
    $user = User::factory()->create();
    $user->clients()->attach($fx['client']->id);

    $this->actingAs($user)->post(route('client.quotes.accept', $fx['quote']))->assertRedirect();

    expect($fx['quote']->fresh()->status)->toBe('Accepted');

    // A second acceptance must not raise a second invoice for the same work.
    $this->actingAs($user)->post(route('client.quotes.accept', $fx['quote']))->assertRedirect();

    expect(Invoice::where('client_id', $fx['client']->id)->count())->toBe(1);
});
