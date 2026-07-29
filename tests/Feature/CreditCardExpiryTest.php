<?php

use App\Mail\CreditCardExpiryMail;
use App\Models\Client;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Mail;

/**
 * Warning a customer their card is about to die.
 *
 * The command said "the next 30 days" but never looked past the end of this
 * month: startOfMonth() moves the instance it is called on, so the copy taken
 * for the far end of the window started from the first of the month instead
 * of from today. The customers who most needed the warning — the ones whose
 * card expires next month — were exactly the ones who never got it.
 */
function cardExpiring(string $yearMonth): PaymentMethod
{
    return PaymentMethod::create([
        'client_id' => Client::factory()->create(['email' => "holder-{$yearMonth}@example.com"])->id,
        'description' => 'Visa',
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'last_four' => '4242',
        'expiry_date' => $yearMonth,
    ]);
}

test('a card expiring next month is warned about', function () {
    Mail::fake();

    $card = cardExpiring(now()->addMonth()->format('Y-m'));

    $this->artisan('pnlcs:cc-expiry-alerts')->assertSuccessful();

    Mail::assertQueued(CreditCardExpiryMail::class, fn ($mail) => $mail->hasTo($card->client->email));
});

test('a card expiring this month is warned about', function () {
    Mail::fake();

    $card = cardExpiring(now()->format('Y-m'));

    $this->artisan('pnlcs:cc-expiry-alerts')->assertSuccessful();

    Mail::assertQueued(CreditCardExpiryMail::class, fn ($mail) => $mail->hasTo($card->client->email));
});

test('a card with a year left is left alone', function () {
    Mail::fake();

    cardExpiring(now()->addYear()->format('Y-m'));

    $this->artisan('pnlcs:cc-expiry-alerts')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('a card that expired last year is left alone', function () {
    Mail::fake();

    cardExpiring(now()->subYear()->format('Y-m'));

    $this->artisan('pnlcs:cc-expiry-alerts')->assertSuccessful();

    Mail::assertNothingQueued();
});
