<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceGenerationService;

/*
 * The note on a renewal invoice is read by the customer, so it is written in
 * the customer's language. It used to be one hard-coded English sentence on
 * every invoice, in front of a Polish or Turkish buyer, and it is the only
 * text on the document that was not translated.
 */

function renewalNoteService(string $language): Service
{
    Currency::where('is_default', true)->exists()
        || Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create(['language' => $language, 'tax_exempt' => true])->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(10),
        'domain' => uniqid().'.example.com',
    ]);
}

it('writes the renewal note in the customer\'s language', function () {
    $service = renewalNoteService('pl');

    $invoice = app(InvoiceGenerationService::class)->generateForService($service);

    expect($invoice->notes)->toBe('Faktura odnowienia wygenerowana automatycznie.');
});

it('writes it in the customer\'s language, not the locale the run is in', function () {
    app()->setLocale('en');
    $service = renewalNoteService('tr');

    $invoice = app(InvoiceGenerationService::class)->generateForService($service);

    expect(app()->getLocale())->toBe('en')
        ->and($invoice->notes)->toBe('Otomatik oluşturulan yenileme faturası.');
});

it('falls back to English for a customer with no language set', function () {
    $service = renewalNoteService('en');

    $invoice = app(InvoiceGenerationService::class)->generateForService($service);

    expect($invoice->notes)->toBe('Auto-generated renewal invoice.');
});
