<?php

use App\Models\Ticket;
use App\Models\TicketDepartment;

/**
 * The public contact form posting into a department taken out of service.
 *
 * The page lists departments that are not hidden, and then accepts any
 * department id that exists. Hiding a department is how an operator takes a
 * queue out of service or keeps an internal one off the customer-facing list -
 * and anyone at all can post this form, so the listing is the only thing that
 * was standing in the way of a ticket landing in a queue nobody is watching,
 * while the sender is told their message was received.
 *
 * The logged-in ticket form, one controller over, has always asked the harder
 * question - Rule::exists(...)->where('hidden', false) - and says in a comment
 * why: not merely a department that exists, one the customer was offered.
 */
function contactFormDepartment(bool $hidden): TicketDepartment
{
    return TicketDepartment::create([
        'name' => $hidden ? 'Internal Escalations' : 'General',
        'email' => 'dept@example.test',
        'hidden' => $hidden,
        'sort_order' => 1,
    ]);
}

function postContactEnquiry(TicketDepartment $department, array $payload = [])
{
    return test()->post(route('client.contact.submit'), array_merge([
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'A question',
        'message' => 'Please get back to me.',
    ], $payload));
}

it('refuses a department the form never offered', function () {
    $hidden = contactFormDepartment(true);

    postContactEnquiry($hidden)->assertSessionHasErrors('department_id');

    expect(Ticket::where('department_id', $hidden->id)->count())->toBe(0);
});

it('still accepts a department the form does offer', function () {
    $open = contactFormDepartment(false);

    postContactEnquiry($open)->assertSessionHasNoErrors();

    expect(Ticket::where('department_id', $open->id)->count())->toBe(1);
});

it('still keeps a hidden department off the contact page', function () {
    $hidden = contactFormDepartment(true);

    test()->get(route('client.contact'))->assertOk()->assertDontSee('Internal Escalations');
});

it('still refuses a department that does not exist at all', function () {
    $open = contactFormDepartment(false);

    postContactEnquiry($open, ['department_id' => 99999])->assertSessionHasErrors('department_id');
});

it('still refuses a message with no email address', function () {
    $open = contactFormDepartment(false);

    postContactEnquiry($open, ['email' => ''])->assertSessionHasErrors('email');
});
