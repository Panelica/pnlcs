<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;

/*
 * A notification rule could be added and deleted, not changed: a typo in the
 * recipient, a move from email to Slack, or switching a noisy rule off for a
 * while all meant deleting it and building it again. Rules can now be edited,
 * under the same checks as a new one.
 */

function nreAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function nreSetup(): array
{
    $email = NotificationProvider::create(['name' => 'Ops mail', 'type' => 'email', 'settings' => [], 'active' => true]);
    $slack = NotificationProvider::create(['name' => 'Ops Slack', 'type' => 'slack', 'settings' => ['webhook_url' => 'https://hooks.slack.test/x'], 'active' => true]);
    $rule = NotificationRule::create(['event' => 'backup.failed', 'provider_id' => $email->id, 'conditions' => ['recipient_email' => 'ops@exmaple.com'], 'active' => true]);

    return [$email, $slack, $rule];
}

test('a rule\'s event, channel, recipient and switch can be changed', function () {
    [$email, $slack, $rule] = nreSetup();

    $this->actingAs(nreAdmin(), 'admin')->put(route('admin.config.notification-rules.update', $rule->id), [
        'event' => 'module.failed', 'provider_id' => $email->id, 'conditions' => ['recipient_email' => 'ops@example.com'], 'active' => '1',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($rule->fresh()->event)->toBe('module.failed')
        ->and($rule->fresh()->conditions['recipient_email'])->toBe('ops@example.com');

    $this->actingAs(nreAdmin(), 'admin')->put(route('admin.config.notification-rules.update', $rule->id), [
        'event' => 'module.failed', 'provider_id' => $slack->id,
    ])->assertSessionHasNoErrors();
    expect($rule->fresh()->provider_id)->toBe($slack->id)->and($rule->fresh()->active)->toBeFalsy();
});

test('an email rule still needs somewhere to send', function () {
    [$email, , $rule] = nreSetup();

    $this->actingAs(nreAdmin(), 'admin')->put(route('admin.config.notification-rules.update', $rule->id), [
        'event' => 'backup.failed', 'provider_id' => $email->id, 'conditions' => ['recipient_email' => ''], 'active' => '1',
    ])->assertSessionHasErrors('conditions.recipient_email');

    expect($rule->fresh()->conditions['recipient_email'])->toBe('ops@exmaple.com');
});

test('each rule has an edit form, filled in', function () {
    [, , $rule] = nreSetup();

    $this->actingAs(nreAdmin(), 'admin')->get(route('admin.config.notifications'))->assertOk()
        ->assertSee(route('admin.config.notification-rules.update', $rule->id), false)
        ->assertSee('value="ops@exmaple.com"', false)
        ->assertSee(__('admin.notifications.edit_rule'));
});
