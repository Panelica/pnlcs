<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteService;


test('quote can be created with factory', function () {
    $quote = Quote::factory()->create();
    expect($quote)->toBeInstanceOf(Quote::class)
        ->and($quote->status)->toBe('Draft');
});

test('quote factory sent state works', function () {
    $quote = Quote::factory()->sent()->create();
    expect($quote->status)->toBe('Sent');
});

test('quote factory accepted state works', function () {
    $quote = Quote::factory()->accepted()->create();
    expect($quote->status)->toBe('Accepted');
});

test('quote service creates quote with items', function () {
    $client = Client::factory()->create();
    $service = new QuoteService();
    $quote = $service->createQuote($client, [
        'subject'     => 'Test Quote',
        'date'        => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'items'       => [
            ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 50, 'discount' => 0, 'taxable' => false],
            ['description' => 'Item 2', 'quantity' => 1, 'unit_price' => 25, 'discount' => 5, 'taxable' => false],
        ],
    ]);

    expect($quote->items)->toHaveCount(2)
        ->and((float)$quote->subtotal)->toBe(120.0)  // 2*50 + (1*25-5)
        ->and((float)$quote->total)->toBe(120.0);
});

test('quote service recalculates totals', function () {
    $client = Client::factory()->create();
    $service = new QuoteService();
    $quote = $service->createQuote($client, [
        'subject'     => 'Recalc Test',
        'date'        => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
    ]);
    $service->addItem($quote, ['description' => 'Item A', 'quantity' => 3, 'unit_price' => 10, 'discount' => 0, 'taxable' => false]);
    $quote->refresh();
    expect((float)$quote->subtotal)->toBe(30.0)
        ->and((float)$quote->total)->toBe(30.0);
});

test('quote service send updates status', function () {
    $quote = Quote::factory()->create(['status' => 'Draft']);
    $service = new QuoteService();
    $result = $service->sendQuote($quote);
    expect($result->status)->toBe('Sent');
});

test('quote service accept updates status', function () {
    $quote = Quote::factory()->create(['status' => 'Sent']);
    $service = new QuoteService();
    $result = $service->acceptQuote($quote);
    expect($result->status)->toBe('Accepted');
});

test('quote service decline updates status', function () {
    $quote = Quote::factory()->create(['status' => 'Sent']);
    $service = new QuoteService();
    $result = $service->declineQuote($quote);
    expect($result->status)->toBe('Declined');
});

test('quote service converts to invoice', function () {
    $client = Client::factory()->create();
    $service = new QuoteService();
    $quote = $service->createQuote($client, [
        'subject'     => 'Convert Test',
        'date'        => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'items'       => [
            ['description' => 'Web Design', 'quantity' => 1, 'unit_price' => 500, 'discount' => 0, 'taxable' => false],
        ],
    ]);
    $invoice = $service->convertToInvoice($quote);
    $quote->refresh();

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->items)->toHaveCount(1)
        ->and((float)$invoice->total)->toBe(500.0)
        ->and($quote->status)->toBe('Accepted');
});

test('quote service delete removes quote and items', function () {
    $client = Client::factory()->create();
    $service = new QuoteService();
    $quote = $service->createQuote($client, [
        'subject'     => 'Delete Test',
        'date'        => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'items'       => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'taxable' => false]],
    ]);
    $quoteId = $quote->id;
    $service->deleteQuote($quote);

    expect(Quote::find($quoteId))->toBeNull()
        ->and(QuoteItem::where('quote_id', $quoteId)->count())->toBe(0);
});

test('admin can access quotes index', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.quotes.index'))
         ->assertStatus(200);
});

test('admin can access quotes create form', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.quotes.create'))
         ->assertStatus(200);
});

test('admin can view a quote', function () {
    $admin = Admin::factory()->create();
    $quote = Quote::factory()->create(['subject' => 'Test Quote Subject']);
    $this->actingAs($admin, 'admin')
         ->get(route('admin.quotes.show', $quote))
         ->assertStatus(200)
         ;
});

test('admin can create a quote via form', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $response = $this->actingAs($admin, 'admin')
         ->post(route('admin.quotes.store'), [
             'client_id'   => $client->id,
             'subject'     => 'New Quote From Form',
             'date'        => now()->toDateString(),
             'valid_until' => now()->addDays(30)->toDateString(),
         ]);
    $response->assertRedirect();
    expect(Quote::where('subject', 'New Quote From Form')->exists())->toBeTrue();
});

test('admin can edit a quote', function () {
    $admin = Admin::factory()->create();
    $quote = Quote::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.quotes.edit', $quote))
         ->assertStatus(200);
});

test('admin can send a quote', function () {
    $admin = Admin::factory()->create();
    $quote = Quote::factory()->create(['status' => 'Draft']);
    $this->actingAs($admin, 'admin')
         ->post(route('admin.quotes.send', $quote))
         ->assertRedirect(route('admin.quotes.show', $quote));
    expect($quote->fresh()->status)->toBe('Sent');
});
