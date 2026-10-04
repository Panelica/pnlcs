<?php

use App\Events\InvoiceCancelled;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Hooks WHMCS addons rely on, which pnlcs never ran: EmailPreSend (see or stop
 * a mail before it leaves), InvoiceCancelled, ClientEdit, ClientDelete,
 * ShoppingCartValidateCheckout and DailyCronJob.
 */

test('EmailPreSend sees every outgoing mail and can stop it', function () {
    $seen = [];
    add_hook('EmailPreSend', function ($vars) use (&$seen) {
        $seen[] = $vars['subject'];

        return str_contains($vars['subject'], 'blocked') ? ['abortsend' => true] : null;
    });

    $sentOk = Mail::raw('Hello', fn ($m) => $m->to('a@example.test')->subject('Welcome'));
    $sentBlocked = Mail::raw('Hello', fn ($m) => $m->to('b@example.test')->subject('A blocked one'));

    expect($seen)->toBe(['Welcome', 'A blocked one'])
        ->and($sentOk)->not->toBeNull()
        ->and($sentBlocked)->toBeNull();
});

test('cancelling an invoice raises InvoiceCancelled and runs its hook', function () {
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'Unpaid', 'total' => 10]);
    $hooked = [];
    add_hook('InvoiceCancelled', function ($vars) use (&$hooked) {
        $hooked[] = $vars['invoice']->id;
    });

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect($hooked)->toBe([$invoice->id]);
});

test('ClientEdit runs when the account details change, not when only the balance moves; ClientDelete runs before removal', function () {
    $client = Client::factory()->create(['city' => 'Ankara', 'credit' => 0]);
    $edits = [];
    $deleted = [];
    add_hook('ClientEdit', function ($vars) use (&$edits) {
        $edits[] = [$vars['userid'], array_keys($vars['changes']), $vars['olddata']];
    });
    add_hook('ClientDelete', function ($vars) use (&$deleted) {
        $deleted[] = $vars['userid'];
    });

    $client->update(['city' => 'İzmir']);
    $client->increment('credit', 5);
    $client->delete();

    expect($edits)->toBe([[$client->id, ['city'], ['city' => 'Ankara']]])
        ->and($deleted)->toBe([$client->id]);
});

test('ShoppingCartValidateCheckout can refuse an order with a message', function () {
    add_hook('ShoppingCartValidateCheckout', fn () => 'We do not ship to the moon.');

    \App\Models\GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'active'], ['value' => '1']);
    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
    $user->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => 1])
        ->assertSessionHasErrors(['checkout' => 'We do not ship to the moon.']);
});

test('DailyCronJob is scheduled once a day', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => $e->description === 'hook:DailyCronJob');

    expect($event)->not->toBeNull()->and($event->expression)->toBe('10 0 * * *');
});
