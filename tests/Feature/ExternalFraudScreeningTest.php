<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Fraud\FraudLabsProClient;
use App\Services\Fraud\MaxMindMinFraudClient;
use App\Services\FraudDetectionService;
use Illuminate\Support\Facades\Http;

/**
 * MaxMind minFraud and FraudLabs Pro, held to the published wire contracts:
 * minFraud is POST minfraud/v2.0/score with Basic auth and device.ip_address,
 * answering risk_score/id/disposition; FraudLabs is POST v2/order/screen with
 * the key in the body, answering fraudlabspro_score/status/id. Both are
 * advisory - an outage, a bad key, or a missing IP never blocks an order.
 */
function fraudOrder(array $attrs = []): Order
{
    $client = Client::factory()->create(['email' => 'screened@example.com', 'country' => 'TR']);

    return Order::create(array_merge([
        'order_num' => 'FR-'.uniqid(),
        'client_id' => $client->id,
        'date' => now(),
        'amount' => 49.90,
        'status' => 'pending',
        'ip_address' => '203.0.113.7',
    ], $attrs));
}

function enableMaxMind(): void
{
    Setting::set('MaxMindEnabled', '1');
    Setting::set('MaxMindAccountId', '123456');
    Setting::set('MaxMindLicenseKey', 'test_license_key');
}

function enableFraudLabs(): void
{
    Setting::set('FraudLabsEnabled', '1');
    Setting::set('FraudLabsApiKey', 'test_fraudlabs_key');
}

it('sends minFraud exactly what the spec asks for', function () {
    Http::fake([MaxMindMinFraudClient::ENDPOINT => Http::response(['risk_score' => 0.5, 'id' => 'uuid-1'], 200)]);
    enableMaxMind();
    $order = fraudOrder();

    app(MaxMindMinFraudClient::class)->score($order);

    Http::assertSent(function ($request) {
        return $request->url() === MaxMindMinFraudClient::ENDPOINT
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('123456:test_license_key'))
            && $request['device']['ip_address'] === '203.0.113.7'
            && $request['email']['address'] === 'screened@example.com'
            && $request['billing']['country'] === 'TR'
            && $request['order']['amount'] === 49.9;
    });
});

it('maps a high minFraud risk_score onto the combined score', function () {
    Http::fake([MaxMindMinFraudClient::ENDPOINT => Http::response([
        'risk_score' => 87.25, 'id' => 'uuid-2', 'disposition' => ['action' => 'reject'],
    ], 200)]);
    enableMaxMind();

    $fraud = app(FraudDetectionService::class)->evaluate(fraudOrder());

    expect($fraud['score'])->toBe(87)
        ->and($fraud['module'])->toBe('maxmind')
        ->and(implode(' ', $fraud['reasons']))->toContain('MaxMind minFraud risk score 87 (reject)');
});

it('sends FraudLabs the key in the body as the spec asks', function () {
    Http::fake([FraudLabsProClient::ENDPOINT => Http::response(['fraudlabspro_score' => 10, 'fraudlabspro_status' => 'APPROVE'], 200)]);
    enableFraudLabs();

    app(FraudLabsProClient::class)->score(fraudOrder());

    Http::assertSent(function ($request) {
        return $request->url() === FraudLabsProClient::ENDPOINT
            && $request->method() === 'POST'
            && $request['key'] === 'test_fraudlabs_key'
            && $request['format'] === 'json'
            && $request['ip'] === '203.0.113.7'
            && $request['email'] === 'screened@example.com';
    });
});

it('lets a FraudLabs REJECT verdict outrank its own number', function () {
    Http::fake([FraudLabsProClient::ENDPOINT => Http::response([
        'fraudlabspro_score' => 41, 'fraudlabspro_status' => 'REJECT', 'fraudlabspro_id' => 'flp-1',
    ], 200)]);
    enableFraudLabs();

    $fraud = app(FraudDetectionService::class)->evaluate(fraudOrder());

    expect($fraud['score'])->toBe(90)->and($fraud['module'])->toBe('fraudlabs');
});

it('holds a REVIEW verdict at the fraud threshold instead of letting it slip through', function () {
    Http::fake([FraudLabsProClient::ENDPOINT => Http::response([
        'fraudlabspro_score' => 35, 'fraudlabspro_status' => 'REVIEW',
    ], 200)]);
    enableFraudLabs();

    expect(app(FraudDetectionService::class)->evaluate(fraudOrder())['score'])->toBe(60);
});

it('does not let two mild signals add up to a rejection', function () {
    Http::fake([
        MaxMindMinFraudClient::ENDPOINT => Http::response(['risk_score' => 40], 200),
        FraudLabsProClient::ENDPOINT => Http::response(['fraudlabspro_score' => 45, 'fraudlabspro_status' => 'APPROVE'], 200),
    ]);
    enableMaxMind();
    enableFraudLabs();

    // Worst signal, not a sum: 45, never 85.
    expect(app(FraudDetectionService::class)->evaluate(fraudOrder())['score'])->toBe(45);
});

it('calls nobody when screening is not configured', function () {
    Http::fake();

    $fraud = app(FraudDetectionService::class)->evaluate(fraudOrder());

    expect($fraud['module'])->toBe('internal');
    Http::assertNothingSent();
});

it('shrugs off a provider outage and keeps the local score', function () {
    Http::fake([
        MaxMindMinFraudClient::ENDPOINT => Http::response(['code' => 'AUTHORIZATION_INVALID', 'error' => 'bad key'], 401),
        FraudLabsProClient::ENDPOINT => Http::response('', 500),
    ]);
    enableMaxMind();
    enableFraudLabs();

    $fraud = app(FraudDetectionService::class)->evaluate(fraudOrder());

    expect($fraud['score'])->toBe(0)->and($fraud['module'])->toBe('internal');
});

it('makes no request for an order without a usable IP', function () {
    Http::fake();
    enableMaxMind();
    enableFraudLabs();

    app(FraudDetectionService::class)->evaluate(fraudOrder(['ip_address' => '0.0.0.0']));

    Http::assertNothingSent();
});

it('names the external screener on the held order', function () {
    Http::fake([MaxMindMinFraudClient::ENDPOINT => Http::response(['risk_score' => 95], 200)]);
    enableMaxMind();
    $order = fraudOrder();

    $fraud = app(FraudDetectionService::class)->evaluate($order);
    app(\App\Services\OrderService::class)->markFraud(
        $order, $fraud['module'], 'Held by fraud screening (score '.$fraud['score'].'): '.implode('; ', $fraud['reasons'])
    );

    $order->refresh();
    expect($order->status->value ?? $order->status)->toBe('fraud')
        ->and($order->fraud_module)->toBe('maxmind')
        ->and($order->fraud_output)->toContain('MaxMind minFraud risk score 95');
});

it('offers the fraud screening fields on the settings screen and keeps blank secrets', function () {
    Setting::set('MaxMindLicenseKey', 'existing_secret');

    $admin = App\Models\Admin::factory()->create();
    $html = $this->actingAs($admin, 'admin')
        ->get(route('admin.settings.general'))->assertOk()->getContent();
    expect($html)->toContain('name="MaxMindEnabled"')->toContain('name="MaxMindLicenseKey"')
        ->toContain('name="FraudLabsEnabled"')->toContain('name="FraudLabsApiKey"')
        ->not->toContain('existing_secret');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'CompanyName' => 'Test Co',
        'MaxMindEnabled' => '1',
        'MaxMindAccountId' => '99',
        'MaxMindLicenseKey' => '',
    ])->assertRedirect();

    expect(Setting::get('MaxMindLicenseKey'))->toBe('existing_secret')
        ->and((int) Setting::get('MaxMindEnabled'))->toBe(1);
});
