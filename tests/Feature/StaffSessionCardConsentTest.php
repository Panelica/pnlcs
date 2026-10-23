<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/*
 * Staff signed in as a customer could store a card on the customer's
 * "Add card" page, tick "keep this card" on an invoice, and switch automatic
 * payment on. Each is the cardholder's own consent, and the activity log wrote
 * it under the customer's name ("Client #5 stored a card ... and agreed to
 * automatic payment") with the staff member's address. From such a session the
 * customer's cards can be seen and an invoice can be paid; nothing is stored
 * and nothing is agreed to.
 */

function staffCardOn(): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
}

/** @return array{0: User, 1: Client, 2: Invoice} */
function staffCardCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['auto_charge' => false]);
    $user->clients()->attach($client->id);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 12.5, 'source_currency' => 'EUR']);

    return [$user, $client, $invoice];
}

function asStaff($test, User $user)
{
    return $test->actingAs($user)->withSession(['impersonating_admin_id' => 1, 'impersonating_admin_name' => 'Staff']);
}

test('the payment methods page offers no card and no automatic-payment switch to staff', function () {
    staffCardOn();
    [$user] = staffCardCustomer();

    $staff = asStaff($this, $user)->get(route('client.payment-methods.index'))->assertOk()->getContent();
    expect($staff)->not->toContain(route('client.payment-methods.add-card'))
        ->and($staff)->not->toContain('action="'.route('client.payment-methods.auto-charge').'"')
        ->and($staff)->toContain(e(__('client.payment_methods.staff_cannot_consent')));

    session()->forget('impersonating_admin_id');
    $own = $this->actingAs($user)->get(route('client.payment-methods.index'))->getContent();
    expect($own)->toContain(route('client.payment-methods.add-card'));
});

test('staff cannot switch automatic payment on for the customer', function () {
    staffCardOn();
    [$user, $client] = staffCardCustomer();

    asStaff($this, $user)->post(route('client.payment-methods.auto-charge'), ['auto_charge' => '1'])
        ->assertSessionHas('error');

    expect((bool) $client->fresh()->auto_charge)->toBeFalse();
});

test('the add-card page sends staff back without opening a card form', function () {
    staffCardOn();
    [$user] = staffCardCustomer();
    Http::fake();

    asStaff($this, $user)->get(route('client.payment-methods.add-card'))
        ->assertRedirect(route('client.payment-methods.index'));

    Http::assertNothingSent();
});

test('the pay form has no keep-this-card box for staff, and a ticked box is ignored', function () {
    staffCardOn();
    [$user, , $invoice] = staffCardCustomer();
    session(['impersonating_admin_id' => 1]);

    expect(app(StripeModule::class)->getPaymentForm($invoice))->not->toContain('id="stripe-save-card"');

    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret'])]);
    asStaff($this, $user)->postJson(route('gateway.stripe.intent', $invoice), ['save_card' => true])->assertOk();

    Http::assertSent(fn ($request) => ! isset($request['setup_future_usage']) && ! isset($request['metadata[save_card]']));
});
