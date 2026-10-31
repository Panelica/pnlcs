<?php

use App\Events\TicketOpened;
use App\Mail\TicketOpenedMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Support\Facades\Mail;

/**
 * Telling the staff of a department about a new ticket.
 *
 * The alert for a ticket a client opened went to the company address and
 * nowhere else. Admin has had support_departments and ticket_notifications
 * from the start, but no form set them and nothing read them, so a member of
 * staff could not be told about the tickets of the department they handle.
 */
function staffAlertTicket(TicketDepartment $department): Ticket
{
    return Ticket::create([
        'tid' => (string) random_int(100000, 999999),
        'department_id' => $department->id,
        'client_id' => Client::factory()->create()->id,
        'name' => 'Aylin Kaya',
        'email' => 'aylin@example.test',
        'title' => 'Mail delivery is failing',
        'message' => 'Nothing leaves the server.',
        'status' => 'Open',
        'priority' => 'medium',
        'last_reply' => now(),
    ]);
}

function staffAlertAdmin(array $attributes = []): Admin
{
    return Admin::factory()->create(array_merge([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
        'is_disabled' => false,
        'ticket_notifications' => true,
        'support_departments' => [],
    ], $attributes));
}

/** @return list<string> every address a staff copy of the new-ticket mail was queued to */
function staffAlertRecipients(): array
{
    $to = [];
    Mail::assertQueued(TicketOpenedMail::class, function (TicketOpenedMail $mail) use (&$to) {
        if ($mail->isAdmin) {
            foreach ($mail->to as $recipient) {
                $to[] = $recipient['address'];
            }
        }

        return true;
    });
    sort($to);

    return $to;
}

beforeEach(function () {
    Mail::fake();
    Setting::set('Email', 'office@example.test', 'general');
});

it('tells the staff of the ticket department, and the company address as before', function () {
    $sales = TicketDepartment::create(['name' => 'Sales '.uniqid()]);
    $support = TicketDepartment::create(['name' => 'Support '.uniqid()]);
    staffAlertAdmin(['email' => 'sales@example.test', 'support_departments' => [$sales->id]]);
    staffAlertAdmin(['email' => 'support@example.test', 'support_departments' => [$support->id]]);
    staffAlertAdmin(['email' => 'nowhere@example.test']);

    event(new TicketOpened(staffAlertTicket($sales)));

    expect(staffAlertRecipients())->toBe(['office@example.test', 'sales@example.test']);
});

it('leaves out staff who switched the alert off, and disabled staff', function () {
    $department = TicketDepartment::create(['name' => 'Support '.uniqid()]);
    staffAlertAdmin(['email' => 'muted@example.test', 'support_departments' => [$department->id], 'ticket_notifications' => false]);
    staffAlertAdmin(['email' => 'gone@example.test', 'support_departments' => [$department->id], 'is_disabled' => true]);

    event(new TicketOpened(staffAlertTicket($department)));

    expect(staffAlertRecipients())->toBe(['office@example.test']);
});

it('does not mail the company address twice when a member of staff uses it', function () {
    $department = TicketDepartment::create(['name' => 'Support '.uniqid()]);
    staffAlertAdmin(['email' => 'Office@example.test', 'support_departments' => [$department->id]]);

    event(new TicketOpened(staffAlertTicket($department)));

    expect(staffAlertRecipients())->toBe(['office@example.test']);
});

it('tells no staff about a ticket a member of staff opened', function () {
    $department = TicketDepartment::create(['name' => 'Support '.uniqid()]);
    staffAlertAdmin(['email' => 'support@example.test', 'support_departments' => [$department->id]]);

    event(new TicketOpened(staffAlertTicket($department), true));

    expect(staffAlertRecipients())->toBe([]);
});

it('saves the departments and the alert switch from the staff form', function () {
    $actor = staffAlertAdmin();
    $department = TicketDepartment::create(['name' => 'Support '.uniqid()]);
    $admin = staffAlertAdmin(['email' => 'edited@example.test']);

    $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $admin), [
        'first_name' => $admin->first_name,
        'last_name' => $admin->last_name,
        'username' => $admin->username,
        'email' => $admin->email,
        'role_id' => $admin->role_id,
        'notification_settings' => '1',
        'support_departments' => [(string) $department->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $admin->refresh();
    expect($admin->support_departments)->toBe([$department->id])
        ->and($admin->ticket_notifications)->toBeFalse();
});

it('keeps the departments when a request does not carry the alert settings', function () {
    $actor = staffAlertAdmin();
    $department = TicketDepartment::create(['name' => 'Support '.uniqid()]);
    $admin = staffAlertAdmin(['email' => 'kept@example.test', 'support_departments' => [$department->id]]);

    $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $admin), [
        'first_name' => 'Renamed',
        'last_name' => $admin->last_name,
        'username' => $admin->username,
        'email' => $admin->email,
        'role_id' => $admin->role_id,
    ])->assertSessionHasNoErrors();

    $admin->refresh();
    expect($admin->support_departments)->toBe([$department->id])
        ->and($admin->ticket_notifications)->toBeTrue();
});

it('creates a member of staff with departments from the staff form', function () {
    $actor = staffAlertAdmin();
    $department = TicketDepartment::create(['name' => 'Support '.uniqid()]);

    $this->actingAs($actor, 'admin')->post(route('admin.config.admins.store'), [
        'first_name' => 'New',
        'last_name' => 'Agent',
        'username' => 'agent'.uniqid(),
        'email' => 'agent@example.test',
        'password' => 'secret123',
        'role_id' => $actor->role_id,
        'notification_settings' => '1',
        'support_departments' => [(string) $department->id],
        'ticket_notifications' => '1',
    ])->assertSessionHasNoErrors();

    $admin = Admin::where('email', 'agent@example.test')->first();
    expect($admin->support_departments)->toBe([$department->id])
        ->and($admin->ticket_notifications)->toBeTrue();
});

it('shows the departments on the staff form', function () {
    $actor = staffAlertAdmin();
    $department = TicketDepartment::create(['name' => 'Billing desk '.uniqid()]);

    $this->actingAs($actor, 'admin')->get(route('admin.config.admins'))
        ->assertOk()
        ->assertSee($department->name)
        ->assertSee(__('admin.admins.ticket_notifications'));
});
