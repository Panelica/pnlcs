<?php

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Sleep;
use Modules\Gateways\Stripe\StripeModule;

/*
 * A customer with a stored card could not pay an invoice with it. The invoice
 * page offered credit and the gateway forms, so the card had to be typed in
 * again, or the customer waited for the next morning's automatic payment.
 * The page now lists the customer's stored cards, each with a button that
 * charges it the way automatic payment does.
 */

beforeEach(fn () => Sleep::fake());
afterEach(fn () => Sleep::fake(false));

function pwcGateway(?callable $responder = null): ArrayObject
{
    $calls = new ArrayObject;
    $responder ??= fn ($invoice, $method, $amount) => ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id.'_'.$method->id, 'amount' => $amount];

    $fake = Mockery::mock(GatewayModuleInterface::class.', '.TokenizableGatewayInterface::class);
    $fake->shouldReceive('getModuleName')->andReturn('fake');
    $fake->shouldReceive('isTokenised')->andReturn(true);
    $fake->shouldReceive('getConfigFields')->andReturn([]);
    $fake->shouldReceive('getPaymentForm')->andReturn('<div>card form</div>');
    $fake->shouldReceive('chargeStoredMethod')->andReturnUsing(function ($invoice, $method, $amount) use ($calls, $responder) {
        $calls[] = ['invoice' => $invoice->id, 'method' => $method->id, 'amount' => round((float) $amount, 2)];

        return $responder($invoice, $method, round((float) $amount, 2));
    });

    app()->instance(GatewayModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerGateway('stripe', GatewayModuleInterface::class);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);
    Setting::set('AutoChargeEnabled', '1');

    return $calls;
}

function pwcCard(Client $client, string $last4, bool $default = false): PaymentMethod
{
    return PaymentMethod::create([
        'client_id' => $client->id, 'gateway_name' => 'stripe', 'payment_type' => 'cc', 'description' => 'Visa '.$last4,
        'remote_token' => 'pm_'.uniqid(), 'gateway_customer_id' => 'cus_'.uniqid(), 'last_four' => $last4, 'card_brand' => 'visa',
        'expiry_date' => '2030-07', 'status' => PaymentMethod::STATUS_ACTIVE, 'is_default' => $default,
    ]);
}

/** @return array{0: User, 1: Client, 2: Invoice} */
function pwcCustomer(array $client = []): array
{
    $user = User::factory()->create();
    $c = Client::factory()->create($client + ['auto_charge' => true]);
    $user->clients()->attach($c->id);
    $invoice = Invoice::factory()->create(['client_id' => $c->id, 'status' => InvoiceStatus::Unpaid->value, 'subtotal' => 40, 'total' => 40, 'due_date' => now()->addDays(20)]);

    return [$user, $c, $invoice];
}

test('the invoice offers each stored card and charges the one the customer picks, once', function () {
    $calls = pwcGateway();
    [$user, $client, $invoice] = pwcCustomer();
    pwcCard($client, '4242', default: true);
    $other = pwcCard($client, '1881');

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()
        ->assertSee(route('client.invoices.pay-with-card', $invoice), false)
        ->assertSee('•••• 4242')->assertSee('•••• 1881');

    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $other->id])
        ->assertRedirect(route('client.invoices.show', $invoice))
        ->assertSessionHas('success', __('client.invoices.card_payment_taken'));
    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $other->id]);

    expect($calls->count())->toBe(1)
        ->and($calls[0]['method'])->toBe($other->id)
        ->and($calls[0]['amount'])->toBe(40.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('a customer who switched automatic payment off can still pay with a card they choose', function () {
    $calls = pwcGateway();
    [$user, $client, $invoice] = pwcCustomer(['auto_charge' => false]);
    $card = pwcCard($client, '4242', default: true);

    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $card->id])
        ->assertSessionHas('success');

    expect($calls->count())->toBe(1)->and((bool) $client->fresh()->auto_charge)->toBeFalse();
});

test('an order\'s invoice can be paid with a stored card', function () {
    $calls = pwcGateway();
    [$user, $client, $invoice] = pwcCustomer();
    Order::factory()->create(['client_id' => $client->id, 'invoice_id' => $invoice->id]);
    $card = pwcCard($client, '4242', default: true);

    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $card->id])
        ->assertSessionHas('success');

    expect($calls->count())->toBe(1);
});

test('a bank that wants the cardholder leaves the invoice waiting for them on the page', function () {
    $calls = pwcGateway(fn () => ['success' => false, 'status' => 'requires_action', 'transaction_id' => 'pi_auth', 'message' => 'Authentication required.']);
    [$user, $client, $invoice] = pwcCustomer();
    $card = pwcCard($client, '4242', default: true);

    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $card->id])
        ->assertSessionHas('info', __('client.invoices.card_payment_needs_you'));

    expect(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::ActionRequired);
    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertSee('pn-authenticate-btn', false);
});

test('a refusal says so and leaves the invoice open', function () {
    pwcGateway(fn () => ['success' => false, 'status' => 'failed', 'message' => 'Insufficient funds.', 'decline_code' => 'insufficient_funds', 'retryable' => true]);
    [$user, $client, $invoice] = pwcCustomer();
    $card = pwcCard($client, '4242', default: true);

    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $card->id])
        ->assertSessionHas('error', __('client.invoices.card_payment_refused'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('another customer\'s card, a removed card and an Add Funds invoice are not charged', function () {
    $calls = pwcGateway();
    [$user, $client, $invoice] = pwcCustomer();
    [, $stranger] = pwcCustomer();
    $theirs = pwcCard($stranger, '9999', default: true);
    $removed = pwcCard($client, '1111');
    $removed->delete();

    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $theirs->id])->assertNotFound();
    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $removed->id])->assertNotFound();

    $mine = pwcCard($client, '4242', default: true);
    $funds = Invoice::factory()->create(['client_id' => $client->id, 'status' => InvoiceStatus::Unpaid->value, 'subtotal' => 50, 'total' => 50]);
    InvoiceItem::create(['invoice_id' => $funds->id, 'client_id' => $client->id, 'type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add funds', 'amount' => 50, 'taxed' => false]);

    $this->actingAs($user)->get(route('client.invoices.show', $funds))->assertDontSee(route('client.invoices.pay-with-card', $funds), false);
    $this->actingAs($user)->post(route('client.invoices.pay-with-card', $funds), ['payment_method_id' => $mine->id])->assertSessionHas('error');

    expect($calls->count())->toBe(0);
});

test('with automatic payment off the page offers no stored card', function () {
    pwcGateway();
    Setting::set('AutoChargeEnabled', '0');
    [$user, $client, $invoice] = pwcCustomer();
    pwcCard($client, '4242', default: true);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()
        ->assertDontSee(route('client.invoices.pay-with-card', $invoice), false);
});

test('a customer who keeps a card is told a new one is kept beside it', function () {
    Setting::set('AutoChargeEnabled', '1');
    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $k], ['value' => $v]);
    }
    [, $client, $invoice] = pwcCustomer();

    expect(app(StripeModule::class)->getPaymentForm($invoice))->toContain(e(__('messages.stripe.save_card')));

    pwcCard($client, '4242', default: true);
    expect(app(StripeModule::class)->getPaymentForm($invoice))->toContain(e(__('messages.stripe.save_card_new')));
});

/*
 * Staff signed in as the customer (Impersonation) are not offered the stored
 * cards. A customer paying with a card they picked is not asked about
 * automatic payment, so from such a session staff could have charged the card
 * of a customer who had switched automatic payment off, recorded as the
 * customer's own payment. Charge now in the admin area charges a stored card
 * on staff's initiative and keeps to that choice.
 */
test('staff signed in as the customer are offered no stored card and cannot charge one', function () {
    $calls = pwcGateway();
    [$user, $client, $invoice] = pwcCustomer(['auto_charge' => false]);
    $card = pwcCard($client, '4242', default: true);
    $staff = fn () => $this->actingAs($user)->withSession(['impersonating_admin_id' => 1, 'impersonating_admin_name' => 'Staff']);

    $staff()->get(route('client.invoices.show', $invoice))->assertOk()
        ->assertDontSee(route('client.invoices.pay-with-card', $invoice), false);

    $staff()->post(route('client.invoices.pay-with-card', $invoice), ['payment_method_id' => $card->id])
        ->assertRedirect(route('client.invoices.show', $invoice))
        ->assertSessionHas('error', __('client.invoices.card_payment_staff'));

    expect($calls->count())->toBe(0)
        ->and(Attempt::where('invoice_id', $invoice->id)->exists())->toBeFalse()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});
