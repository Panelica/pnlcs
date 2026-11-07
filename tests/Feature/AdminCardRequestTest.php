<?php

use App\Mail\CardSetupRequestMail;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\GatewaySettings;
use App\Models\Language;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Services\EmailTemplateService;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Staff signed in as a customer cannot store a card for them
 * (StaffSessionCardConsentTest): the card and the agreement to charge it are
 * the cardholder's. That left staff with no way to get a card onto an account
 * at all. The client page now sends the customer the link to their own Add
 * card page, so the customer enters the card and the consent stays theirs.
 */

function cardRequestShopOn(): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach (['active' => '1', 'publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
}

function cardRequestStaff(array $permissions = ['view_clients', 'list_clients', 'edit_clients']): Admin
{
    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => $permissions]);

    return Admin::factory()->create(['role_id' => $role->id, 'username' => 'billing']);
}

test('staff can email the customer the link to add a card', function () {
    cardRequestShopOn();
    Mail::fake();
    $client = Client::factory()->create(['email' => 'card-owner@example.test']);

    $this->actingAs(cardRequestStaff(), 'admin')
        ->from(route('admin.clients.show', $client))
        ->post(route('admin.clients.card-request', $client))
        ->assertRedirect(route('admin.clients.show', $client))
        ->assertSessionHas('success');

    Mail::assertQueued(CardSetupRequestMail::class, fn ($mail) => $mail->hasTo('card-owner@example.test') && $mail->client->is($client));
    expect(ActivityLog::where('client_id', $client->id)->where('description', 'like', '%link to add a card%')->exists())->toBeTrue();
});

test('the email carries the link to the customer\'s own Add card page', function () {
    $client = Client::factory()->create(['first_name' => 'Deniz']);

    $html = (new CardSetupRequestMail($client))->render();

    expect($html)->toContain(route('client.payment-methods.add-card'))
        ->and($html)->toContain(e(__('email.common.greeting', ['name' => 'Deniz'])));
});

test('nothing is sent where the customer could not add a card', function () {
    Setting::set('AutoChargeEnabled', '0');
    Mail::fake();
    $client = Client::factory()->create();

    $this->actingAs(cardRequestStaff(), 'admin')
        ->post(route('admin.clients.card-request', $client))
        ->assertSessionHas('error');

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

test('staff without the right to change clients cannot send it', function () {
    cardRequestShopOn();
    Mail::fake();
    $client = Client::factory()->create();

    $this->actingAs(cardRequestStaff(['view_clients', 'list_clients']), 'admin')
        ->post(route('admin.clients.card-request', $client))
        ->assertForbidden();

    Mail::assertNothingQueued();
});

test('the client page offers the button only where a card can be stored', function () {
    $client = Client::factory()->create();
    $staff = cardRequestStaff();

    $off = $this->actingAs($staff, 'admin')->get(route('admin.clients.show', $client))->assertOk()->getContent();
    expect($off)->not->toContain(route('admin.clients.card-request', $client));

    cardRequestShopOn();
    $on = $this->actingAs($staff, 'admin')->get(route('admin.clients.show', $client))->assertOk()->getContent();
    expect($on)->toContain(route('admin.clients.card-request', $client))
        ->and($on)->toContain(e(__('admin.clients.stored_cards')));
});

test('a customer who is signed out is taken to the Add card page after signing in', function () {
    cardRequestShopOn();

    $this->get(route('client.payment-methods.add-card'))->assertRedirect();

    expect(session('url.intended'))->toBe(route('client.payment-methods.add-card'));
});

test('the email is bound to a template the operator can reword', function () {
    (new EmailTemplateSeeder)->run();
    $client = Client::factory()->create(['first_name' => 'Deniz', 'last_name' => 'Kaya', 'email' => 'deniz@example.test']);
    EmailTemplate::where('name', 'Card Setup Request')->where('language', 'en')
        ->update(['custom' => true, 'message' => "Hello {client_name}, add your card here: {add_card_url}"]);

    $sent = new ArrayObject;
    Event::listen(MessageSent::class, fn ($event) => $sent->append($event->message));
    Mail::to($client->email)->send(new CardSetupRequestMail($client));

    expect(app(EmailTemplateService::class)->forMailable(CardSetupRequestMail::class)?->name)->toBe('Card Setup Request')
        ->and($sent[0]->getTextBody())->toBe('Hello Deniz Kaya, add your card here: '.route('client.payment-methods.add-card'));
});

test('an existing install gets the template in every language it has', function () {
    (new EmailTemplateSeeder)->run();
    Language::where('code', 'de')->delete();
    Language::create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_active' => true]);
    EmailTemplate::where('name', 'Card Setup Request')->delete();

    $migration = require database_path('migrations/2026_11_07_000001_seed_card_setup_request_template.php');
    $migration->up();
    $migration->up();

    expect(EmailTemplate::where('name', 'Card Setup Request')->where('language', 'en')->count())->toBe(1)
        ->and(EmailTemplate::where('name', 'Card Setup Request')->where('language', 'de')->value('subject'))
        ->toStartWith('Bitte hinterlegen Sie eine Zahlungskarte');
});

test('the client page counts the customer\'s cards and nothing else', function () {
    cardRequestShopOn();
    $client = Client::factory()->create();
    PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'stripe', 'payment_type' => 'cc', 'last_four' => '4242', 'remote_token' => 'pm_1']);
    PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'iyzico', 'payment_type' => 'card', 'last_four' => '0008', 'remote_token' => 'ct_1']);
    PaymentMethod::create(['client_id' => $client->id, 'payment_type' => 'BankAccount', 'description' => 'TR00 0000']);
    PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'paypal', 'payment_type' => 'paypal', 'remote_token' => 'pp_1']);

    $this->actingAs(cardRequestStaff(), 'admin')->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertViewHas('storedCardCount', 2);
});
