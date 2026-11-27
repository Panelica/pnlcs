<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentNotification;
use App\Models\Transaction;
use App\Services\PaymentService;

it('refuses a payment with require_payable on a settled invoice', function () {
    $client = Client::factory()->create(['credit' => 0]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'paid',
        'total' => 100,
    ]);

    $result = app(PaymentService::class)->applyPayment(
        $invoice,
        'banktransfer',
        'TEST-require-payable',
        100,
        ['require_payable' => true]
    );

    expect($result['success'])->toBeFalse()
        ->and($result['not_payable'])->toBeTrue()
        ->and(Transaction::count())->toBe(0)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});

it('refuses a booking when the expected invoice balance changed', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 100,
    ]);

    $result = app(PaymentService::class)->applyPayment(
        $invoice,
        'banktransfer',
        'TEST-balance-changed',
        100,
        ['expected_balance' => 50]
    );

    expect($result['success'])->toBeFalse()
        ->and($result['balance_changed'])->toBeTrue()
        ->and(Transaction::count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe('unpaid');
});

it('uses the shared guard when an admin approves a payment notification', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 100,
    ]);
    $notification = PaymentNotification::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'gateway' => 'banktransfer',
        'sender_name' => 'Payer',
        'amount' => 100,
        'transfer_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $payments = Mockery::mock(PaymentService::class);
    $payments->shouldReceive('balance')->once()->andReturn(100.0);
    $payments->shouldReceive('applyPayment')
        ->once()
        ->withArgs(fn ($invoiceArg, $gateway, $reference, $amount, $options): bool => $options['require_payable'] === true)
        ->andReturn(['success' => true, 'status' => 'paid', 'balance' => 0.0]);

    app()->instance(PaymentService::class, $payments);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.payment-notifications.approve', $notification), ['amount' => 100])
        ->assertSessionHas('success');

    expect($notification->fresh()->status)->toBe('approved');
});
