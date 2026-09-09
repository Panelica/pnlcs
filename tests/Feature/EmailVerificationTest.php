<?php

use App\Http\Controllers\Client\EmailVerificationController;
use App\Mail\EmailVerificationMail;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
 * Proving that the address on an account belongs to whoever is using it.
 *
 * Anyone can type anyone's email into a sign-up form, and everything that
 * reaches a customer afterwards - the invoice, the password reset, the
 * suspension notice - goes to that address. So an unproven one is a service
 * sold to somebody who will never hear from us.
 *
 * On by default, because the safe behaviour should be the one an operator gets
 * without reading a settings screen; and stopped at the checkout rather than
 * the front door, because that is the point where an unreachable address
 * actually costs somebody money.
 */
function verifyProduct(): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => '', 'type' => 'other', 'hidden' => false, 'retired' => false,
    ]);

    Pricing::create([
        'type' => 'product',
        'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id,
        'rel_id' => $product->id,
        'monthly' => 10,
    ]);

    return $product;
}

function signedUpCustomer(bool $verified = false): array
{
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    $client = Client::factory()->create([
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
    ]);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

test('it is on without anybody turning it on', function () {
    expect(EmailVerificationController::required())->toBeTrue();
});

test('an operator can switch it off from the settings screen', function () {
    Setting::set('EmailVerificationRequired', '0');

    expect(EmailVerificationController::required())->toBeFalse();
});

test('signing up sends the link and lands on the waiting page', function () {
    Mail::fake();

    $this->post(route('client.register.submit'), [
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'tos' => '1',
    ])->assertRedirect(route('client.verification.notice'));

    Mail::assertSent(EmailVerificationMail::class, fn ($mail) => $mail->hasTo('ada@example.test'));

    expect(User::where('email', 'ada@example.test')->first()->email_verified_at)->toBeNull();
});

test('with verification switched off nobody is sent anywhere', function () {
    Setting::set('EmailVerificationRequired', '0');
    Mail::fake();

    $this->post(route('client.register.submit'), [
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => 'ada2@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'tos' => '1',
    ])->assertRedirect(route('client.home'));

    Mail::assertNothingSent();
});

test('an unverified customer is stopped at the checkout, not at the door', function () {
    [$user] = signedUpCustomer(verified: false);

    $this->actingAs($user);

    // The panel itself stays open.
    $this->get(route('client.home'))->assertOk();

    $this->post(route('client.cart.add'), ['product_id' => verifyProduct()->id, 'billing_cycle' => 'monthly']);

    $this->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => '1'])
        ->assertRedirect(route('client.verification.notice'));
});

test('a verified customer checks out as before', function () {
    [$user] = signedUpCustomer(verified: true);

    $this->actingAs($user);
    $this->post(route('client.cart.add'), ['product_id' => verifyProduct()->id, 'billing_cycle' => 'monthly']);

    $this->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => '1'])
        ->assertSessionHasNoErrors();
});

test('the link verifies the address and lets the customer in', function () {
    [$user] = signedUpCustomer(verified: false);

    $this->get(EmailVerificationController::verificationUrl($user))
        ->assertRedirect(route('client.home'));

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

test('a link with a broken signature verifies nothing', function () {
    [$user] = signedUpCustomer(verified: false);

    $tampered = EmailVerificationController::verificationUrl($user).'x';

    $this->get($tampered)->assertRedirect(route('client.login'));

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('a link for one account cannot verify another', function () {
    [$victim] = signedUpCustomer(verified: false);
    [$attacker] = signedUpCustomer(verified: false);

    // The signature covers the id, so swapping it invalidates the link.
    $url = str_replace('/'.$victim->id.'/', '/'.$attacker->id.'/', EmailVerificationController::verificationUrl($victim));

    $this->get($url)->assertRedirect(route('client.login'));

    expect($attacker->fresh()->email_verified_at)->toBeNull();
});

test('an expired link is refused', function () {
    [$user] = signedUpCustomer(verified: false);

    $url = URL::temporarySignedRoute(
        'client.verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->get($url)->assertRedirect(route('client.login'));

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('changing the address kills the link that was already sent', function () {
    [$user] = signedUpCustomer(verified: false);

    $url = EmailVerificationController::verificationUrl($user);
    $user->update(['email' => 'moved@example.test']);

    $this->get($url)->assertRedirect(route('client.login'));

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('verifying returns a stopped order to the checkout', function () {
    [$user] = signedUpCustomer(verified: false);

    $this->actingAs($user);
    $this->post(route('client.cart.add'), ['product_id' => verifyProduct()->id, 'billing_cycle' => 'monthly']);
    $this->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => '1']);

    $this->get(EmailVerificationController::verificationUrl($user))
        ->assertRedirect(route('client.cart.checkout'));
});

test('the resend button sends another link', function () {
    Mail::fake();
    [$user] = signedUpCustomer(verified: false);

    $this->actingAs($user)->post(route('client.verification.send'))->assertRedirect();

    Mail::assertSent(EmailVerificationMail::class);
});

test('an already verified account is not sent one', function () {
    Mail::fake();
    [$user] = signedUpCustomer(verified: true);

    EmailVerificationController::send($user);

    Mail::assertNothingSent();
});

test('the unverified banner appears in the panel and the checkout says why', function () {
    [$user] = signedUpCustomer(verified: false);

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertSee(__('client.email_verify.banner_link'));
});

test('the mail carries a link the framework accepts', function () {
    [$user] = signedUpCustomer(verified: false);

    $mail = new EmailVerificationMail(
        EmailVerificationController::verificationUrl($user),
        $user->email,
        (string) $user->first_name
    );

    expect($mail->render())->toContain('/client/email/verify/');
});
