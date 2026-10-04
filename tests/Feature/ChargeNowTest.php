<?php

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\InvoiceStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\AutoChargeService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;

/*
 * Staff charge a stored card for one invoice, now.
 *
 * Collection by card only ran on its schedule. When a customer said "take it
 * from my card" on the phone, or replaced a card after a refusal, staff could
 * only wait for the next morning - or for the retry date, days away.
 */

beforeEach(fn () => Sleep::fake());
afterEach(fn () => Sleep::fake(false));

function cnGateway(?callable $responder = null): ArrayObject
{
    $calls = new ArrayObject;
    $responder ??= fn ($invoice, $method, $amount) => ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];

    $fake = Mockery::mock(GatewayModuleInterface::class.', '.TokenizableGatewayInterface::class);
    $fake->shouldReceive('getModuleName')->andReturn('fake');
    $fake->shouldReceive('isTokenised')->andReturn(true);
    $fake->shouldReceive('getConfigFields')->andReturn([]);
    $fake->shouldReceive('chargeStoredMethod')->andReturnUsing(function ($invoice, $method, $amount) use ($calls, $responder) {
        $calls[] = ['invoice' => $invoice->id, 'amount' => round((float) $amount, 2)];

        return $responder($invoice, $method, round((float) $amount, 2), count($calls));
    });

    app()->instance(GatewayModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerGateway('stripe', GatewayModuleInterface::class);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);

    return $calls;
}

/** An invoice due well outside the charging window, with one stored card. */
function cnReady(float $total = 80.0): Invoice
{
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Unpaid->value, 'subtotal' => $total, 'total' => $total, 'due_date' => now()->addDays(20)]);
    PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'stripe', 'payment_type' => 'cc', 'description' => 'Visa 4242',
        'remote_token' => 'pm_'.uniqid(), 'gateway_customer_id' => 'cus_'.uniqid(), 'last_four' => '4242', 'expiry_date' => '2030-07',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);

    return $invoice;
}

function cnAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['view_invoices', 'manage_invoices']])->id]);
}

test('staff charge the card from the invoice, outside the window, and it is recorded once', function () {
    Setting::set('AutoChargeEnabled', '1');
    $calls = cnGateway();
    $invoice = cnReady(80.0);
    $admin = cnAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.invoices.show', $invoice))->assertOk()
        ->assertSee(route('admin.invoices.charge-now', $invoice), false);

    test()->actingAs($admin, 'admin')->post(route('admin.invoices.charge-now', $invoice))
        ->assertSessionHas('success', __('admin.invoices.charge_now_charged'));
    test()->actingAs($admin, 'admin')->post(route('admin.invoices.charge-now', $invoice))
        ->assertSessionHas('error');

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('a retry scheduled for later is made now and counts as an attempt; a used-up card is not presented', function () {
    Setting::set('AutoChargeEnabled', '1');
    Setting::set('AutoChargeMaxAttempts', '2');
    $calls = cnGateway(fn () => ['success' => false, 'status' => 'failed', 'message' => 'Insufficient funds.', 'decline_code' => 'insufficient_funds', 'retryable' => true]);
    $invoice = cnReady();

    expect(app(AutoChargeService::class)->chargeNow($invoice))->toBe('failed');
    $row = Attempt::forInvoice($invoice);
    expect($row->state)->toBe(ChargeAttemptState::Scheduled)->and($row->next_attempt_at->isFuture())->toBeTrue();

    expect(app(AutoChargeService::class)->chargeNow($invoice))->toBe('failed')
        ->and(Attempt::forInvoice($invoice)->attempts)->toBe(2);

    expect(app(AutoChargeService::class)->chargeNow($invoice))->toBe('closed')
        ->and($calls->count())->toBe(2);
});

test('a card waiting on the cardholder is not asked again', function () {
    Setting::set('AutoChargeEnabled', '1');
    $calls = cnGateway(fn () => ['success' => false, 'status' => 'requires_action', 'transaction_id' => 'pi_auth', 'message' => 'Authentication required.']);
    $invoice = cnReady();

    expect(app(AutoChargeService::class)->chargeNow($invoice))->toBe('action_required')
        ->and(app(AutoChargeService::class)->chargeNow($invoice))->toBe('closed')
        ->and($calls->count())->toBe(1);
});

test('it charges nothing while collection is off, for an order invoice, or for a customer who opted out', function () {
    $calls = cnGateway();
    $invoice = cnReady();

    expect(app(AutoChargeService::class)->chargeNow($invoice))->toBe('switched_off');
    $html = test()->actingAs(cnAdmin(), 'admin')->get(route('admin.invoices.show', $invoice))->getContent();
    expect($html)->not->toContain(route('admin.invoices.charge-now', $invoice));

    Setting::set('AutoChargeEnabled', '1');
    Order::factory()->create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id]);
    expect(app(AutoChargeService::class)->chargeNow($invoice))->toBe('not_a_card_bill');

    $other = cnReady();
    $other->client->update(['auto_charge' => false]);
    expect(app(AutoChargeService::class)->chargeNow($other->fresh()))->toBe('customer_has_opted_out')
        ->and($calls->count())->toBe(0);
});

test('the scheduled run still waits for the retry date', function () {
    Setting::set('AutoChargeEnabled', '1');
    $calls = cnGateway(fn () => ['success' => false, 'status' => 'failed', 'message' => 'Insufficient funds.', 'decline_code' => 'insufficient_funds', 'retryable' => true]);
    $invoice = cnReady();
    $invoice->update(['due_date' => now()->addDay()]);

    Artisan::call('pnlcs:auto-charge');
    test()->travel(1)->days();
    Artisan::call('pnlcs:auto-charge');

    expect($calls->count())->toBe(1);
});
