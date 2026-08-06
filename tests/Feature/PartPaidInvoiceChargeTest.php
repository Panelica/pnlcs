<?php

use App\Contracts\GatewayModuleInterface;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use App\Services\PaymentService;

/**
 * Paying the rest of an invoice charges the whole of it.
 *
 * The invoice page works out what is still owed and shows it - "remaining
 * balance" - and every pay-now path then asks the gateway for the invoice
 * total instead. A customer who has paid half by bank transfer is shown 60
 * left to pay and their card is charged 100.
 *
 * The money is not lost - the overpayment becomes account credit - but they
 * were charged more than the page told them they owed, which is the sort of
 * thing a cardholder disputes.
 */
function partlyPaidInvoice(float $total = 100.0, float $paid = 40.0): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $total,
        'total' => $total,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => $total,
        'taxed' => false,
    ]);

    if ($paid > 0) {
        app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'PART-'.uniqid(), $paid);
    }

    return [$user, $invoice->fresh()];
}

/** A gateway that records what it was asked to charge. */
function capturingGateway(array $names): ArrayObject
{
    $charged = new ArrayObject;

    $fake = Mockery::mock(GatewayModuleInterface::class);
    $fake->shouldReceive('capture')->andReturnUsing(function ($invoice, $amount, $params = []) use ($charged) {
        $charged[] = round((float) $amount, 2);

        return ['success' => true, 'transaction_id' => 'TXN-'.count($charged)];
    });
    $fake->shouldReceive('getModuleName')->andReturn('fake');

    app()->instance(GatewayModuleInterface::class, $fake);

    foreach ($names as $name) {
        app(ModuleRegistry::class)->registerGateway($name, GatewayModuleInterface::class);
    }

    return $charged;
}

it('charges what is left on the invoice, not the whole of it', function () {
    $charged = capturingGateway(['authorize']);
    [$user, $invoice] = partlyPaidInvoice(100.0, 40.0);

    test()->actingAs($user)->postJson(route('gateway.authorize.capture', $invoice), ['opaque_data' => 'tok']);

    expect($charged->getArrayCopy())->toBe([60.0]);
});

it('asks stripe for what is left', function () {
    $charged = capturingGateway(['stripe']);
    [$user, $invoice] = partlyPaidInvoice(100.0, 40.0);

    test()->actingAs($user)->postJson(url("/gateway/stripe/intent/{$invoice->id}"));

    expect($charged->getArrayCopy())->toBe([60.0]);
});

it('asks razorpay for what is left', function () {
    $charged = capturingGateway(['razorpay']);
    [$user, $invoice] = partlyPaidInvoice(100.0, 40.0);

    test()->actingAs($user)->postJson(route('gateway.razorpay.capture', $invoice));

    expect($charged->getArrayCopy())->toBe([60.0]);
});

it('still charges the whole of an invoice nobody has paid towards', function () {
    $charged = capturingGateway(['authorize']);
    [$user, $invoice] = partlyPaidInvoice(100.0, 0.0);

    test()->actingAs($user)->postJson(route('gateway.authorize.capture', $invoice), ['opaque_data' => 'tok']);

    expect($charged->getArrayCopy())->toBe([100.0]);
});

it('puts what is left on the pay button as well', function () {
    [$user, $invoice] = partlyPaidInvoice(100.0, 40.0);

    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'publishable_key'],
        ['value' => 'pk_test_123']
    );

    $form = app(ModuleRegistry::class)->getGatewayModule('stripe')->getPaymentForm($invoice);

    expect($form)->toContain('60.00');
    expect($form)->not->toContain('100.00');
});
