<?php

use App\Mail\BulkMassMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\MarketingConsent;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * Marketing email only to customers who agreed, with a way to stop.
 *
 * marketing_consents existed and nothing used it: no box at signup, nothing
 * on the profile, and the mass mail went to everyone selected with no
 * unsubscribe link - a marketing campaign could not be sent lawfully.
 */

function mcSignup(array $extra = [])
{
    return test()->post(route('client.register.submit'), array_merge([
        'first_name' => 'New', 'last_name' => 'Customer', 'email' => 'new@example.test',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'tos' => '1',
    ], $extra));
}

function mcClient(string $email, ?bool $consent = null): Client
{
    $client = Client::factory()->create(['email' => $email]);
    if ($consent !== null) {
        MarketingConsent::record($client, $consent, 'account', '203.0.113.9');
    }

    return $client;
}

function mcAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Mail', 'permissions' => ['manage_email_templates']])->id]);
}

it('records consent only when the signup box is ticked, with the proof', function () {
    mcSignup(['marketing_emails' => '1'])->assertSessionHasNoErrors();
    $client = Client::where('email', 'new@example.test')->sole();
    $consent = MarketingConsent::where('client_id', $client->id)->sole();

    expect($consent->email_opt_in)->toBeTrue()
        ->and($consent->source)->toBe('signup')
        ->and($consent->ip_address)->not->toBeNull()
        ->and($consent->consented_at)->not->toBeNull();
});

it('records nothing when the box is left unticked', function () {
    mcSignup()->assertSessionHasNoErrors();

    expect(MarketingConsent::count())->toBe(0);
});

it('lets the customer give and withdraw it on the profile', function () {
    $user = User::factory()->create();
    $client = mcClient('me@example.test');
    $user->clients()->attach($client->id, ['owner' => true]);

    $base = ['first_name' => $client->first_name, 'last_name' => $client->last_name, 'email' => $user->email, 'country' => 'TR', 'marketing_section' => 1];
    test()->actingAs($user)->put(route('client.account.update'), $base + ['marketing_emails' => 1])->assertRedirect();
    expect(MarketingConsent::optedIn($client))->toBeTrue();

    test()->actingAs($user)->put(route('client.account.update'), $base)->assertRedirect();
    $consent = MarketingConsent::where('client_id', $client->id)->sole();
    expect($consent->email_opt_in)->toBeFalse()->and($consent->withdrawn_at)->not->toBeNull();
});

it('sends a marketing message only to those who agreed, each with a way out', function () {
    Mail::fake();
    $yes = mcClient('yes@example.test', true);
    $no = mcClient('no@example.test', false);
    $never = mcClient('never@example.test');

    test()->actingAs(mcAdmin(), 'admin')->post(route('admin.bulk.mass-email.send'), [
        'client_ids' => [$yes->id, $no->id, $never->id], 'subject' => 'Autumn offer', 'message' => '20% off', 'marketing' => 1,
    ])->assertSessionHas('success');

    Mail::assertQueued(BulkMassMail::class, 1);
    Mail::assertQueued(BulkMassMail::class, fn ($m) => $m->hasTo('yes@example.test')
        && str_contains((string) $m->unsubscribeUrl, '/unsubscribe/'.$yes->id)
        && $m->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click');
});

it('still sends a service notice to everyone selected', function () {
    Mail::fake();
    $no = mcClient('no@example.test', false);
    $never = mcClient('never@example.test');

    test()->actingAs(mcAdmin(), 'admin')->post(route('admin.bulk.mass-email.send'), [
        'client_ids' => [$no->id, $never->id], 'subject' => 'Maintenance on Sunday', 'message' => 'Short downtime.',
    ]);

    Mail::assertQueued(BulkMassMail::class, 2);
    Mail::assertQueued(BulkMassMail::class, fn ($m) => $m->unsubscribeUrl === null);
});

it('unsubscribes through the signed link, after a click and not on opening it', function () {
    $client = mcClient('yes@example.test', true);
    $url = MarketingConsent::unsubscribeUrl($client);

    test()->get($url)->assertOk()->assertSee(__('client.marketing.unsubscribe_button'));
    expect(MarketingConsent::optedIn($client))->toBeTrue();

    test()->post($url)->assertOk();
    $consent = MarketingConsent::where('client_id', $client->id)->sole();
    expect($consent->email_opt_in)->toBeFalse()->and($consent->source)->toBe('unsubscribe');
});

it('refuses an unsigned or altered link', function () {
    $client = mcClient('yes@example.test', true);
    $other = mcClient('other@example.test', true);

    test()->post(route('client.unsubscribe.store', ['client' => $client->id]))->assertForbidden();
    test()->post(str_replace('/unsubscribe/'.$client->id, '/unsubscribe/'.$other->id, MarketingConsent::unsubscribeUrl($client)))->assertForbidden();

    expect(MarketingConsent::optedIn($client))->toBeTrue()->and(MarketingConsent::optedIn($other))->toBeTrue();
});
