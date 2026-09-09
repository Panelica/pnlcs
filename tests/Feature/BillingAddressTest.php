<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\InvoiceService;

/*
 * The address an invoice is issued to.
 *
 * Nothing asked for it. Sign-up wanted a name, an email and a password;
 * checkout wanted even less. Every account therefore arrived with no street,
 * no city and - worse than blank - a country of "US" that the column default
 * supplied, because a country is what the tax rate is chosen by. So a Turkish
 * customer was taxed as an American one, the invoice PDF printed a buyer with
 * no address, and a domain order carried a registrant the registrar refuses.
 *
 * The fields, the columns and even the translated labels were all already
 * there. Only the questions were missing.
 */

function addressProduct(): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => '',
        'type' => 'other',
        'hidden' => false,
        'retired' => false,
    ]);

    Pricing::create([
        'type' => 'product',
        'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id,
        'rel_id' => $product->id,
        'monthly' => 10,
    ]);

    return $product;
}

test('the sign-up form asks for an address', function () {
    $html = $this->get(route('client.register'))->assertOk()->getContent();

    expect($html)->toContain('name="address1"')
        ->and($html)->toContain('name="city"')
        ->and($html)->toContain('name="postcode"')
        ->and($html)->toContain('name="country"');
});

test('signing up without an address is refused', function () {
    $this->post(route('client.register.submit'), [
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'tos' => '1',
    ])->assertSessionHasErrors(['address1', 'city', 'postcode', 'country']);

    expect(User::where('email', 'ada@example.test')->exists())->toBeFalse();
});

test('signing up stores the whole address', function () {
    $this->post(route('client.register.submit'), [
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => 'ada2@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => '12 Market Street', 'address2' => 'Floor 3',
        'city' => 'Istanbul', 'state' => 'Marmara', 'postcode' => '34000',
        'country' => 'TR', 'tax_id' => '1234567890',
        'tos' => '1',
    ])->assertSessionHasNoErrors();

    $client = Client::where('email', 'ada2@example.test')->first();

    expect($client)->not->toBeNull()
        ->and($client->address1)->toBe('12 Market Street')
        ->and($client->address2)->toBe('Floor 3')
        ->and($client->city)->toBe('Istanbul')
        ->and($client->state)->toBe('Marmara')
        ->and($client->postcode)->toBe('34000')
        ->and($client->country)->toBe('TR')
        ->and($client->tax_id)->toBe('1234567890');
});

test('an account with no country given follows the operator setting, not a hardcoded US', function () {
    Setting::set('Country', 'TR');

    [$user, $client] = app(\App\Services\ClientRegistrationService::class)->register([
        'first_name' => 'No', 'last_name' => 'Country',
        'email' => 'nocountry@example.test', 'password' => 'secret-enough',
    ], request());

    expect($client->country)->toBe('TR');
});

test('checkout asks a customer whose address was never captured', function () {
    $client = Client::factory()->create(['address1' => '', 'city' => '', 'postcode' => '']);
    $user = User::factory()->create();
    $client->users()->attach($user->id, ['owner' => true]);

    $this->actingAs($user);
    $this->post(route('client.cart.add'), ['product_id' => addressProduct()->id, 'billing_cycle' => 'monthly']);

    $this->get(route('client.cart.checkout'))->assertOk()->assertSee('name="address1"', false);
});

test('checkout does not ask a customer who already has one', function () {
    $client = Client::factory()->create([
        'address1' => '9 Existing Road', 'city' => 'Ankara', 'postcode' => '06000', 'country' => 'TR',
    ]);
    $user = User::factory()->create();
    $client->users()->attach($user->id, ['owner' => true]);

    $this->actingAs($user);
    $this->post(route('client.cart.add'), ['product_id' => addressProduct()->id, 'billing_cycle' => 'monthly']);

    $this->get(route('client.cart.checkout'))->assertOk()->assertDontSee('name="address1"', false);
});

test('checkout stores the address it asked an existing customer for', function () {
    $client = Client::factory()->create(['address1' => '', 'city' => '', 'postcode' => '', 'country' => 'US']);
    $user = User::factory()->create();
    $client->users()->attach($user->id, ['owner' => true]);

    $this->actingAs($user);
    $this->post(route('client.cart.add'), ['product_id' => addressProduct()->id, 'billing_cycle' => 'monthly']);

    $this->post(route('client.cart.process'), [
        'address1' => '5 New Street', 'city' => 'Izmir', 'postcode' => '35000', 'country' => 'TR',
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect($client->fresh()->country)->toBe('TR')
        ->and($client->fresh()->address1)->toBe('5 New Street');
});

test('the country that was asked for decides the tax rate', function () {
    TaxRule::create(['name' => 'KDV', 'country' => 'TR', 'state' => '', 'tax_rate' => 20, 'is_default' => true]);
    TaxRule::create(['name' => 'Sales Tax', 'country' => 'US', 'state' => '', 'tax_rate' => 0, 'is_default' => true]);

    $turkish = Client::factory()->create(['country' => 'TR', 'state' => '']);

    expect(app(InvoiceService::class)->taxRuleFor($turkish)?->tax_rate)->toEqual(20);
});

test('a customer can correct the tax id their own invoices carry', function () {
    $client = Client::factory()->create(['country' => 'TR', 'tax_id' => 'OLD']);
    $user = User::factory()->create();
    $client->users()->attach($user->id, ['owner' => true]);

    $this->actingAs($user)->put(route('client.account.update'), [
        'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email,
        'country' => 'TR', 'tax_id' => 'NEW-VKN-123',
    ])->assertSessionHasNoErrors();

    expect($client->fresh()->tax_id)->toBe('NEW-VKN-123');
});
