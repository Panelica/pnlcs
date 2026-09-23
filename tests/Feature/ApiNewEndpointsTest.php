<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\ClientSsoToken;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProjectTask;
use App\Models\Service;
use App\Models\SslModuleSettings;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
 * The endpoints that answered 501 until 2026-09-23 and now do their work,
 * each through code the panel already uses: the template mails, the mass-mail
 * mailable, the notification rules, the module settings rules, the product
 * creator, "log in as this client". Plus the client-area pieces they needed:
 * one-time sign-in links, invitations, and per-login permissions.
 */

function newEpStaff(array $permissions): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => $permissions])->id]);
}

function newEpAdmin(array $attrs = []): Admin
{
    $role = AdminRole::where('is_full_admin', true)->first() ?? AdminRole::factory()->fullAdmin()->create();

    return Admin::factory()->create($attrs + ['role_id' => $role->id]);
}

function newEpHeaders(Admin $admin): array
{
    $secret = Str::random(64);
    $cred = ApiCredential::create(['admin_id' => $admin->id, 'identifier' => Str::random(32), 'secret' => ApiCredential::hashSecret($secret), 'description' => 't', 'active' => true]);

    return ['X-API-Key' => $cred->identifier, 'X-API-Secret' => $secret, 'Accept' => 'application/json'];
}

function newEpClientWithOwner(): array
{
    $client = Client::factory()->create();
    $owner = User::factory()->create(['email' => 'owner-'.Str::random(6).'@example.com']);
    $owner->clients()->attach($client->id, ['owner' => true]);

    return [$client, $owner];
}

// ---------------------------------------------------------------- mail ----

test('sendemail sends a template mail built from the record, and a custom one', function () {
    Mail::fake();
    $h = newEpHeaders(newEpAdmin());
    $client = Client::factory()->create(['email' => 'cust@example.com']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    $this->withHeaders($h)->post('/api/v1/sendemail', ['messagename' => 'Invoice Created', 'id' => $invoice->id])->assertOk();
    Mail::assertQueued(\App\Mail\InvoiceCreatedMail::class, fn ($m) => $m->invoice->is($invoice));

    $this->withHeaders($h)->post('/api/v1/sendemail', ['customsubject' => 'Hello', 'custommessage' => 'Body text', 'id' => $client->id])->assertOk();
    Mail::assertQueued(\App\Mail\BulkMassMail::class, fn ($m) => $m->mailSubject === 'Hello' && $m->hasTo('cust@example.com'));

    $this->withHeaders($h)->post('/api/v1/sendemail', ['messagename' => 'Email Verification', 'id' => $client->id])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/sendemail', ['messagename' => 'Invoice Created', 'id' => 987654321])->assertNotFound();
});

test('sendemail and sendadminemail need the mass-mail permission', function () {
    $h = newEpHeaders(newEpStaff(['list_clients']));

    $this->withHeaders($h)->post('/api/v1/sendemail', ['customsubject' => 's', 'custommessage' => 'm', 'id' => 1])->assertForbidden();
    $this->withHeaders($h)->post('/api/v1/sendadminemail', ['customsubject' => 's', 'custommessage' => 'm'])->assertForbidden();
});

test('sendadminemail reaches active staff only', function () {
    Mail::fake();
    Admin::query()->update(['is_disabled' => true]);
    $h = newEpHeaders(newEpAdmin(['email' => 'boss@example.com', 'is_disabled' => false]));
    newEpAdmin(['email' => 'gone@example.com', 'is_disabled' => true]);

    $this->withHeaders($h)->post('/api/v1/sendadminemail', ['customsubject' => 'Heads up', 'custommessage' => 'x'])->assertOk()->assertJson(['recipients' => 1]);
    Mail::assertQueued(\App\Mail\BulkMassMail::class, fn ($m) => $m->hasTo('boss@example.com'));
    Mail::assertNotQueued(\App\Mail\BulkMassMail::class, fn ($m) => $m->hasTo('gone@example.com'));
});

// ------------------------------------------------------- notifications ----

test('triggernotificationevent goes out through the rules set for api.custom', function () {
    Http::fake(['hooks.example.test/*' => Http::response('ok')]);
    $provider = NotificationProvider::create(['name' => 'hook', 'type' => 'webhook', 'settings' => ['url' => 'https://hooks.example.test/in'], 'active' => true]);
    NotificationRule::create(['event' => 'api.custom', 'provider_id' => $provider->id, 'active' => true]);

    $this->withHeaders(newEpHeaders(newEpAdmin()))
        ->post('/api/v1/triggernotificationevent', ['title' => 'Deploy done', 'message' => 'Version 2 is live'])
        ->assertOk()->assertJson(['rules' => 1]);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.example.test') && str_contains($r->body(), 'Deploy done'));
    expect(\App\Services\NotificationService::eventTypes())->toContain('api.custom');
});

// ------------------------------------------------------ module settings ----

test('module parameters are described without values, and updated under the screen\'s rules', function () {
    $h = newEpHeaders(newEpAdmin());
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => 'sk_live_keepme']);

    $params = $this->withHeaders($h)->get('/api/v1/getmoduleconfigurationparameters?moduleType=gateway&moduleName=stripe')->assertOk();
    expect($params->getContent())->not->toContain('sk_live_keepme')
        ->and(collect($params->json('parameters'))->pluck('name'))->toContain('secret_key');

    $this->withHeaders($h)->post('/api/v1/updatemoduleconfiguration', ['moduleType' => 'gateway', 'moduleName' => 'stripe', 'parameters' => ['secret_key' => '', 'publishable_key' => 'pk_test_new']])->assertOk();

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'secret_key')->first()->value)->toBe('sk_live_keepme')
        ->and(GatewaySettings::where('gateway', 'stripe')->where('setting', 'publishable_key')->first()->value)->toBe('pk_test_new');
});

test('changing a gateway\'s settings needs manage_gateways, as on its screen', function () {
    $h = newEpHeaders(newEpStaff(['manage_settings', 'view_system']));

    $this->withHeaders($h)->post('/api/v1/updatemoduleconfiguration', ['moduleType' => 'gateway', 'moduleName' => 'stripe', 'parameters' => ['publishable_key' => 'x']])->assertForbidden();
});

// ----------------------------------------------------------- products ----

test('addproduct creates a catalogue product the way the product screen does', function () {
    $h = newEpHeaders(newEpAdmin());
    $group = ProductGroup::factory()->create();
    $currency = Currency::first() ?? Currency::factory()->create();

    $pid = $this->withHeaders($h)->post('/api/v1/addproduct', [
        'name' => 'API Plan', 'gid' => $group->id, 'type' => 'hostingaccount', 'paytype' => 'recurring',
        'pricing' => [$currency->id => ['monthly' => 4.99]],
    ])->assertOk()->json('pid');

    $product = Product::find($pid);
    expect($product->type)->toBe('hosting')
        ->and($product->slug)->toBe('api-plan')
        ->and((float) Pricing::where('type', 'product')->where('rel_id', $pid)->where('currency_id', $currency->id)->value('monthly'))->toBe(4.99)
        ->and(Pricing::where('type', 'product')->where('rel_id', $pid)->count())->toBe(Currency::count());
});

// ------------------------------------------------------------- timers ----

test('task timers keep time, one running timer per task and person', function () {
    $h = newEpHeaders(newEpAdmin());
    $task = ProjectTask::factory()->create();

    $timerId = $this->withHeaders($h)->post('/api/v1/starttasktimer', ['taskid' => $task->id])->assertOk()->json('timerid');
    $this->withHeaders($h)->post('/api/v1/starttasktimer', ['taskid' => $task->id])->assertStatus(409);

    $this->travel(90)->seconds();
    $end = $this->withHeaders($h)->post('/api/v1/endtasktimer', ['timerid' => $timerId])->assertOk();

    expect($end->json('seconds'))->toBeGreaterThanOrEqual(90);
    $timers = $this->withHeaders($h)->get('/api/v1/getproject?projectid='.$task->project_id)->json('project.tasks.0.timers');
    expect($timers)->toHaveCount(1);
});

// ----------------------------------------------------- module functions ----

test('modulecustom names the functions a module offers instead of pretending', function () {
    $service = Service::factory()->create();

    $this->withHeaders(newEpHeaders(newEpAdmin()))
        ->post('/api/v1/modulecustom', ['serviceid' => $service->id, 'func_name' => 'reboot'])
        ->assertNotFound()
        ->assertJsonFragment(['result' => 'error']);
});

// ---------------------------------------------------------------- SSO ----

test('an SSO link signs the login in once, into the right account, and never twice', function () {
    [$client, $owner] = newEpClientWithOwner();
    $h = newEpHeaders(newEpAdmin(['username' => 'sso_issuer']));

    $url = $this->withHeaders($h)->post('/api/v1/createssotoken', ['client_id' => $client->id, 'destination' => 'clientarea:invoices'])->assertOk()->json('redirect_url');
    $path = parse_url($url, PHP_URL_PATH);
    auth('admin')->logout();

    $this->get($path)->assertRedirect(route('client.invoices.index', [], false));
    expect(auth()->id())->toBe($owner->id)->and(session('active_client_id'))->toBe($client->id);
    expect(\App\Models\ActivityLog::where('client_id', $client->id)->where('user', 'sso_issuer')->exists())->toBeTrue();

    auth()->logout();
    $this->get($path)->assertRedirect(route('client.login'));
    expect(auth()->check())->toBeFalse();
});

test('an SSO link stops working after a minute, and never leaves the client area', function () {
    [$client] = newEpClientWithOwner();
    $h = newEpHeaders(newEpAdmin());

    $this->withHeaders($h)->post('/api/v1/createssotoken', ['client_id' => $client->id, 'destination' => 'sso:custom_redirect', 'sso_redirect_path' => 'https://evil.example/'])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/createssotoken', ['client_id' => $client->id, 'destination' => 'sso:custom_redirect', 'sso_redirect_path' => '//evil.example/'])->assertStatus(422);

    $url = $this->withHeaders($h)->post('/api/v1/createssotoken', ['client_id' => $client->id])->json('redirect_url');
    auth('admin')->logout();
    $this->travel(61)->seconds();
    $this->get(parse_url($url, PHP_URL_PATH))->assertRedirect(route('client.login'));

    expect(ClientSsoToken::first()->getAttributes()['token_hash'])->not->toBe(basename($url));
});

test('issuing an SSO link needs the edit_clients permission', function () {
    [$client] = newEpClientWithOwner();

    $this->withHeaders(newEpHeaders(newEpStaff(['list_clients'])))->post('/api/v1/createssotoken', ['client_id' => $client->id])->assertForbidden();
});

// ------------------------------------------ invitations and permissions ----

test('an invitation is mailed, accepted with a new login, and the login is held to its permissions', function () {
    Mail::fake();
    [$client] = newEpClientWithOwner();
    $h = newEpHeaders(newEpAdmin());

    $this->withHeaders($h)->post('/api/v1/createclientinvite', ['client_id' => $client->id, 'email' => 'Helper@Example.com', 'permissions' => 'tickets'])->assertOk();

    $link = null;
    Mail::assertQueued(\App\Mail\BulkMassMail::class, function ($m) use (&$link) {
        preg_match('#https?://\S+/client/invite/\S+#', $m->mailBody, $found);
        $link = $found[0] ?? null;

        return $m->hasTo('helper@example.com');
    });
    expect($link)->not->toBeNull();
    auth('admin')->logout();
    $path = parse_url($link, PHP_URL_PATH);

    $this->get($path)->assertOk()->assertSee('helper@example.com');
    $this->post($path, ['first_name' => 'Hel', 'last_name' => 'Per', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])
        ->assertRedirect(route('client.home'));

    $helper = User::where('email', 'helper@example.com')->first();
    expect(auth()->id())->toBe($helper->id)
        ->and(UserInvite::first()->accepted_at)->not->toBeNull();

    // Tickets yes, invoices no.
    $this->get(route('client.tickets.index'))->assertOk();
    $this->get(route('client.invoices.index'))->assertForbidden();

    // The link is spent.
    auth()->logout();
    $this->get($path)->assertRedirect(route('client.login'));
});

test('an invitation for an address that has a login is accepted only by that login, signed in', function () {
    Mail::fake();
    [$client] = newEpClientWithOwner();
    $existing = User::factory()->create(['email' => 'has-login@example.com']);
    $plain = Str::random(64);
    UserInvite::create(['token' => hash('sha256', $plain), 'email' => 'has-login@example.com', 'client_id' => $client->id, 'invited_by' => 1, 'permissions' => ['invoices']]);

    // Not signed in: the link alone attaches nothing.
    $this->post('/client/invite/'.$plain)->assertRedirect(route('client.login'));
    expect($existing->clients()->whereKey($client->id)->exists())->toBeFalse();

    $this->actingAs($existing)->withSession(['2fa_verified' => true])->post('/client/invite/'.$plain)->assertRedirect(route('client.home'));
    expect($existing->clients()->whereKey($client->id)->exists())->toBeTrue();
});

test('owners and unrestricted logins keep every permission; restrictions are read and written', function () {
    [$client, $owner] = newEpClientWithOwner();
    $member = User::factory()->create();
    $member->clients()->attach($client->id, ['owner' => false, 'permissions' => null]);
    $h = newEpHeaders(newEpAdmin());

    expect($this->withHeaders($h)->get("/api/v1/getuserpermissions?user_id={$member->id}&client_id={$client->id}")->json('permissions'))
        ->toBe(\App\Support\ClientPermissions::ALL);

    $this->withHeaders($h)->post('/api/v1/updateuserpermissions', ['user_id' => $member->id, 'client_id' => $client->id, 'permissions' => 'invoices,quotes'])->assertOk();
    $this->withHeaders($h)->post('/api/v1/updateuserpermissions', ['user_id' => $member->id, 'client_id' => $client->id, 'permissions' => 'invoices,nuke'])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/updateuserpermissions', ['user_id' => $owner->id, 'client_id' => $client->id, 'permissions' => 'invoices'])->assertStatus(422);

    expect($this->withHeaders($h)->get("/api/v1/getuserpermissions?user_id={$member->id}&client_id={$client->id}")->json('permissions'))->toBe(['invoices', 'quotes']);

    auth('admin')->logout();
    $this->actingAs($member)->withSession(['2fa_verified' => true, 'active_client_id' => $client->id])->get(route('client.tickets.index'))->assertForbidden();
    $this->actingAs($owner)->withSession(['2fa_verified' => true, 'active_client_id' => $client->id])->get(route('client.tickets.index'))->assertOk();
});

// ---------------------------------------------------- found on the way ----

test('the reset-password page does not echo what the URL carries as markup', function () {
    // No "/" in the payload: it would split the path instead of reaching the page.
    $this->get('/client/reset-password/tok%22%3E%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E?email=%22%3E%3Cimg%20src%3Dy%20onerror%3Dalert(2)%3E')
        ->assertOk()
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertDontSee('<img src=y onerror=alert(2)>', false);
});

test('the SSL provider password is stored encrypted, never shown, and kept when left blank', function () {
    $admin = newEpAdmin();
    SslModuleSettings::setSetting('gogetssl', 'api_password', 'ggssl-secret-pw');

    $raw = DB::table('ssl_module_settings')->where('module', 'gogetssl')->where('setting', 'api_password')->value('value');
    expect($raw)->not->toBe('ggssl-secret-pw')
        ->and(SslModuleSettings::getForModule('gogetssl')['api_password'])->toBe('ggssl-secret-pw');

    $this->actingAs($admin, 'admin')->get(route('admin.config.sslModules'))->assertOk()->assertDontSee('ggssl-secret-pw');

    $this->actingAs($admin, 'admin')->post(route('admin.config.updateSslModuleSettings', 'gogetssl'), ['settings' => ['api_username' => 'me', 'api_password' => '']]);
    expect(SslModuleSettings::getForModule('gogetssl')['api_password'])->toBe('ggssl-secret-pw')
        ->and(SslModuleSettings::getForModule('gogetssl')['api_username'])->toBe('me');
});
