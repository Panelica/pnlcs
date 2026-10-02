<?php

use App\Mail\ConfirmationCodeMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\SensitiveActionConfirmation;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * A domain's transfer code and its registrar lock ask for an emailed code.
 *
 * A signed-in session was enough to read the EPP code (or unlock the domain)
 * and take the name to another registrar: a shared computer or a stolen
 * session cookie was all it took. Logins opened through Google have no
 * password, so the check is the mailbox.
 */

function csaDomain(): array
{
    // The check is off by default; these tests are about it switched on.
    Setting::set('ConfirmSensitiveActions', '1');
    $user = User::factory()->create(['email' => 'owner@example.test']);
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'brand.com', 'registrar' => 'Manual', 'status' => 'active']);

    return [$user, $domain];
}

/** Sends a code and returns it, read from the mail that carried it. */
function csaCode(User $user): string
{
    $code = null;
    Mail::fake();
    test()->actingAs($user)->post(route('client.confirm.send'))->assertRedirect(route('client.confirm.show'));
    Mail::assertSent(ConfirmationCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return $mail->hasTo('owner@example.test');
    });

    return $code;
}

it('sends the customer to confirm before showing the transfer code', function () {
    [$user, $domain] = csaDomain();

    test()->actingAs($user)->get(route('client.domains.epp', $domain))->assertRedirect(route('client.confirm.show'));
    test()->actingAs($user)->getJson(route('client.domains.epp', $domain))->assertForbidden()->assertJsonPath('confirm_url', route('client.confirm.show'));
    test()->actingAs($user)->post(route('client.domains.lock', $domain))->assertRedirect(route('client.confirm.show'));
});

it('lets the customer through with the emailed code, back to where they were going', function () {
    [$user, $domain] = csaDomain();

    test()->actingAs($user)->get(route('client.domains.epp', $domain));
    $code = csaCode($user);

    test()->actingAs($user)->post(route('client.confirm.verify'), ['code' => $code])
        ->assertRedirect(route('client.domains.epp', $domain));
    test()->actingAs($user)->get(route('client.domains.epp', $domain))->assertRedirect(route('client.domains.show', $domain));
});

it('keeps the confirmation for a few minutes only', function () {
    [$user, $domain] = csaDomain();
    $code = csaCode($user);
    test()->actingAs($user)->post(route('client.confirm.verify'), ['code' => $code]);

    $this->travel(SensitiveActionConfirmation::WINDOW_MINUTES - 1)->minutes();
    test()->actingAs($user)->getJson(route('client.domains.epp', $domain))->assertOk();

    $this->travel(2)->minutes();
    test()->actingAs($user)->getJson(route('client.domains.epp', $domain))->assertForbidden();
});

it('refuses a wrong code and gives up after five tries', function () {
    [$user, $domain] = csaDomain();
    $code = csaCode($user);

    foreach (range(1, SensitiveActionConfirmation::MAX_ATTEMPTS) as $i) {
        test()->actingAs($user)->post(route('client.confirm.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
    }
    test()->actingAs($user)->post(route('client.confirm.verify'), ['code' => $code])->assertSessionHasErrors('code');

    test()->actingAs($user)->getJson(route('client.domains.epp', $domain))->assertForbidden();
});

it('does not accept an expired code', function () {
    [$user, $domain] = csaDomain();
    $code = csaCode($user);

    $this->travel(SensitiveActionConfirmation::CODE_MINUTES + 1)->minutes();
    test()->actingAs($user)->post(route('client.confirm.verify'), ['code' => $code])->assertSessionHasErrors('code');
});

it('keeps the code out of the mail history', function () {
    [$user] = csaDomain();

    test()->actingAs($user)->post(route('client.confirm.send'));

    $logged = Email::where('to', 'like', '%owner@example.test%')->latest('id')->first();
    expect($logged)->not->toBeNull()
        ->and($logged->message)->not->toMatch('/\b\d{6}\b/');
});

it('still sends the code when its template is switched off', function () {
    [$user] = csaDomain();
    EmailTemplate::updateOrCreate(['name' => 'Confirmation Code', 'language' => 'en'], [
        'type' => 'general', 'subject' => 'Your code - {CompanyName}', 'message' => '{confirmation_code}', 'disabled' => true,
    ]);

    // Not Mail::fake(): the fake replaces the mailer, and the listener that
    // applies (or switches off) a template would never run.
    $sent = new ArrayObject;
    Event::listen(MessageSent::class, fn ($e) => $sent->append($e->message->getTo()[0]->getAddress()));

    test()->actingAs($user)->post(route('client.confirm.send'))->assertRedirect(route('client.confirm.show'));

    expect($sent->getArrayCopy())->toBe(['owner@example.test']);
});

it('changes nothing while the operator has not switched it on', function () {
    [$user, $domain] = csaDomain();
    Setting::set('ConfirmSensitiveActions', '0');

    test()->actingAs($user)->getJson(route('client.domains.epp', $domain))->assertOk();
    test()->actingAs($user)->get(route('client.domains.epp', $domain))->assertRedirect(route('client.domains.show', $domain));
});

it('is switched on and off from the settings form', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'S', 'permissions' => ['manage_settings']])->id]);
    Setting::set('ConfirmSensitiveActions', '0');

    test()->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()->assertSee('name="ConfirmSensitiveActions"', false);
    test()->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['ConfirmSensitiveActions' => '1'])->assertRedirect();

    expect(SensitiveActionConfirmation::enabled())->toBeTrue();
});
