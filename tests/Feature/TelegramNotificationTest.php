<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;

/**
 * Telegram as a notification channel.
 *
 * Email is read tomorrow; an order at 2am, or a card that was declined, is
 * worth a buzz in somebody's pocket tonight. These cover the whole path an
 * operator walks: create the provider on the settings screen, prove it with a
 * test message, and have a real event reach the channel — plus the two ways
 * the old screen leaked or destroyed the bot token.
 */
function telegramAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Settings',
            'permissions' => ['manage_settings'],
        ])->id,
    ]);
}

function telegramProvider(array $settings = []): NotificationProvider
{
    return NotificationProvider::create([
        'name' => 'Ops channel',
        'type' => 'telegram',
        'active' => true,
        'settings' => $settings + ['bot_token' => '123:AAtoken', 'chat_id' => '-1001234567890'],
    ]);
}

test('the settings screen accepts a telegram provider', function () {
    $this->actingAs(telegramAdmin(), 'admin')
        ->post(route('admin.config.notification-providers.store'), [
            'name' => 'Ops channel',
            'type' => 'telegram',
            'active' => '1',
            'settings' => ['bot_token' => '123:AAtoken', 'chat_id' => '-100999'],
        ])
        ->assertSessionHasNoErrors();

    expect(NotificationProvider::where('type', 'telegram')->count())->toBe(1);
});

test('a subscribed event reaches the channel', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $provider = telegramProvider();
    NotificationRule::create(['provider_id' => $provider->id, 'event' => 'order.placed', 'active' => true]);

    app(NotificationService::class)->dispatch('order.placed', [
        'event_type' => 'order.placed',
        'subject' => 'New Order Placed',
        'message' => 'Order #1001 placed by Ada Lovelace',
        'order_id' => 7,
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/bot123:AAtoken/sendMessage')
            && $request['chat_id'] === '-1001234567890'
            && str_contains($request['text'], 'New Order Placed')
            && str_contains($request['text'], 'Order #1001')
            // The alert carries a way straight into the panel.
            && str_contains($request['text'], '/admin/orders/7');
    });
});

test('a provider with no token stays silent instead of calling telegram', function () {
    Http::fake();

    $provider = telegramProvider(['bot_token' => '']);
    $provider->update(['settings' => ['bot_token' => '', 'chat_id' => '-100']]);
    NotificationRule::create(['provider_id' => $provider->id, 'event' => 'order.placed', 'active' => true]);

    app(NotificationService::class)->dispatch('order.placed', ['subject' => 'x', 'message' => 'y']);

    Http::assertNothingSent();
});

test('the test button reports what telegram said', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

    $provider = telegramProvider();

    $this->actingAs(telegramAdmin(), 'admin')
        ->post(route('admin.config.notification-providers.test', $provider->id))
        ->assertSessionHas('error');
});

test('the test button confirms a working channel', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $provider = telegramProvider();

    $this->actingAs(telegramAdmin(), 'admin')
        ->post(route('admin.config.notification-providers.test', $provider->id))
        ->assertSessionHas('success');
});

test('the bot token never reaches the page source', function () {
    telegramProvider(['bot_token' => 'SECRET-TOKEN-VALUE']);

    $this->actingAs(telegramAdmin(), 'admin')
        ->get(route('admin.config.notifications'))
        ->assertOk()
        ->assertDontSee('SECRET-TOKEN-VALUE');
});

test('renaming a provider does not wipe its bot token', function () {
    $provider = telegramProvider(['bot_token' => 'keep-me']);
    $provider->update(['settings' => ['bot_token' => 'keep-me', 'chat_id' => '-100']]);

    $this->actingAs(telegramAdmin(), 'admin')
        ->put(route('admin.config.notification-providers.update', $provider->id), [
            'name' => 'Renamed channel',
            'type' => 'telegram',
            'active' => '1',
            'settings' => ['bot_token' => '', 'chat_id' => '-100'],
        ])
        ->assertSessionHasNoErrors();

    expect($provider->fresh()->settings['bot_token'])->toBe('keep-me');
});

test('a failed payment can be subscribed to', function () {
    expect(NotificationService::eventTypes())->toContain('payment.failed');
});
