<?php

use App\Listeners\SuppressMailWhenDisabled;
use App\Mail\AccountSignupMail;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/**
 * The general settings page has a "Mail Enabled" switch. It was written to the
 * database but nothing read it, so an operator who turned mail off kept mailing
 * customers anyway — on this installation the switch was off while mail flowed.
 *
 * Note when reading these: every mailable here implements ShouldQueue, so a
 * delivery lands in Mail::queued(), not Mail::sent(). Measuring the wrong
 * bucket is how the switch first looked like it worked.
 */
function signupMailFor(): array
{
    $client = Client::factory()->create();

    return [$client, new AccountSignupMail($client)];
}

test('mail flows when the switch is on', function () {
    Setting::set('MailEnabled', '1', 'general');
    Mail::fake();
    [$client, $mailable] = signupMailFor();

    Mail::to($client->email)->queue($mailable);

    expect(Mail::queued(AccountSignupMail::class))->toHaveCount(1);
});

test('mail flows when the switch was never set', function () {
    Setting::where('setting', 'MailEnabled')->delete();
    Mail::fake();
    [$client, $mailable] = signupMailFor();

    Mail::to($client->email)->queue($mailable);

    // A fresh install, or one that never touched the switch, must behave as before.
    expect(Mail::queued(AccountSignupMail::class))->toHaveCount(1);
});

test('the listener cancels delivery when the switch is off', function () {
    Setting::set('MailEnabled', '0', 'general');

    // The listener is what the mailer consults just before handing a message to
    // the transport; a false return cancels the send.
    $listener = app(SuppressMailWhenDisabled::class);
    $message = (new Email)
        ->from('panel@example.com')
        ->to('customer@example.com')
        ->subject('Invoice #1')
        ->text('body');

    expect($listener->handle(new MessageSending($message)))->toBeFalse();

    Setting::set('MailEnabled', '1', 'general');
    // null means allow: the event halts on any other return value, which
    // would switch off every mail hook registered after this one.
    expect($listener->handle(new MessageSending($message)))->toBeNull();
});

test('the listener is registered on the sending event', function () {
    expect(Event::hasListeners(MessageSending::class))->toBeTrue();
});

test('unreadable settings never swallow mail', function () {
    Setting::where('setting', 'MailEnabled')->delete();
    $listener = app(SuppressMailWhenDisabled::class);
    $message = (new Email)
        ->from('panel@example.com')->to('customer@example.com')->subject('x')->text('y');

    // null means allow: the event halts on any other return value, which
    // would switch off every mail hook registered after this one.
    expect($listener->handle(new MessageSending($message)))->toBeNull();
});
