<?php

use App\Contracts\SslModuleInterface;
use App\Models\Client;
use App\Models\SslOrder;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use App\Services\SslProvisioningService;

/**
 * Buying the same certificate twice.
 *
 * Configuring a certificate submits it to the certificate authority - a real
 * purchase. The form refuses to open once the order has been configured, and
 * the handler behind it checks nothing at all: a resubmitted form, a
 * double-click, a browser replay or the API endpoint runs the purchase again.
 *
 * The second one costs the operator another certificate, leaves a duplicate
 * order at the authority, and overwrites the CSR and contact details recorded
 * against a certificate that has already been issued.
 */
function countingSslModule(string $name = 'countingssl'): ArrayObject
{
    $calls = new ArrayObject;

    $fake = Mockery::mock(SslModuleInterface::class);
    $fake->shouldReceive('purchaseCertificate')->andReturnUsing(function ($order, $config) use ($calls) {
        $calls[] = $order->id;

        return ['success' => true, 'message' => 'ordered'];
    });
    $fake->shouldReceive('decodeCsr')->andReturn(['data' => ['cn' => 'example.test']]);
    $fake->shouldReceive('getWebServerTypes')->andReturn(['apache' => 'Apache']);
    $fake->shouldReceive('getApproverEmails')->andReturn(['admin@example.test']);

    app()->instance(SslModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerSsl($name, SslModuleInterface::class);

    return $calls;
}

function sslOrderAwaiting(string $module = 'countingssl'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $order = SslOrder::create([
        'client_id' => $client->id,
        'domain' => 'example.test',
        'module' => $module,
        'status' => 'Awaiting Configuration',
        'order_date' => now(),
    ]);

    return [$user, $order];
}

function sslConfigPayload(): array
{
    return [
        'csr' => str_repeat('A', 120),
        'webserver_type' => 'apache',
        'validation_method' => 'EMAIL',
        'approver_email' => 'admin@example.test',
        'admin_first_name' => 'Ada',
        'admin_last_name' => 'Lovelace',
        'admin_email' => 'ada@example.test',
    ];
}

it('does not buy the certificate a second time', function () {
    $calls = countingSslModule();
    [$user, $order] = sslOrderAwaiting();

    $service = app(SslProvisioningService::class);

    $first = $service->submitConfiguration($order, sslConfigPayload());
    $second = $service->submitConfiguration($order->fresh(), sslConfigPayload());

    expect($first['success'])->toBeTrue();
    expect($second['success'])->toBeFalse();
    expect($calls->count())->toBe(1);
});

it('does not let a resubmitted form buy it again', function () {
    $calls = countingSslModule();
    [$user, $order] = sslOrderAwaiting();

    test()->actingAs($user)->post(route('client.ssl.submitConfiguration', $order), sslConfigPayload());
    test()->actingAs($user)->post(route('client.ssl.submitConfiguration', $order->fresh()), sslConfigPayload());

    expect($calls->count())->toBe(1);
});

it('leaves the details of an order that is already submitted alone', function () {
    countingSslModule();
    [$user, $order] = sslOrderAwaiting();

    $service = app(SslProvisioningService::class);
    $service->submitConfiguration($order, sslConfigPayload());

    // array_merge, not +: the union operator keeps the key that is already
    // there, so the overwrite this test is about would never have happened.
    $service->submitConfiguration($order->fresh(), array_merge(sslConfigPayload(), ['admin_email' => 'someone-else@example.test']));

    expect($order->fresh()->admin_email)->toBe('ada@example.test');
});

it('still lets a first configuration through', function () {
    $calls = countingSslModule();
    [$user, $order] = sslOrderAwaiting();

    $result = app(SslProvisioningService::class)->submitConfiguration($order, sslConfigPayload());

    expect($result['success'])->toBeTrue();
    expect($calls->count())->toBe(1);
    expect($order->fresh()->status)->toBe('Configuration Submitted');
});
