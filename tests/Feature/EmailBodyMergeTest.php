<?php

use App\Mail\AffiliateWelcomeMail;
use App\Mail\PasswordResetMail;
use App\Mail\ServiceSuspensionMail;
use App\Mail\TicketReplyMail;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * The placeholders an operator can put in a template body.
 *
 * A body only goes out once the operator has customised it, which is why these
 * had gone unnoticed — but the moment somebody edited the password reset
 * template, the customer would have been sent the literal text {reset_url}
 * instead of the link they were waiting for.
 */
function sentBodies(): ArrayObject
{
    $bodies = new ArrayObject;

    Event::listen(MessageSent::class, function ($event) use ($bodies) {
        $bodies->append(quoted_printable_decode($event->message->getBody()->bodyToString()));
    });

    return $bodies;
}

function customTemplate(string $name, string $type, string $body): void
{
    EmailTemplate::updateOrCreate(
        ['name' => $name],
        ['type' => $type, 'subject' => $name, 'message' => $body, 'disabled' => false, 'custom' => true]
    );
}

test('a password reset carries the link, not the word for it', function () {
    customTemplate('Password Reset Confirmation', 'general', 'Use this link: {reset_url}');

    $bodies = sentBodies();
    Mail::to('someone@example.test')->send(new PasswordResetMail('https://panel.example/reset/abc123', 'someone@example.test'));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('https://panel.example/reset/abc123');
});

test('a suspension says why', function () {
    customTemplate('Service Suspension', 'product', 'Suspended because: {suspend_reason}');

    $client = Client::factory()->create();
    $service = Service::factory()->create(['client_id' => $client->id]);

    $bodies = sentBodies();
    Mail::to($client->email)->send(new ServiceSuspensionMail($service, 'Invoice #42 unpaid'));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('Invoice #42 unpaid');
});

test('a ticket reply carries the reply', function () {
    customTemplate('Support Ticket Reply', 'support', 'Our answer: {ticket_reply}');

    $client = Client::factory()->create();
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
    ]);

    $bodies = sentBodies();
    Mail::to($client->email)->send(new TicketReplyMail($ticket, 'We have restarted the server.', true));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('We have restarted the server.');
});

test('an affiliate welcome carries the referral link', function () {
    customTemplate('Affiliate Welcome Email', 'general', 'Share this: {affiliate_link}');

    $client = Client::factory()->create();
    $affiliate = Affiliate::create([
        'client_id' => $client->id,
        'visitors' => 0,
        'pay_type' => 'percentage',
        'pay_amount' => 10,
        'onetime' => false,
        'balance' => 0,
        'withdrawn' => 0,
    ]);

    $bodies = sentBodies();
    Mail::to($client->email)->send(new AffiliateWelcomeMail($client, $affiliate));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('ref='.$affiliate->id);
});
