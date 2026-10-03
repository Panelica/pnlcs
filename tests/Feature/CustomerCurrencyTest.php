<?php

use App\Mail\InvoiceCreatedMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\DomainPricing;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Support\CustomerCurrency;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/*
 * Customers choosing the currency they see prices and invoices in.
 *
 * The books stay in the shop's default currency: prices, invoice lines,
 * balances, refunds and charges are all worked out in it. What the customer
 * chooses is the currency prices are shown in, at the daily rate, and the
 * currency each of their invoices is stamped with, the rate fixed the day it
 * is raised. Off until the operator switches it on.
 *
 * Shop: USD (default). PLN at 4, written "40.00 zł". EUR at 0.9, "€9.00".
 */

function ccShop(): array
{
    Currency::query()->update(['is_default' => false]);
    $usd = Currency::updateOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $pln = Currency::updateOrCreate(['code' => 'PLN'], ['prefix' => '', 'suffix' => ' zł', 'rate' => 4, 'is_default' => false]);
    $eur = Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 0.9, 'is_default' => false]);

    // money_fmt() memoises the shop currency for the request.
    app()->forgetInstance('pnlcs.currency');

    return [$usd, $pln, $eur];
}

function ccOn(): void
{
    Setting::set(CustomerCurrency::SETTING, '1');
}

function ccProduct(Currency $usd, float $monthly = 10.0): Product
{
    $group = ProductGroup::factory()->create(['hidden' => false]);
    $product = Product::factory()->create(['group_id' => $group->id, 'hidden' => false, 'retired' => false, 'name' => 'CC Plan '.uniqid()]);

    Pricing::factory()->create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $usd->id,
        'monthly' => $monthly, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1,
    ]);

    return $product;
}

/** @return array{0: User, 1: Client} */
function ccCustomer(?Currency $currency = null): array
{
    $user = User::factory()->create(['email' => 'cc_'.uniqid().'@example.com']);
    $client = Client::factory()->create(['email' => $user->email, 'currency_id' => $currency?->id]);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

function ccInvoice(Client $client, float $total = 10.0): Invoice
{
    return Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => $total, 'total' => $total]);
}

function ccAdmin(array $permissions): Admin
{
    $role = AdminRole::create(['name' => 'CC '.uniqid(), 'permissions' => $permissions, 'is_full_admin' => false]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

function ccGateway(string $gateway, array $settings): void
{
    foreach ($settings as $name => $value) {
        GatewaySettings::updateOrCreate(['gateway' => $gateway, 'setting' => $name], ['value' => $value]);
    }
}

// ------------------------------------------------------------ switched off

test('it is off until the operator switches it on, and then nothing changes', function () {
    [$usd] = ccShop();
    $product = ccProduct($usd);

    $html = $this->get(route('client.store', ['currency' => 'PLN']))->assertOk()->getContent();

    expect(session(CustomerCurrency::SESSION_KEY))->toBeNull()
        ->and($html)->toContain('$10.00')
        ->and($html)->not->toContain('zł')
        ->and($html)->not->toContain('pn-currency-selector');
});

test('with it off, a customer currency set by staff does not touch their invoices', function () {
    [, $pln] = ccShop();
    Setting::set('BillingCurrency', '');
    [, $client] = ccCustomer($pln);

    $invoice = ccInvoice($client);

    expect($invoice->billing_currency)->toBeNull()
        ->and((float) $invoice->total)->toBe(10.0);
});

test('with it off, the shop-wide billing currency works exactly as before', function () {
    ccShop();
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '', 'suffix' => ' TL', 'rate' => 40, 'is_default' => false]);
    Setting::set('BillingCurrency', 'TRY');
    Setting::set('ExchangeRateSource', 'TCMB');
    [, $client] = ccCustomer(Currency::where('code', 'PLN')->first());

    $invoice = ccInvoice($client);

    expect($invoice->billing_currency)->toBe('TRY')
        ->and((float) $invoice->exchange_rate)->toBe(40.0)
        ->and($invoice->exchange_rate_source)->toBe('TCMB');
});

test('switched on with a single currency there is nothing to choose', function () {
    Currency::query()->delete();
    Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    ccOn();

    expect(CustomerCurrency::enabled())->toBeFalse();
    $this->get(route('client.store'))->assertOk()->assertDontSee('pn-currency-selector', false);
});

// ----------------------------------------------------------- visitor choice

test('a visitor picks a currency from the top bar and keeps it for the session', function () {
    [$usd, $pln] = ccShop();
    ccOn();
    $product = ccProduct($usd);

    $html = $this->get(route('client.store', ['currency' => 'PLN']))->assertOk()->getContent();

    expect(session(CustomerCurrency::SESSION_KEY))->toBe($pln->id)
        ->and($html)->toContain('40.00 zł')
        ->and($html)->not->toContain('$10.00')
        ->and($html)->toContain('pn-currency-selector')
        ->and($html)->toContain('currency=EUR');

    // The next page, with no parameter, is still in złoty.
    $this->get(route('client.store'))->assertOk()->assertSee('40.00 zł');

    // And back.
    $this->get(route('client.store', ['currency' => 'usd']))->assertOk()->assertSee('$10.00');
    expect(session(CustomerCurrency::SESSION_KEY))->toBe($usd->id);
});

test('anything that is not a usable currency code is ignored', function (mixed $value) {
    [$usd, $pln] = ccShop();
    ccOn();
    ccProduct($usd);
    $this->get(route('client.store', ['currency' => 'PLN']));

    $html = $this->get(route('client.store').'?'.http_build_query(['currency' => $value]))->assertOk()->getContent();

    expect(session(CustomerCurrency::SESSION_KEY))->toBe($pln->id)
        ->and($html)->not->toContain('<script>alert(1)</script>');
})->with([
    'unknown code' => 'XXX',
    'markup' => '<script>alert(1)</script>',
    'too long' => 'PLNX',
    'digits' => '123',
    'an array' => [['PLN']],
    'sql' => "PLN' OR 1=1 --",
]);

test('a currency without a rate is neither offered nor accepted', function () {
    [$usd] = ccShop();
    ccOn();
    Currency::create(['code' => 'GBP', 'prefix' => '£', 'suffix' => '', 'rate' => 0, 'is_default' => false]);
    ccProduct($usd);

    $html = $this->get(route('client.store', ['currency' => 'GBP']))->assertOk()->getContent();

    expect(session(CustomerCurrency::SESSION_KEY))->toBeNull()
        ->and($html)->not->toContain('currency=GBP')
        ->and($html)->toContain('$10.00');
});

test('the order form shows and totals every price in the chosen currency', function () {
    [$usd] = ccShop();
    ccOn();
    $product = ccProduct($usd, 12.5);

    $this->get(route('client.store', ['currency' => 'PLN']));
    $html = $this->get(route('client.store.configure', $product))->assertOk()->getContent();

    expect($html)->toContain('data-price="50"')
        ->and($html)->toContain('50.00 zł')
        // The script that adds the total formats with the same sign.
        ->and($html)->toContain('"suffix":'.json_encode(' zł'));
});

test('the cart and the checkout show the chosen currency and say how it is converted', function () {
    [$usd] = ccShop();
    ccOn();
    $product = ccProduct($usd);
    [$user, $client] = ccCustomer(Currency::where('code', 'EUR')->first());
    app(CartService::class)->addProduct(app(CartService::class)->getOrCreateCart($client->id), $product, 'monthly', 'cc-'.uniqid().'.com');

    $this->actingAs($user)->get(route('client.cart.index'))->assertOk()
        ->assertSee('€9.00')
        ->assertSee('1 USD = 0.9000 EUR');

    $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()
        ->assertSee('€9.00')
        ->assertSee('pn-currency-note', false);
});

test('domain prices follow the chosen currency', function () {
    ccShop();
    ccOn();
    DomainPricing::create(['extension' => '.cc'.substr(uniqid(), -4), 'register_price' => 20, 'transfer_price' => 20, 'renew_price' => 20, 'enabled' => true]);

    $this->get(route('client.domain.pricing', ['currency' => 'PLN']))->assertOk()->assertSee('80.00 zł');
});

test('the domain quote on the order form is in the chosen currency', function () {
    ccShop();
    ccOn();
    $this->get(route('client.store', ['currency' => 'PLN']));

    $this->mock(CartService::class, function ($mock) {
        $mock->shouldReceive('quoteDomain')->andReturn(['domain' => 'cc-quote.com', 'type' => 'register', 'tld' => '.com', 'status' => 'ok', 'price' => 12.0]);
        $mock->shouldReceive('domainQuoteProblem')->andReturn(null);
    });

    $this->postJson(route('client.cart.domain-quote'), ['domain' => 'cc-quote.com', 'type' => 'register'])
        ->assertOk()
        ->assertJson(['price' => 48, 'price_formatted' => '48.00 zł']);
});

test('the admin area is not touched by a currency parameter', function () {
    ccShop();
    ccOn();

    $this->get('/admin/login?currency=PLN');

    expect(session(CustomerCurrency::SESSION_KEY))->toBeNull();
});

test('a page that fails still fails, once: the currency step does not swallow it', function () {
    ccShop();
    ccOn();
    $runs = 0;
    \Illuminate\Support\Facades\Route::middleware('web')->get('/cc-fails', function () use (&$runs) {
        $runs++;
        throw new RuntimeException('page failed');
    });

    $this->get('/cc-fails?currency=PLN')->assertStatus(500);

    expect($runs)->toBe(1);
});

// ------------------------------------------------------ accounts and login

test('the account a visitor opens keeps the currency they picked', function () {
    [, $pln] = ccShop();
    ccOn();

    $this->get(route('client.store', ['currency' => 'PLN']));
    $this->post(route('client.register.submit'), [
        'first_name' => 'Cur', 'last_name' => 'Rency', 'email' => 'cc-picked@example.com',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'address1' => '1 Test Street', 'city' => 'Warsaw', 'postcode' => '00-001', 'country' => 'PL',
        'tos' => '1',
    ])->assertRedirect();

    expect(Client::where('email', 'cc-picked@example.com')->value('currency_id'))->toBe($pln->id);
});

test('an account opened without a choice stays in the shop currency', function () {
    ccShop();
    ccOn();

    $this->post(route('client.register.submit'), [
        'first_name' => 'No', 'last_name' => 'Choice', 'email' => 'cc-none@example.com',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'address1' => '1 Test Street', 'city' => 'Warsaw', 'postcode' => '00-001', 'country' => 'PL',
        'tos' => '1',
    ])->assertRedirect();

    expect(Client::where('email', 'cc-none@example.com')->value('currency_id'))->toBeNull();
});

test('with the feature off a choice made earlier is not written to a new account', function () {
    ccShop();
    ccOn();
    $this->get(route('client.store', ['currency' => 'PLN']));
    Setting::set(CustomerCurrency::SETTING, '0');

    $this->post(route('client.register.submit'), [
        'first_name' => 'Late', 'last_name' => 'Off', 'email' => 'cc-off@example.com',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'address1' => '1 Test Street', 'city' => 'Warsaw', 'postcode' => '00-001', 'country' => 'PL',
        'tos' => '1',
    ])->assertRedirect();

    expect(Client::where('email', 'cc-off@example.com')->value('currency_id'))->toBeNull();
});

test('a link cannot change the currency of a logged-in customer', function () {
    [$usd, $pln, $eur] = ccShop();
    ccOn();
    ccProduct($usd);
    [$user, $client] = ccCustomer($pln);

    $html = $this->actingAs($user)->get(route('client.store', ['currency' => 'EUR']))->assertOk()->getContent();

    expect($client->fresh()->currency_id)->toBe($pln->id)
        ->and(session(CustomerCurrency::SESSION_KEY))->toBeNull()
        ->and($html)->toContain('40.00 zł')
        ->and($html)->not->toContain('€9.00')
        // Their currency is shown, not offered.
        ->and($html)->not->toContain('currency=EUR');
});

test('a customer whose account has no currency sees the shop currency, whatever they picked as a visitor', function () {
    [$usd] = ccShop();
    ccOn();
    ccProduct($usd);
    [$user] = ccCustomer();

    $this->get(route('client.store', ['currency' => 'PLN']));
    $this->actingAs($user)->get(route('client.store'))->assertOk()->assertSee('$10.00')->assertDontSee('40.00 zł');
});

// ----------------------------------------------------------------- invoices

test('a customer invoice carries their currency with the rate of the day; the amounts stay in the shop currency', function () {
    [, $pln] = ccShop();
    ccOn();
    // Official-rate details describe the lira; they must not land on a złoty invoice.
    Setting::set('ExchangeRateSource', 'TCMB');
    [, $client] = ccCustomer($pln);

    $invoice = ccInvoice($client, 10.0);

    expect($invoice->source_currency)->toBe('USD')
        ->and((float) $invoice->total)->toBe(10.0)
        ->and($invoice->billing_currency)->toBe('PLN')
        ->and((float) $invoice->exchange_rate)->toBe(4.0)
        ->and($invoice->exchange_rate_source)->toBeNull()
        ->and(billing_money_fmt(10, $invoice))->toBe('40.00 zł')
        ->and(dual_money_fmt(10, $invoice))->toBe('40.00 zł ($10.00)');

    // The rate moves; the invoice does not.
    $pln->update(['rate' => 4.5]);
    expect(billing_money_fmt(10, $invoice->fresh()))->toBe('40.00 zł');
});

test('the customer currency comes before the shop-wide billing currency', function () {
    [, $pln] = ccShop();
    ccOn();
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '', 'suffix' => ' TL', 'rate' => 40, 'is_default' => false]);
    Setting::set('BillingCurrency', 'TRY');

    [, $polish] = ccCustomer($pln);
    [, $plain] = ccCustomer();
    [, $inShopCurrency] = ccCustomer(Currency::where('code', 'USD')->first());

    expect(ccInvoice($polish)->billing_currency)->toBe('PLN')
        // No currency of their own, or the shop's: the shop-wide rule, as before.
        ->and(ccInvoice($plain)->billing_currency)->toBe('TRY')
        ->and(ccInvoice($inShopCurrency)->billing_currency)->toBe('TRY');
});

test('a lira customer gets the official rate details on their invoice', function () {
    ccShop();
    ccOn();
    $try = Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '', 'suffix' => ' TL', 'rate' => 40, 'is_default' => false]);
    Setting::set('ExchangeRateSource', 'TCMB');
    Setting::set('ExchangeRateDate', '03.10.2026');
    [, $client] = ccCustomer($try);

    $invoice = ccInvoice($client);

    expect($invoice->billing_currency)->toBe('TRY')
        ->and($invoice->exchange_rate_source)->toBe('TCMB')
        ->and($invoice->exchange_rate_date)->toBe('03.10.2026');
});

test('no stamp when the customer currency has no rate, or the invoice is not in the shop currency', function () {
    [, $pln] = ccShop();
    ccOn();
    [, $client] = ccCustomer($pln);

    $pln->update(['rate' => 0]);
    expect(ccInvoice($client)->billing_currency)->toBeNull();

    $pln->update(['rate' => 4]);
    $foreign = Invoice::factory()->create(['client_id' => $client->id, 'total' => 10, 'source_currency' => 'EUR']);
    expect($foreign->billing_currency)->toBeNull();
});

test('a visitor is invoiced at the figure the cart showed them', function () {
    [$usd, $pln] = ccShop();
    ccOn();
    $product = ccProduct($usd, 10.0);

    $this->get(route('client.store', ['currency' => 'PLN']));
    $this->post(route('client.register.submit'), [
        'first_name' => 'End', 'last_name' => 'ToEnd', 'email' => 'cc-e2e@example.com',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'address1' => '1 Test Street', 'city' => 'Warsaw', 'postcode' => '00-001', 'country' => 'PL',
        'tos' => '1',
    ])->assertRedirect();

    $client = Client::where('email', 'cc-e2e@example.com')->firstOrFail();
    $user = User::where('email', 'cc-e2e@example.com')->firstOrFail();
    // A new account confirms its address before it can order.
    $user->forceFill(['email_verified_at' => now()])->save();
    app(CartService::class)->addProduct(app(CartService::class)->getOrCreateCart($client->id), $product, 'monthly', 'cc-e2e-'.uniqid().'.com');

    $cart = $this->actingAs($user)->get(route('client.cart.index'))->assertOk()->getContent();

    $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => '1'])->assertRedirect();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect($invoice->billing_currency)->toBe('PLN')
        ->and((float) $invoice->exchange_rate)->toBe(4.0)
        ->and($cart)->toContain(billing_money_fmt($invoice->total, $invoice));
});

test('the invoice list, the invoice page and the invoice email lead with the customer currency', function () {
    [, $pln] = ccShop();
    ccOn();
    [$user, $client] = ccCustomer($pln);
    $invoice = ccInvoice($client, 10.0);

    $this->actingAs($user)->get(route('client.invoices.index'))->assertOk()->assertSee('40.00 zł ($10.00)');
    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()->assertSee('40.00 zł');

    $mail = (new InvoiceCreatedMail($invoice))->render();
    expect($mail)->toContain('40.00 zł ($10.00)');
});

test('an invoice in the shop currency reads exactly as before', function () {
    ccShop();
    Setting::set('BillingCurrency', '');
    [, $client] = ccCustomer();
    $invoice = ccInvoice($client, 10.0);

    expect((new InvoiceCreatedMail($invoice))->render())->toContain('$10.00')
        ->and(dual_money_fmt(10, $invoice))->toBe('$10.00');
});

// ------------------------------------------------------ the money itself

test('a card is still charged in the shop currency, the amount unchanged', function () {
    [, $pln] = ccShop();
    ccOn();
    [, $client] = ccCustomer($pln);
    $invoice = ccInvoice($client, 10.0);
    ccGateway('stripe', ['secret_key' => 'sk_test_cc']);
    Http::fake(['*' => Http::response(['id' => 'pi_cc', 'client_secret' => 'cs_cc'], 200)]);

    app(StripeModule::class)->capture($invoice, 10.0);

    Http::assertSent(fn ($request) => ($request['currency'] ?? null) === 'usd' && (int) ($request['amount'] ?? 0) === 1000);
    Http::assertNotSent(fn ($request) => ($request['currency'] ?? null) === 'pln');
});

test('adding funds asks in the customer currency and credits the shop-currency amount', function () {
    [, $pln] = ccShop();
    ccOn();
    ccGateway('banktransfer', ['active' => '1']);
    [$user, $client] = ccCustomer($pln);

    $this->actingAs($user)->get(route('client.funds.index'))->assertOk()->assertSee('1 USD = 4,0000 PLN');

    // The minimum is five shop-currency units, asked in złoty.
    $this->actingAs($user)->post(route('client.funds.store'), ['amount' => 19, 'payment_method' => 'banktransfer'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)->post(route('client.funds.store'), ['amount' => 40, 'payment_method' => 'banktransfer'])->assertRedirect();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect((float) $invoice->total)->toBe(10.0)
        ->and($invoice->billing_currency)->toBe('PLN')
        ->and(billing_money_fmt($invoice->total, $invoice))->toBe('40.00 zł');
});

// -------------------------------------------------------------------- admin

test('staff who manage currencies switch it on and choose the daily rate update', function () {
    ccShop();
    $admin = ccAdmin(['manage_currencies']);

    $this->actingAs($admin, 'admin')->get(route('admin.config.currencies'))->assertOk()
        ->assertSee(__('admin.currencies.customer_choice'))
        ->assertSee(__('admin.currencies.update_rates_now'));

    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.settings'), ['customer_choice' => '1', 'auto_update_rates' => '0'])
        ->assertRedirect()->assertSessionHas('success');

    expect(Setting::get(CustomerCurrency::SETTING))->toBe('1')
        ->and(Setting::get('currency_auto_update'))->toBe('0')
        ->and(CustomerCurrency::enabled())->toBeTrue();

    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.settings'), ['customer_choice' => '0', 'auto_update_rates' => '1']);
    expect(Setting::get(CustomerCurrency::SETTING))->toBe('0')
        ->and(Setting::get('currency_auto_update'))->toBe('1');
});

test('staff without the currency permission can change none of it', function () {
    ccShop();
    $admin = ccAdmin(['list_clients']);

    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.settings'), ['customer_choice' => '1'])->assertForbidden();
    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.update-rates'))->assertForbidden();

    expect(Setting::get(CustomerCurrency::SETTING))->toBeNull();
});

test('the rates can be fetched now, even with the daily update off', function () {
    [, $pln] = ccShop();
    Setting::set('currency_auto_update', '0');
    Setting::set('OfficialRateProvider', '');
    Http::fake([
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['PLN' => 4.2, 'EUR' => 0.95]], 200),
    ]);

    $this->actingAs(ccAdmin(['manage_currencies']), 'admin')
        ->post(route('admin.config.currencies.update-rates'))
        ->assertRedirect()->assertSessionHas('success');

    expect((float) $pln->fresh()->rate)->toBe(4.2);
});

test('a currency customers are billed in cannot be deleted from under them', function () {
    [, $pln] = ccShop();
    [, $client] = ccCustomer($pln);
    $admin = ccAdmin(['manage_currencies']);

    $this->actingAs($admin, 'admin')->delete(route('admin.config.currencies.destroy', $pln))->assertSessionHas('error');
    expect(Currency::find($pln->id))->not->toBeNull();

    $client->update(['currency_id' => null]);
    $this->actingAs($admin, 'admin')->delete(route('admin.config.currencies.destroy', $pln))->assertSessionHas('success');
    expect(Currency::find($pln->id))->toBeNull();
});

test('staff see and change a customer currency on the client profile', function () {
    [, $pln, $eur] = ccShop();
    ccOn();
    [, $client] = ccCustomer($pln);
    $admin = ccAdmin(['edit_clients', 'view_clients', 'list_clients']);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.clients.edit', $client))->assertOk()->getContent();
    expect($html)->toMatch('/<option value="'.$pln->id.'"\s+selected/');

    $this->actingAs($admin, 'admin')->put(route('admin.clients.update', $client), [
        'first_name' => $client->first_name, 'last_name' => $client->last_name, 'email' => $client->email,
        'country' => 'PL', 'status' => 'active', 'currency_id' => $eur->id,
    ])->assertRedirect();

    expect($client->fresh()->currency_id)->toBe($eur->id)
        ->and(ccInvoice($client->fresh())->billing_currency)->toBe('EUR');
});

test('the currencies page counts the customers in each currency', function () {
    [$usd, $pln] = ccShop();
    ccCustomer($pln);
    ccCustomer($pln);

    $page = $this->actingAs(ccAdmin(['manage_currencies']), 'admin')->get(route('admin.config.currencies'))->assertOk();

    expect($page->viewData('clientsPerCurrency')[$pln->id] ?? 0)->toBe(2)
        // Accounts with no currency of their own are in the default one.
        ->and($page->viewData('clientsPerCurrency')[$usd->id] ?? 0)->toBe(Client::whereNull('currency_id')->count() + Client::where('currency_id', $usd->id)->count());
});
