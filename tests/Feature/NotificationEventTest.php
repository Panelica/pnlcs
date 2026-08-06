<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use App\Services\NotificationService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;

/**
 * The events an operator can be told about.
 *
 * The notification screen offered nine event types — the nine a listener
 * translates from business events. Five more are dispatched by the system
 * itself and were missing from the list, so no rule could be created for them
 * and the messages went nowhere: a failed nightly backup, provisioning that
 * gave up on a server, and a bank transfer sitting in the review queue all
 * arrived in silence.
 */
function notificationsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Settings',
            'permissions' => ['manage_settings'],
        ])->id,
    ]);
}

test('every dispatched event can be subscribed to', function () {
    $dispatched = ['backup.failed', 'module.failed', 'module.failed_permanently', 'order.awaiting_acceptance', 'payment.notification_received'];

    expect(NotificationService::eventTypes())->toContain(...$dispatched);
});

test('the screen offers them', function () {
    $this->actingAs(notificationsAdmin(), 'admin')
        ->get(route('admin.config.notifications'))
        ->assertOk()
        ->assertSee('backup.failed')
        ->assertSee('module.failed_permanently')
        ->assertSee('payment.notification_received');
});

test('a rule for a failed backup reaches the operator', function () {
    $recipients = new ArrayObject;

    Event::listen(
        MessageSent::class,
        function ($event) use ($recipients) {
            foreach ($event->message->getTo() as $address) {
                $recipients->append($address->getAddress());
            }
        }
    );

    $provider = NotificationProvider::create([
        'name' => 'Ops mailbox',
        'type' => 'email',
        'settings' => [],
        'active' => true,
    ]);

    NotificationRule::create([
        'provider_id' => $provider->id,
        'event' => 'backup.failed',
        'conditions' => ['recipient_email' => 'ops@example.test'],
        'active' => true,
    ]);

    app(NotificationService::class)->dispatch('backup.failed', [
        'event_type' => 'backup.failed',
        'subject' => 'Database backup FAILED',
        'message' => 'mysqldump exited 1',
    ]);

    expect($recipients->getArrayCopy())->toContain('ops@example.test');
});

/**
 * The two the domain work added.
 *
 * A registrar that refuses to renew, and a registry that refuses to register a
 * domain the customer has paid for, both raise an alert - and neither event
 * was on the list the screen offers, so no rule could be created for them and
 * the alerts went nowhere. The same gap this file was written to close.
 */
test('the domain failures can be subscribed to as well', function () {
    expect(NotificationService::eventTypes())
        ->toContain('domain.renew_failed', 'domain.registration_failed');
});

test('the screen offers the domain failures', function () {
    $this->actingAs(notificationsAdmin(), 'admin')
        ->get(route('admin.config.notifications'))
        ->assertOk()
        ->assertSee('domain.renew_failed')
        ->assertSee('domain.registration_failed');
});
