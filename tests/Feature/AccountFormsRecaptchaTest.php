<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
 * reCAPTCHA in front of the account forms: opening an account (the register
 * page and the checkout, where a visitor opens one mid-order), signing in,
 * and asking for a password reset link.
 *
 * The contact and ticket forms gained a switch each; these are the forms bots
 * go for first - mass sign-ups, password guessing, reset mails sent to
 * strangers - and they had none. Each has its own switch, off by default, and
 * shares the keys and the fail-closed check of the existing ones.
 */

function afrKeys(): void
{
    Setting::set('RecaptchaSiteKey', 'site-key-for-tests');
    Setting::set('RecaptchaSecretKey', 'secret-key-for-tests');
}

function afrGoogle(bool $success): void
{
    Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => $success])]);
}

function afrSignup(array $extra = [])
{
    return test()->post(route('client.register.submit'), array_merge([
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada-captcha@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'tos' => '1',
    ], $extra));
}

function afrCart(): void
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => '', 'type' => 'other', 'hidden' => false, 'retired' => false,
    ]);
    Pricing::create([
        'type' => 'product', 'rel_id' => $product->id, 'monthly' => 10,
        'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id,
    ]);
    test()->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);
}

it('leaves every account form as it was while the switches are off', function () {
    Http::fake();
    Mail::fake();
    afrKeys();

    test()->get(route('client.register'))->assertOk()->assertDontSee('g-recaptcha', false);
    test()->get(route('client.login'))->assertOk()->assertDontSee('g-recaptcha', false);
    test()->get(route('client.password.request'))->assertOk()->assertDontSee('g-recaptcha', false);
    afrSignup()->assertSessionHasNoErrors();

    expect(User::where('email', 'ada-captcha@example.test')->exists())->toBeTrue();
    Http::assertNothingSent();
});

it('opens no account on the register page without an accepted answer', function () {
    afrGoogle(false);
    afrKeys();
    Setting::set('RecaptchaSignupEnabled', '1');

    test()->get(route('client.register'))->assertOk()->assertSee('data-sitekey="site-key-for-tests"', false);
    afrSignup([RecaptchaService::FIELD => 'bad-answer'])->assertSessionHasErrors(RecaptchaService::FIELD);

    expect(User::where('email', 'ada-captcha@example.test')->exists())->toBeFalse();
});

it('opens the account when Google accepts the answer', function () {
    afrGoogle(true);
    Mail::fake();
    afrKeys();
    Setting::set('RecaptchaSignupEnabled', '1');

    afrSignup([RecaptchaService::FIELD => 'good-answer'])->assertSessionHasNoErrors();

    expect(User::where('email', 'ada-captcha@example.test')->exists())->toBeTrue();
});

it('guards the account a visitor opens at checkout with the same switch', function () {
    afrGoogle(false);
    afrKeys();
    Setting::set('RecaptchaSignupEnabled', '1');
    afrCart();

    test()->get(route('client.cart.checkout'))->assertOk()->assertSee('data-sitekey="site-key-for-tests"', false);
    test()->post(route('client.cart.process'), [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada-captcha@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasErrors(RecaptchaService::FIELD);

    expect(User::where('email', 'ada-captcha@example.test')->exists())->toBeFalse();
});

it('does not try the password before the sign-in challenge is answered', function () {
    afrGoogle(false);
    afrKeys();
    Setting::set('RecaptchaLoginEnabled', '1');
    $user = User::factory()->create(['email' => 'known@example.test', 'password' => bcrypt('right-password')]);
    $user->clients()->attach(Client::factory()->create()->id);

    test()->get(route('client.login'))->assertOk()->assertSee('data-sitekey="site-key-for-tests"', false);
    test()->post(route('client.login.submit'), ['email' => 'known@example.test', 'password' => 'right-password'])
        ->assertSessionHasErrors(RecaptchaService::FIELD);

    $this->assertGuest();
});

it('sends no reset mail without an accepted answer', function () {
    afrGoogle(false);
    Mail::fake();
    afrKeys();
    Setting::set('RecaptchaPasswordEnabled', '1');
    User::factory()->create(['email' => 'known@example.test']);

    test()->get(route('client.password.request'))->assertOk()->assertSee('data-sitekey="site-key-for-tests"', false);
    test()->post(route('client.password.email'), ['email' => 'known@example.test'])
        ->assertSessionHasErrors(RecaptchaService::FIELD);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('keeps each switch to its own form', function () {
    afrKeys();
    Setting::set('RecaptchaLoginEnabled', '1');

    expect(app(RecaptchaService::class)->enabled('login'))->toBeTrue()
        ->and(app(RecaptchaService::class)->enabled('signup'))->toBeFalse()
        ->and(app(RecaptchaService::class)->enabled('password'))->toBeFalse()
        ->and(app(RecaptchaService::class)->enabled('contact'))->toBeFalse();
});
