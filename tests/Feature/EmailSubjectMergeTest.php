<?php

use App\Mail\DomainRegistrationMail;
use App\Mail\TicketOpenedMail;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailTemplate;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * The placeholders the operator can see in a template subject.
 *
 * The seeded subjects say {domain} and {ticket_id}, and the code offered
 * domain_name and ticket_tid. Nothing matched, so every domain confirmation
 * and every support email went out with the braces still in the subject line —
 * "Domain Registration Confirmation: {domain}".
 */
function sentSubjects(): ArrayObject
{
    $subjects = new ArrayObject;

    Event::listen(MessageSent::class, function ($event) use ($subjects) {
        $subjects->append($event->message->getSubject());
    });

    return $subjects;
}

test('a domain email says which domain', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Domain Registration Confirmation'],
        ['type' => 'domain', 'subject' => 'Domain Registration Confirmation: {domain}', 'message' => 'x', 'disabled' => false, 'custom' => false]
    );

    $client = Client::factory()->create();
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'example-registered.com']);

    $subjects = sentSubjects();
    Mail::to($client->email)->send(new DomainRegistrationMail($domain));

    expect($subjects->getArrayCopy())->toContain('Domain Registration Confirmation: example-registered.com');
});

test('a support email says which ticket', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Support Ticket Opened'],
        ['type' => 'support', 'subject' => '[{ticket_id}] {ticket_subject}', 'message' => 'x', 'disabled' => false, 'custom' => false]
    );

    $client = Client::factory()->create();
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'tid' => '482913',
        'title' => 'Site is down',
    ]);

    $subjects = sentSubjects();
    Mail::to($client->email)->send(new TicketOpenedMail($ticket));

    expect($subjects->getArrayCopy())->toContain('[482913] Site is down');
});

test('no subject goes out with braces left in it', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Domain Registration Confirmation'],
        ['type' => 'domain', 'subject' => 'Domain Registration Confirmation: {domain}', 'message' => 'x', 'disabled' => false, 'custom' => false]
    );

    $client = Client::factory()->create();
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'braces-check.com']);

    $subjects = sentSubjects();
    Mail::to($client->email)->send(new DomainRegistrationMail($domain));

    foreach ($subjects as $subject) {
        expect($subject)->not->toMatch('/\{[a-zA-Z_]+\}/');
    }
});
