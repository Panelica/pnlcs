<?php

use App\Events\InvoiceCreated;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Services\InvoiceService;
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

// ---------------------------------------------------------------------------
// Converting the same quote twice
// ---------------------------------------------------------------------------

/**
 * One quote, two invoices.
 *
 * Converting a quote raises an invoice and marks the quote accepted. The
 * customer's own accept button refuses to do it twice - the quote has to be
 * "Sent" - and the API returns early on an accepted quote. The admin button
 * checks nothing at all.
 *
 * So a second click, or converting a quote the customer has already accepted
 * themselves, raises a second invoice for the same piece of work, and the
 * customer is chased for both.
 */
test('converting the same quote again gives back the invoice it already made', function () {
    Mail::fake();
    $fx = acceptedQuote(120.0);

    $service = app(QuoteService::class);
    $first = $service->convertToInvoice($fx['quote']);
    $second = $service->convertToInvoice($fx['quote']->fresh());

    expect($second->id)->toBe($first->id);
    expect(InvoiceItem::where('type', 'Quote')->where('rel_id', $fx['quote']->id)->count())->toBe(1);
});

test('the admin convert button does not raise a second invoice', function () {
    Mail::fake();
    $fx = acceptedQuote(120.0);

    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => ['manage_quotes']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    test()->actingAs($admin, 'admin')->post(route('admin.quotes.convert', $fx['quote']))->assertRedirect();
    test()->actingAs($admin, 'admin')->post(route('admin.quotes.convert', $fx['quote']->fresh()))->assertRedirect();

    expect(Invoice::where('client_id', $fx['client']->id)->count())->toBe(1);
});

test('a quote whose invoice was cancelled can be raised again', function () {
    Mail::fake();
    $fx = acceptedQuote(120.0);

    $service = app(QuoteService::class);
    $first = $service->convertToInvoice($fx['quote']);

    app(InvoiceService::class)->cancelInvoice($first);

    $second = $service->convertToInvoice($fx['quote']->fresh());

    expect($second->id)->not->toBe($first->id);
});

test('two different quotes still make two invoices', function () {
    Mail::fake();
    $a = acceptedQuote(120.0);
    $b = acceptedQuote(80.0);

    $service = app(QuoteService::class);

    $first = $service->convertToInvoice($a['quote']);
    $second = $service->convertToInvoice($b['quote']);

    expect($second->id)->not->toBe($first->id);
});
