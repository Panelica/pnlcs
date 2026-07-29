<?php

use App\Models\GatewaySettings;
use App\Services\Module\ModuleRegistry;

/**
 * A signed Stripe webhook does not stay valid forever.
 *
 * The timestamp is part of what Stripe signs, so a caller cannot alter it —
 * but without a recency check an intercepted call can be replayed at any time
 * and still verify. Stripe's own libraries reject anything more than five
 * minutes old, and their documentation is explicit that skipping the check is
 * the same as a tolerance of zero.
 */
function stripeWebhook(array $payload, string $secret, int $timestamp): array
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return array_merge($payload, [
        '_raw_payload' => $body,
        '_signature_header' => "t={$timestamp},v1={$signature}",
    ]);
}

function stripeConfigured(string $secret = 'whsec_test'): object
{
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'webhook_secret'],
        ['value' => $secret]
    );

    return app(ModuleRegistry::class)->getGatewayModule('stripe');
}

$event = [
    'type' => 'payment_intent.succeeded',
    'data' => ['object' => ['id' => 'pi_123', 'metadata' => ['invoice_id' => '1'], 'amount_received' => 2500]],
];

test('a webhook signed just now is accepted', function () use ($event) {
    $module = stripeConfigured();

    $result = $module->processWebhook(stripeWebhook($event, 'whsec_test', time()));

    expect($result['success'])->toBeTrue()
        ->and($result['transaction_id'])->toBe('pi_123');
});

test('a webhook replayed an hour later is refused', function () use ($event) {
    $module = stripeConfigured();

    $result = $module->processWebhook(stripeWebhook($event, 'whsec_test', time() - 3600));

    expect($result['success'])->toBeFalse();
});

test('a webhook signed with the wrong secret is refused', function () use ($event) {
    $module = stripeConfigured();

    $result = $module->processWebhook(stripeWebhook($event, 'whsec_wrong', time()));

    expect($result['success'])->toBeFalse();
});
