<?php

use App\Models\Admin;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;

/**
 * An email notification rule with nowhere to send.
 *
 * The rule form takes an event, a provider and a recipient address, and only
 * the first two are required. An email rule saved without an address is
 * accepted, listed as active, and then sends nothing at all: the dispatcher
 * looks for a recipient, does not find one, and returns.
 *
 * So an operator sets up alerts for failed backups or failed provisioning,
 * sees the rule sitting there in the list, and never hears a thing.
 */
function notificationAdmin(): Admin
{
    return Admin::factory()->create();
}

function emailProvider(): NotificationProvider
{
    return NotificationProvider::create([
        'name' => 'Email alerts',
        'type' => 'email',
        'settings' => [],
        'active' => true,
    ]);
}

it('refuses an email rule with nowhere to send', function () {
    $provider = emailProvider();

    $this->actingAs(notificationAdmin(), 'admin')
        ->post(route('admin.config.notification-rules.store'), [
            'event' => 'backup.failed',
            'provider_id' => $provider->id,
            'active' => 1,
        ])->assertSessionHasErrors();

    expect(NotificationRule::where('event', 'backup.failed')->count())->toBe(0);
});

it('accepts an email rule that has an address', function () {
    $provider = emailProvider();

    $this->actingAs(notificationAdmin(), 'admin')
        ->post(route('admin.config.notification-rules.store'), [
            'event' => 'backup.failed',
            'provider_id' => $provider->id,
            'conditions' => ['recipient_email' => 'ops@test.local'],
            'active' => 1,
        ])->assertSessionHasNoErrors();

    $rule = NotificationRule::where('event', 'backup.failed')->firstOrFail();

    expect($rule->conditions['recipient_email'] ?? null)->toBe('ops@test.local');
});

it('does not require an address for a channel that does not need one', function () {
    $slack = NotificationProvider::create([
        'name' => 'Slack alerts',
        'type' => 'slack',
        'settings' => ['webhook_url' => 'https://example.com/hook'],
        'active' => true,
    ]);

    $this->actingAs(notificationAdmin(), 'admin')
        ->post(route('admin.config.notification-rules.store'), [
            'event' => 'module.failed',
            'provider_id' => $slack->id,
            'active' => 1,
        ])->assertSessionHasNoErrors();

    expect(NotificationRule::where('event', 'module.failed')->count())->toBe(1);
});
