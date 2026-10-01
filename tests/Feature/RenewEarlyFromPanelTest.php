<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentService;

/*
 * A customer can renew a service or a domain ahead of time.
 *
 * Renewal invoices were only raised by the nightly run, close to the due
 * date. InvoiceGenerationService::generateForService() existed and nothing
 * called it, so a customer who wanted to pay ahead could not.
 */

function repCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function repService(Client $client, array $attrs = []): Service
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'pay_type' => 'recurring']);

    return Service::factory()->create(array_merge([
        'client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active',
        'amount' => 12.5, 'billing_cycle' => 'Monthly', 'next_due_date' => now()->addDays(40)->toDateString(),
    ], $attrs));
}

function repDomain(Client $client, array $attrs = []): Domain
{
    return Domain::create(array_merge([
        'client_id' => $client->id, 'domain' => 'renew-ahead.com', 'type' => 'Register', 'registrar' => '',
        'status' => 'active', 'registration_period' => 1,
        'expiry_date' => now()->addMonths(5)->toDateString(), 'next_due_date' => now()->addMonths(5)->toDateString(),
        'first_payment_amount' => 10, 'recurring_amount' => 15,
    ], $attrs));
}

it('raises the next period\'s invoice for a service and opens it', function () {
    [$user, $client] = repCustomer();
    $service = repService($client);

    $response = $this->actingAs($user)->post(route('client.services.renew', $service));

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();
    $response->assertRedirect(route('client.invoices.show', $invoice));
    expect($invoice->items()->where('type', 'Hosting')->where('rel_id', $service->id)->exists())->toBeTrue()
        ->and((float) $invoice->subtotal)->toBe(12.5);
});

it('sends a second click to the invoice already raised', function () {
    [$user, $client] = repCustomer();
    $service = repService($client);

    $this->actingAs($user)->post(route('client.services.renew', $service));
    $this->actingAs($user)->post(route('client.services.renew', $service));

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});

it('moves the due date on once the early invoice is paid', function () {
    [$user, $client] = repCustomer();
    $service = repService($client);
    $due = $service->next_due_date->copy();

    $this->actingAs($user)->post(route('client.services.renew', $service));
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-early', null);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($service->fresh()->next_due_date->toDateString())->toBe($due->copy()->addMonth()->toDateString());
});

it('raises a renewal invoice for a domain', function () {
    [$user, $client] = repCustomer();
    $domain = repDomain($client);

    $this->actingAs($user)->post(route('client.domains.renew', $domain))->assertRedirect();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();
    expect($invoice->items()->where('type', 'Domain')->where('rel_id', $domain->id)->exists())->toBeTrue()
        ->and((float) $invoice->subtotal)->toBe(15.0);
});

it('offers the button on an active service and domain only', function () {
    [$user, $client] = repCustomer();
    $active = repService($client);
    $cancelled = repService($client, ['status' => 'cancelled']);
    $domain = repDomain($client);

    $this->actingAs($user)->get(route('client.services.show', $active))->assertSee(route('client.services.renew', $active), false);
    $this->actingAs($user)->get(route('client.services.show', $cancelled))->assertDontSee(route('client.services.renew', $cancelled), false);
    $this->actingAs($user)->get(route('client.domains.show', $domain))->assertSee(route('client.domains.renew', $domain), false);
});

it('raises nothing for a cancelled service or another customer\'s one', function () {
    [$user, $client] = repCustomer();
    $cancelled = repService($client, ['status' => 'cancelled']);
    [, $other] = repCustomer();
    $theirs = repService($other);

    $this->actingAs($user)->post(route('client.services.renew', $cancelled))->assertRedirect();
    $this->actingAs($user)->post(route('client.services.renew', $theirs))->assertForbidden();

    expect(Invoice::count())->toBe(0);
});
