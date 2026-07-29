<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * An API that reports success has to have done the work.
 *
 * Six endpoints returned a cheerful message and did nothing at all: domain
 * registration, transfer and renewal, invoice generation, and the three
 * payment-method calls. An integration calling any of them was told the job
 * was done.
 */
function honestApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

test('generating invoices through the API actually generates them', function () {
    Mail::fake();

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 40]
    );

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 40,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'api-genin.com',
        'auto_renew' => true,
    ]);

    $response = $this->postJson('/api/v1/geninvoices', [], honestApiHeaders());

    $response->assertOk();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1)
        ->and($response->json('generated'))->toBe(1);
});

test('renewing a domain through the API moves its dates', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><ApiResponse Status="OK"></ApiResponse>', 200)]);

    $domain = Domain::factory()->create([
        'domain' => 'api-renew.com',
        'registrar' => 'Namecheap',
        'status' => 'active',
        'expiry_date' => '2027-05-05',
        'next_due_date' => '2027-05-05',
        'recurring_amount' => 12,
    ]);

    $this->postJson('/api/v1/domainrenew', ['domainid' => $domain->id, 'years' => 1], honestApiHeaders())
        ->assertOk();

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2028-05-05');
});

test('an endpoint that cannot do the job says so instead of reporting success', function () {
    $refused = [];

    foreach ([
        'addpaymethod' => ['clientid' => 1],
        'updatepaymethod' => ['paymethodid' => 1],
        'deletepaymethod' => ['paymethodid' => 1],
    ] as $endpoint => $payload) {
        $response = $this->postJson('/api/v1/'.$endpoint, $payload, honestApiHeaders());

        if ($response->json('result') === 'success') {
            $refused[] = $endpoint.' still reports success';
        }
    }

    expect($refused)->toBe([]);
});

test('registering a domain through the API records one', function () {
    $client = Client::factory()->create();

    $this->postJson('/api/v1/domainregister', [
        'clientid' => $client->id,
        'domain' => 'api-register.com',
        'years' => 2,
    ], honestApiHeaders())->assertOk();

    $domain = Domain::where('domain', 'api-register.com')->first();

    expect($domain)->not->toBeNull()
        ->and($domain->client_id)->toBe($client->id)
        ->and($domain->registration_period)->toBe(2);
});
