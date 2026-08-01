<?php

use App\Mail\SslCertificateExpiringMail;
use App\Models\Client;
use App\Models\SslOrder;
use Illuminate\Support\Facades\Mail;

/**
 * Telling a customer their certificate is about to expire.
 *
 * The command looked for certificates expiring on exactly the day thirty,
 * fourteen, seven, three or one days out. Miss a run - a deploy, a stopped
 * scheduler, a slow morning - and that notice was gone for good; nothing
 * recorded what had been sent, so no later run could make it up.
 *
 * A certificate that arrived with twenty days left never got the thirty-day
 * notice either, because that day was already behind it.
 */
function expiringCertificate(int $daysLeft): SslOrder
{
    return SslOrder::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => "expiring-{$daysLeft}.test",
        'module' => 'gogetssl',
        'status' => 'Completed',
        'order_date' => now()->subYear(),
        'crt_expires' => now()->addDays($daysLeft),
    ]);
}

test('a certificate is still chased after the exact day was missed', function () {
    Mail::fake();

    // Thirteen days left: the fourteen-day run did not happen.
    $order = expiringCertificate(13);

    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();

    Mail::assertQueued(SslCertificateExpiringMail::class, fn ($mail) => $mail->hasTo($order->client->email));
});

test('the same notice is not sent twice', function () {
    Mail::fake();
    expiringCertificate(13);

    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();
    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();

    Mail::assertQueued(SslCertificateExpiringMail::class, 1);
});

test('a nearer deadline is a new notice', function () {
    Mail::fake();
    $order = expiringCertificate(13);

    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();

    $order->update(['crt_expires' => now()->addDays(2)]);
    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();

    Mail::assertQueued(SslCertificateExpiringMail::class, 2);
});

test('a certificate with months left is left alone', function () {
    Mail::fake();
    expiringCertificate(120);

    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('an expired certificate is marked as such', function () {
    Mail::fake();
    $order = expiringCertificate(-2);

    $this->artisan('pnlcs:ssl-expiry-check')->assertSuccessful();

    expect($order->fresh()->status)->toBe('Expired');
});
