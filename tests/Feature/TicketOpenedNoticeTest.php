<?php

use App\Events\TicketOpened;
use App\Mail\TicketOpenedMail;
use App\Models\ApiCredential;
use App\Models\Setting;
use App\Models\TicketDepartment;
use App\Models\TicketSpamFilter;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * A ticket opened with nobody told about it.
 *
 * Opening a ticket raises TicketOpened, which acknowledges it to whoever wrote
 * in and alerts the support address. The client area's form raises it and the
 * mail import raises it. The public contact form and the API's openticket
 * created the ticket and raised nothing.
 *
 * The contact form is the one that matters: it is the door for people who are
 * not customers yet. Their enquiry landed in the panel with no acknowledgement
 * to them and no alert to anybody, so it sat there until somebody happened to
 * look at the ticket list.
 */
function noticeDepartment(): TicketDepartment
{
    return TicketDepartment::create([
        'name' => 'Sales '.uniqid(),
        'email' => 'sales@example.com',
        'hidden' => false,
    ]);
}

function submitContactForm(TicketDepartment $department): void
{
    test()->post(route('client.contact.submit'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'Pre-sales question',
        'message' => 'Do you offer daily backups?',
    ])->assertRedirect();
}

function openTicketOverApi(TicketDepartment $department): void
{
    $credential = ApiCredential::factory()->create();

    test()->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->postJson('/api/v1/openticket', [
        'deptid' => $department->id,
        'subject' => 'Opened over the api',
        'message' => 'Please look into this.',
        'email' => 'api@example.test',
    ])->assertSuccessful();
}

it('acknowledges a contact form enquiry to the person who sent it', function () {
    Mail::fake();
    Setting::set('Email', 'support@company.test');

    submitContactForm(noticeDepartment());

    Mail::assertQueued(TicketOpenedMail::class, fn ($mail) => $mail->hasTo('visitor@example.test'));
});

it('alerts the support address about a contact form enquiry', function () {
    Mail::fake();
    Setting::set('Email', 'support@company.test');

    submitContactForm(noticeDepartment());

    Mail::assertQueued(TicketOpenedMail::class, fn ($mail) => $mail->hasTo('support@company.test'));
});

it('raises the same event for an api ticket', function () {
    Event::fake([TicketOpened::class]);

    openTicketOverApi(noticeDepartment());

    Event::assertDispatched(TicketOpened::class);
});

it('says nothing about a message the spam filter turned away', function () {
    Mail::fake();
    Setting::set('Email', 'support@company.test');
    TicketSpamFilter::create(['type' => 'keyword', 'content' => 'crypto casino']);

    $department = noticeDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Spammer',
        'email' => 'spam@example.test',
        'department_id' => $department->id,
        'subject' => 'Best crypto casino offer',
        'message' => 'Click here',
    ])->assertRedirect();

    Mail::assertNothingQueued();
});
