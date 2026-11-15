<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\User;
use App\Support\CountryCurrency;
use App\Support\CustomerCurrency;

/*
 * #432. With customers choosing their currency, a first visit still opened in
 * the shop's default whatever country it came from. The operator can now
 * start a visitor in the currency of their country: a first guess, read from
 * the country header a CDN sets or the browser's region, that never overrides
 * a choice from the top bar and never touches an account.
 *
 * Shop: USD (default). PLN at 4, written "40.00 zł". EUR at 0.9, "€9.00".
 */

function cbrShop(bool $byRegion = true): array
{
    Currency::query()->update(['is_default' => false]);
    $usd = Currency::updateOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $pln = Currency::updateOrCreate(['code' => 'PLN'], ['prefix' => '', 'suffix' => ' zł', 'rate' => 4, 'is_default' => false]);
    $eur = Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 0.9, 'is_default' => false]);
    app()->forgetInstance('pnlcs.currency');

    Setting::set(CustomerCurrency::SETTING, '1');
    Setting::set(CustomerCurrency::REGION_SETTING, $byRegion ? '1' : '0');

    $group = ProductGroup::factory()->create(['hidden' => false]);
    $product = Product::factory()->create(['group_id' => $group->id, 'hidden' => false, 'retired' => false, 'name' => 'CBR Plan '.uniqid()]);
    Pricing::factory()->create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $usd->id,
        'monthly' => 10, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1,
    ]);

    return [$usd, $pln, $eur];
}

function cbrRegister($test, string $email, array $headers = [])
{
    return $test->withHeaders($headers)->post(route('client.register.submit'), [
        'first_name' => 'By', 'last_name' => 'Region', 'email' => $email,
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'address1' => '1 Test Street', 'city' => 'Warsaw', 'postcode' => '00-001', 'country' => 'PL',
        'tos' => '1',
    ]);
}

test('the table knows the currency of a country, and says nothing for what is not one', function () {
    expect(CountryCurrency::for('FR'))->toBe('EUR')
        ->and(CountryCurrency::for('us'))->toBe('USD')
        ->and(CountryCurrency::for('GB'))->toBe('GBP')
        ->and(CountryCurrency::for('SN'))->toBe('XOF')
        ->and(CountryCurrency::for('CA'))->toBe('CAD')
        ->and(CountryCurrency::for('TR'))->toBe('TRY')
        ->and(CountryCurrency::for('ZZ'))->toBeNull()
        ->and(CountryCurrency::for('FRA'))->toBeNull()
        ->and(CountryCurrency::for(null))->toBeNull();
});

test('off, as by default, a visitor from anywhere sees the shop currency', function () {
    cbrShop(byRegion: false);

    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()
        ->assertSee('$10.00')->assertDontSee('40.00 zł');
});

test('on, a first visit opens in the currency of the country it comes from', function () {
    cbrShop();

    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()
        ->assertSee('40.00 zł')->assertDontSee('$10.00');

    $this->withHeaders(['CF-IPCountry' => 'DE'])->get(route('client.store'))->assertOk()->assertSee('€9.00');
});

test('without a country header the region the browser states is used', function () {
    cbrShop();

    $this->withHeaders(['Accept-Language' => 'pl-PL,pl;q=0.9,en;q=0.8'])->get(route('client.store'))->assertOk()->assertSee('40.00 zł');

    // A language without a region says nothing about where the visitor is.
    $this->withHeaders(['Accept-Language' => 'pl'])->get(route('client.store'))->assertOk()->assertSee('$10.00');
});

test('the country header wins over the browser region', function () {
    cbrShop();

    $this->withHeaders(['CF-IPCountry' => 'DE', 'Accept-Language' => 'pl-PL'])->get(route('client.store'))->assertOk()->assertSee('€9.00');
});

test('a country whose currency the shop does not have, or has without a rate, starts in the shop currency', function () {
    [, $pln] = cbrShop();

    $this->withHeaders(['CF-IPCountry' => 'JP'])->get(route('client.store'))->assertOk()->assertSee('$10.00');

    $pln->update(['rate' => 0]);
    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()->assertSee('$10.00');
});

test('a choice from the top bar overrides the guess and is remembered, the shop currency included', function () {
    [$usd] = cbrShop();

    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store', ['currency' => 'EUR']))->assertOk()->assertSee('€9.00');
    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()->assertSee('€9.00');

    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store', ['currency' => 'USD']))->assertOk()->assertSee('$10.00');
    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()->assertSee('$10.00');

    expect(session(CustomerCurrency::SESSION_KEY))->toBe($usd->id);
});

test('the guess is not written to the session: it is not a choice', function () {
    cbrShop();

    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk();

    expect(session(CustomerCurrency::SESSION_KEY))->toBeNull();
});

test('the account a visitor opens is billed in the currency they were shown', function () {
    [, $pln] = cbrShop();

    cbrRegister($this, 'cbr-pl@example.com', ['CF-IPCountry' => 'PL'])->assertRedirect();

    expect(Client::where('email', 'cbr-pl@example.com')->value('currency_id'))->toBe($pln->id);
});

test('an account opened from the shop\'s own country, or with the guess off, carries no currency of its own', function () {
    cbrShop();
    cbrRegister($this, 'cbr-us@example.com', ['CF-IPCountry' => 'US'])->assertRedirect();
    expect(Client::where('email', 'cbr-us@example.com')->value('currency_id'))->toBeNull();

    auth()->logout();
    Setting::set(CustomerCurrency::REGION_SETTING, '0');
    cbrRegister($this, 'cbr-off@example.com', ['CF-IPCountry' => 'PL'])->assertRedirect();
    expect(Client::where('email', 'cbr-off@example.com')->value('currency_id'))->toBeNull();
});

test('a logged-in customer sees their account currency wherever they are', function () {
    [, , $eur] = cbrShop();
    $user = User::factory()->create(['email' => 'cbr-in@example.com']);
    $client = Client::factory()->create(['email' => $user->email, 'currency_id' => $eur->id]);
    $user->clients()->attach($client->id, ['owner' => true]);

    $this->actingAs($user)->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()
        ->assertSee('€9.00')->assertDontSee('40.00 zł');
});

test('with customers not choosing their currency the guess does nothing', function () {
    cbrShop();
    Setting::set(CustomerCurrency::SETTING, '0');

    $this->withHeaders(['CF-IPCountry' => 'PL'])->get(route('client.store'))->assertOk()->assertSee('$10.00');
});

test('staff who manage currencies switch it on from the currencies page', function () {
    cbrShop(byRegion: false);
    $role = AdminRole::factory()->create(['permissions' => ['manage_currencies']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.currencies'))->assertOk()
        ->assertSee(__('admin.currencies.by_region'));

    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.settings'), ['customer_choice' => '1', 'by_region' => '1', 'auto_update_rates' => '1'])
        ->assertRedirect()->assertSessionHas('success');
    expect(Setting::get(CustomerCurrency::REGION_SETTING))->toBe('1');

    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.settings'), ['customer_choice' => '1', 'by_region' => '0', 'auto_update_rates' => '1']);
    expect(Setting::get(CustomerCurrency::REGION_SETTING))->toBe('0');
});
