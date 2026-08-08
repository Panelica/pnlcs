<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use Database\Factories\ApiCredentialFactory;

/**
 * The one field that decides whether a service is still billed.
 *
 * updateclientproduct copied status, next_due_date and billing_cycle straight
 * onto the record with nothing checked. services.status is not cast to the
 * ServiceStatus enum, so a typo in an integration - 'actve', 'Active ' with a
 * space - was written as-is, and from that moment the service matched none of
 * the queries that keep it alive: the invoice run bills services whose status
 * is 'active', the suspension run suspends those, the cancellation run looks
 * for active or suspended. The customer kept their hosting and stopped being
 * invoiced for it, and nothing anywhere said so.
 *
 * The billing cycle is the same kind of field - the checkout has always
 * restricted it to the six the pricing tables carry - and the due date is a
 * date column that was taking any string at all.
 */
function serviceApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function apiEditableService(): Service
{
    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id])->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'next_due_date' => today()->addMonth()->toDateString(),
        'domain' => 'shop.test',
    ]);
}

it('refuses a status no part of the panel knows', function () {
    $service = apiEditableService();

    $this->withHeaders(serviceApiHeaders())->postJson('/api/v1/updateclientproduct', [
        'serviceid' => $service->id,
        'status' => 'actve',
    ])->assertStatus(422);

    expect($service->fresh()->status)->toBe('active');
});

it('still accepts a status the panel uses', function () {
    $service = apiEditableService();

    $this->withHeaders(serviceApiHeaders())->postJson('/api/v1/updateclientproduct', [
        'serviceid' => $service->id,
        'status' => 'suspended',
    ])->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});

it('refuses a billing cycle the pricing tables do not carry', function () {
    $service = apiEditableService();

    $this->withHeaders(serviceApiHeaders())->postJson('/api/v1/updateclientproduct', [
        'serviceid' => $service->id,
        'billing_cycle' => 'fortnightly',
    ])->assertStatus(422);

    expect($service->fresh()->billing_cycle)->toBe('monthly');
});

it('refuses a due date that is not a date', function () {
    $service = apiEditableService();

    $this->withHeaders(serviceApiHeaders())->postJson('/api/v1/updateclientproduct', [
        'serviceid' => $service->id,
        'next_due_date' => 'whenever',
    ])->assertStatus(422);

    expect($service->fresh()->next_due_date->toDateString())->toBe(today()->addMonth()->toDateString());
});

it('still moves the due date when given a real one', function () {
    $service = apiEditableService();
    $when = today()->addMonths(3)->toDateString();

    $this->withHeaders(serviceApiHeaders())->postJson('/api/v1/updateclientproduct', [
        'serviceid' => $service->id,
        'next_due_date' => $when,
    ])->assertSuccessful();

    expect($service->fresh()->next_due_date->toDateString())->toBe($when);
});

it('still changes the fields it was always free to change', function () {
    $service = apiEditableService();

    $this->withHeaders(serviceApiHeaders())->postJson('/api/v1/updateclientproduct', [
        'serviceid' => $service->id,
        'domain' => 'newshop.test',
        'notes' => 'Moved from the old box.',
    ])->assertSuccessful();

    expect($service->fresh()->domain)->toBe('newshop.test')
        ->and($service->fresh()->notes)->toBe('Moved from the old box.');
});
