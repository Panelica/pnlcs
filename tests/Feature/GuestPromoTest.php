<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Promotion;

/**
 * A new-signups-only code exists for people who are not customers yet - and a
 * guest cart refused it for exactly those people, because "no client" was
 * treated as failing every client rule. The cart check is only provisional:
 * the order itself re-validates against the real client at placement, so a
 * guest may carry the code to checkout and the final gate still decides.
 */
it('lets a guest carry a new-signups-only code to checkout', function () {
    $promo = Promotion::create(['code' => 'WELCOME', 'type' => 'percentage', 'value' => 20, 'new_signups_only' => true]);

    expect($promo->isValidFor(null))->toBeTrue();
});

it('lets a guest carry a one-per-customer code, judged properly at the order', function () {
    $promo = Promotion::create(['code' => 'ONCE', 'type' => 'percentage', 'value' => 20, 'apply_once' => true]);

    expect($promo->isValidFor(null))->toBeTrue();
});

it('still refuses an existing-customers-only code to a guest', function () {
    $promo = Promotion::create(['code' => 'LOYAL', 'type' => 'percentage', 'value' => 20, 'existing_client' => true]);

    expect($promo->isValidFor(null))->toBeFalse();
});

it('still refuses the new-signups code to a client with an order, at the final gate', function () {
    $promo = Promotion::create(['code' => 'WELCOME2', 'type' => 'percentage', 'value' => 20, 'new_signups_only' => true]);
    $client = Client::factory()->create();
    Order::create(['order_num' => 'O-'.uniqid(), 'client_id' => $client->id, 'date' => now(), 'amount' => 5, 'status' => 'active', 'ip_address' => '203.0.113.9']);

    expect($promo->isValidFor($client))->toBeFalse();
});
