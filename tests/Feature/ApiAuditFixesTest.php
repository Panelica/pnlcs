<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\BannedEmail;
use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\ModuleQueue;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketNote;
use App\Models\TicketReply;
use App\Models\TicketSpamFilter;
use App\Models\TicketStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DomainAvailability;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
 * The findings of the API audit of 2026-09-23, one test each.
 *
 * Every one of these was a call that answered - most of them with
 * "success" - while doing something other than what it said: a key minted in
 * the owner's name, a customer filter that returned every customer, a staff
 * reply filed as the customer's, a "blocked" sender who could still open
 * tickets, a disabled account whose key still worked.
 */

function auditStaff(array $permissions, array $attrs = []): Admin
{
    return Admin::factory()->create($attrs + [
        'password' => bcrypt('audit-password'),
        'role_id' => AdminRole::factory()->create([
            'is_full_admin' => false,
            'permissions' => $permissions,
        ])->id,
    ]);
}

function auditFullAdmin(array $attrs = []): Admin
{
    $role = AdminRole::where('is_full_admin', true)->first() ?? AdminRole::factory()->fullAdmin()->create();

    return Admin::factory()->create($attrs + ['role_id' => $role->id, 'password' => bcrypt('audit-password')]);
}

function auditHeaders(Admin $admin): array
{
    $secret = Str::random(64);
    $cred = ApiCredential::create([
        'admin_id' => $admin->id,
        'identifier' => Str::random(32),
        'secret' => ApiCredential::hashSecret($secret),
        'description' => 'audit',
        'active' => true,
    ]);

    return ['X-API-Key' => $cred->identifier, 'X-API-Secret' => $secret, 'Accept' => 'application/json'];
}

// ---------------------------------------------------------------- auth ----

test('a disabled account cannot use its API key or its password', function () {
    $admin = auditFullAdmin(['is_disabled' => true, 'username' => 'gone_staff']);

    $this->withHeaders(auditHeaders($admin))->get('/api/v1/getclients')->assertForbidden();
    $this->getJson('/api/v1/getclients?username=gone_staff&password=audit-password')->assertForbidden();
});

test('an account with two-factor cannot sign in to the API with its password alone', function () {
    auditFullAdmin(['username' => 'twofactor_staff', 'second_factor_type' => 'totp', 'second_factor_secret' => 'JBSWY3DPEHPK3PXP']);
    auditFullAdmin(['username' => 'plain_staff']);

    $this->getJson('/api/v1/getstats?username=twofactor_staff&password=audit-password')
        ->assertStatus(401)
        ->assertJsonFragment(['result' => 'error']);

    // The WHMCS-compatible password path still works for an account without it.
    $this->getJson('/api/v1/getstats?username=plain_staff&password=audit-password')->assertOk();
});

// ---------------------------------------------------------- permissions ---

test('a new API credential belongs to the caller, and needs manage_staff', function () {
    $owner = auditFullAdmin();          // first in line, the one it used to go to
    $staffManager = auditStaff(['manage_staff']);
    $settingsOnly = auditStaff(['manage_settings']);

    $this->withHeaders(auditHeaders($settingsOnly))->post('/api/v1/createoauthcredential')->assertForbidden();

    $id = $this->withHeaders(auditHeaders($staffManager))
        ->post('/api/v1/createoauthcredential', ['description' => 'mine'])
        ->assertOk()
        ->json('credentialid');

    expect(ApiCredential::find($id)->admin_id)->toBe($staffManager->id)
        ->and(ApiCredential::find($id)->admin_id)->not->toBe($owner->id);
});

test('projects, quotes and affiliates answer to their own permissions', function () {
    $project = Project::factory()->create();
    $viewSystem = auditHeaders(auditStaff(['view_system', 'manage_settings']));

    $this->withHeaders($viewSystem)->get('/api/v1/getproject?projectid='.$project->id)->assertForbidden();
    $this->withHeaders($viewSystem)->post('/api/v1/createquote', ['clientid' => $project->client_id])->assertForbidden();
    $this->withHeaders($viewSystem)->get('/api/v1/getaffiliates')->assertForbidden();

    $this->withHeaders(auditHeaders(auditStaff(['list_projects'])))
        ->get('/api/v1/getproject?projectid='.$project->id)->assertOk();
});

// -------------------------------------------------------- attribution ----

test('log entries, client notes and ticket notes are signed by the caller', function () {
    $admin = auditFullAdmin(['username' => 'real_author']);
    $h = auditHeaders($admin);
    $client = Client::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->withHeaders($h)->post('/api/v1/logactivity', ['description' => 'audit entry', 'user' => 'someone_else'])->assertOk();
    $this->withHeaders($h)->post('/api/v1/addclientnote', ['clientid' => $client->id, 'note' => 'n', 'adminusername' => 'someone_else'])->assertOk();
    $this->withHeaders($h)->post('/api/v1/addticketnote', ['ticketid' => $ticket->id, 'message' => 'm', 'adminusername' => 'someone_else'])->assertOk();

    expect(ActivityLog::where('description', 'audit entry')->value('user'))->toBe('real_author')
        ->and(ClientNote::where('client_id', $client->id)->value('admin'))->toBe('real_author')
        ->and(TicketNote::where('ticket_id', $ticket->id)->value('admin'))->toBe('real_author');

    // And none of them may be empty.
    $this->withHeaders($h)->post('/api/v1/logactivity', [])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/addclientnote', ['clientid' => $client->id])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/addticketnote', ['ticketid' => $ticket->id])->assertStatus(422);
});

test('a staff reply is filed as staff, signed by the caller, and marks the ticket answered', function () {
    Mail::fake();
    $h = auditHeaders(auditFullAdmin(['username' => 'support_lead']));
    $ticket = Ticket::factory()->create(['status' => 'Open']);

    $this->withHeaders($h)->post('/api/v1/addticketreply', ['ticketid' => $ticket->id, 'message' => 'We are on it', 'adminusername' => 'x'])->assertOk();

    $reply = TicketReply::where('ticket_id', $ticket->id)->latest('id')->first();
    expect($reply->admin)->toBe('support_lead')
        ->and($ticket->fresh()->status)->toBe('Answered');

    // Without adminusername it is the customer writing.
    $this->withHeaders($h)->post('/api/v1/addticketreply', ['ticketid' => $ticket->id, 'message' => 'Thanks'])->assertOk();
    expect($ticket->fresh()->status)->toBe('Customer-Reply');
});

test('project and project message are recorded under the caller, with a status the screens know', function () {
    $admin = auditFullAdmin(['username' => 'pm_owner']);
    $h = auditHeaders($admin);
    $client = Client::factory()->create();

    $id = $this->withHeaders($h)->post('/api/v1/createproject', ['title' => 'Site', 'clientid' => $client->id])->assertOk()->json('projectid');
    $this->withHeaders($h)->post('/api/v1/addprojectmessage', ['projectid' => $id, 'message' => 'hello'])->assertOk();

    $project = Project::find($id);
    expect($project->admin_id)->toBe($admin->id)
        ->and($project->status)->toBe('pending')
        ->and($project->messages()->value('admin'))->toBe('pm_owner');

    $this->withHeaders($h)->post('/api/v1/updateproject', ['projectid' => $id, 'status' => 'active'])->assertStatus(422);
});

// ------------------------------------------------------------ filters ----

test('customer filters named clientid return that customer only', function () {
    $h = auditHeaders(auditFullAdmin());
    [$a, $b] = [Client::factory()->create(), Client::factory()->create()];
    $serviceA = Service::factory()->create(['client_id' => $a->id]);
    Service::factory()->create(['client_id' => $b->id]);
    Domain::factory()->create(['client_id' => $a->id]);
    Domain::factory()->create(['client_id' => $b->id]);
    Transaction::factory()->create(['client_id' => $a->id]);
    Transaction::factory()->create(['client_id' => $b->id]);

    $ids = fn ($r) => collect($r->json('data'))->pluck('client_id')->unique()->values()->all();

    expect($ids($this->withHeaders($h)->get('/api/v1/getclientsproducts?clientid='.$a->id)))->toBe([$a->id])
        ->and($ids($this->withHeaders($h)->get('/api/v1/getclientsdomains?clientid='.$a->id)))->toBe([$a->id])
        ->and($ids($this->withHeaders($h)->get('/api/v1/gettransactions?clientid='.$a->id)))->toBe([$a->id]);

    $one = $this->withHeaders($h)->get('/api/v1/getclientsproducts?serviceid='.$serviceA->id)->json('data');
    expect($one)->toHaveCount(1)->and($one[0]['id'])->toBe($serviceA->id);
});

test('startnumber says where the returned page really begins', function () {
    $h = auditHeaders(auditFullAdmin());
    Client::factory()->count(6)->create();

    $r = $this->withHeaders($h)->get('/api/v1/getclients?limitnum=5&limitstart=3')->json();

    // limitstart 3 falls in the first page of five: rows 0-4 come back, so
    // the page starts at 0, not at the 3 that was asked for.
    expect($r['startnumber'])->toBe(0)->and($r['numreturned'])->toBe(5);
});

// ------------------------------------------------------------ clients ----

test('deleteuserclient takes a login off one account and keeps the login', function () {
    $h = auditHeaders(auditFullAdmin());
    $user = User::factory()->create();
    [$a, $b] = [Client::factory()->create(), Client::factory()->create()];
    $user->clients()->attach([$a->id, $b->id]);

    $this->withHeaders($h)->post('/api/v1/deleteuserclient', ['userid' => $user->id, 'clientid' => $a->id])->assertOk();

    expect(User::find($user->id))->not->toBeNull()
        ->and($user->clients()->pluck('clients.id')->all())->toBe([$b->id]);
});

test('adduser refuses an address already in use with a 422, not a 500', function () {
    $h = auditHeaders(auditFullAdmin());
    $existing = User::factory()->create();

    $this->withHeaders($h)->post('/api/v1/adduser', ['email' => $existing->email, 'password' => 'long-enough-1', 'first_name' => 'A', 'last_name' => 'B'])
        ->assertStatus(422);
});

// ----------------------------------------------------------- invoices ----

test('getpaymethods lists the stored methods, without the token', function () {
    $h = auditHeaders(auditFullAdmin());
    $client = Client::factory()->create();
    PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'stripe', 'payment_type' => 'card', 'last_four' => '4242', 'card_brand' => 'visa', 'exp_month' => 4, 'exp_year' => 2030, 'remote_token' => 'pm_secret_token', 'gateway_customer_id' => 'cus_secret', 'is_default' => true]);

    $r = $this->withHeaders($h)->get('/api/v1/getpaymethods?clientid='.$client->id)->assertOk();

    expect($r->json('paymethods'))->toHaveCount(1)
        ->and($r->json('paymethods.0.card_last_four'))->toBe('4242')
        ->and($r->getContent())->not->toContain('pm_secret_token')
        ->and($r->getContent())->not->toContain('cus_secret');
});

test('geninvoices refuses a filter it would have ignored', function () {
    $h = auditHeaders(auditFullAdmin());
    $client = Client::factory()->create();
    $before = Invoice::count();

    $this->withHeaders($h)->post('/api/v1/geninvoices', ['clientid' => $client->id])->assertStatus(422);

    expect(Invoice::count())->toBe($before);
});

test('addtransaction will not point at another customer\'s invoice', function () {
    $h = auditHeaders(auditFullAdmin());
    [$a, $b] = [Client::factory()->create(), Client::factory()->create()];
    $invoiceOfB = Invoice::factory()->create(['client_id' => $b->id]);

    $this->withHeaders($h)->post('/api/v1/addtransaction', ['userid' => $a->id, 'description' => 'x', 'amountin' => 5, 'invoiceid' => $invoiceOfB->id])
        ->assertStatus(422);
});

test('getpaymentmethods lists only gateways a customer can pay with', function () {
    $h = auditHeaders(auditFullAdmin());
    GatewaySettings::query()->delete();
    GatewaySettings::create(['gateway' => 'banktransfer', 'setting' => 'active', 'value' => '1']);
    GatewaySettings::create(['gateway' => 'paypal', 'setting' => 'active', 'value' => '0']);

    $modules = collect($this->withHeaders($h)->get('/api/v1/getpaymentmethods')->json('paymentmethods'))->pluck('module')->all();

    expect($modules)->toContain('banktransfer')->not->toContain('paypal');
});

// ------------------------------------------------------ orders/services ----

test('addorder refuses a negative price and an unknown gateway', function () {
    $h = auditHeaders(auditFullAdmin());
    $client = Client::factory()->create();
    $product = Product::factory()->create();

    $this->withHeaders($h)->post('/api/v1/addorder', ['clientid' => $client->id, 'pid' => [$product->id], 'billingcycle' => ['monthly'], 'priceoverride' => [-50]])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/addorder', ['clientid' => $client->id, 'pid' => [$product->id], 'paymentmethod' => 'nosuchgateway'])->assertStatus(422);
});

test('a cancellation request is written the way the customer area writes it, and only for a live service', function () {
    $h = auditHeaders(auditFullAdmin());
    $live = Service::factory()->create(['status' => 'active']);
    $gone = Service::factory()->create(['status' => 'terminated']);

    $this->withHeaders($h)->post('/api/v1/addcancelrequest', ['serviceid' => $live->id])->assertOk();
    $this->withHeaders($h)->post('/api/v1/addcancelrequest', ['serviceid' => $gone->id])->assertStatus(422);

    expect(CancellationRequest::where('service_id', $live->id)->value('type'))->toBe('End of Billing Period');
});

// ------------------------------------------------------------ domains ----

test('domainwhois answers availability from the same check the domain search uses', function () {
    $h = auditHeaders(auditFullAdmin());
    $answer = ['available' => true, 'checked' => true];
    app()->instance(DomainAvailability::class, new class($answer) extends DomainAvailability {
        public function __construct(public array $answer) {}
        public function check(string $domain): array
        {
            return ['domain' => $domain] + $this->answer;
        }
    });

    $this->withHeaders($h)->get('/api/v1/domainwhois?domain=free-name.com')->assertOk()->assertJson(['status' => 'available']);

    app(DomainAvailability::class)->answer = ['available' => false, 'checked' => false];
    $this->withHeaders($h)->get('/api/v1/domainwhois?domain=free-name.com')->assertStatus(503);

    $this->withHeaders($h)->get('/api/v1/domainwhois?domain=not a domain')->assertStatus(422);
});

test('createorupdatetld stores the extension the shop looks up and refuses a negative price', function () {
    $h = auditHeaders(auditFullAdmin());

    $this->withHeaders($h)->post('/api/v1/createorupdatetld', ['extension' => 'auditx', 'register_price' => 9.5])->assertOk();
    expect(DomainPricing::where('extension', '.auditx')->exists())->toBeTrue();

    $this->withHeaders($h)->post('/api/v1/createorupdatetld', ['extension' => '.auditx', 'register_price' => -1])->assertStatus(422);
});

// ------------------------------------------------------------ tickets ----

test('blocking a sender stops their tickets, not their signups', function () {
    $h = auditHeaders(auditFullAdmin());
    $ticket = Ticket::factory()->create(['email' => 'Spammer@Example.com']);

    $this->withHeaders($h)->post('/api/v1/blockticketsender', ['ticketid' => $ticket->id])->assertOk();

    expect(TicketSpamFilter::where('type', 'email')->where('content', 'spammer@example.com')->exists())->toBeTrue()
        ->and(app(\App\Services\TicketSpamService::class)->isSpam('spammer@example.com', 's', 'm'))->toBeTrue()
        ->and(BannedEmail::where('domain', 'Spammer@Example.com')->exists())->toBeFalse();
});

test('merging moves the notes too, and a ticket cannot be merged into itself', function () {
    $h = auditHeaders(auditFullAdmin());
    [$from, $into] = [Ticket::factory()->create(), Ticket::factory()->create()];
    TicketNote::create(['ticket_id' => $from->id, 'admin' => 'a', 'message' => 'staff note']);

    $this->withHeaders($h)->post('/api/v1/mergeticket', ['ticketid' => $from->id, 'mergeid' => $from->id])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/mergeticket', ['ticketid' => $from->id, 'mergeid' => $into->id])->assertOk();

    expect(TicketNote::where('ticket_id', $into->id)->where('message', 'staff note')->exists())->toBeTrue()
        ->and($from->fresh()->status)->toBe('Closed');
});

test('updateticket takes only a configured status, stored as it is spelled there', function () {
    $h = auditHeaders(auditFullAdmin());
    TicketStatus::firstOrCreate(['title' => 'On Hold'], ['color' => '#999', 'sort_order' => 9]);
    $ticket = Ticket::factory()->create();

    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $ticket->id, 'status' => 'Sleeping'])->assertStatus(422);
    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $ticket->id, 'status' => 'on hold'])->assertOk();

    expect($ticket->fresh()->status)->toBe('On Hold');
});

// ------------------------------------------------------------- system ----

test('getmodulequeue shows the real queue, never the payload', function () {
    $h = auditHeaders(auditFullAdmin());
    $service = Service::factory()->create();
    ModuleQueue::create(['service_id' => $service->id, 'action' => 'change_password', 'status' => 'pending', 'payload' => ['password' => 'the-new-password']]);

    $r = $this->withHeaders($h)->get('/api/v1/getmodulequeue')->assertOk();

    expect($r->json('totalresults'))->toBeGreaterThanOrEqual(1)
        ->and($r->getContent())->not->toContain('the-new-password');
});

test('endpoints that once reported success for nothing now ask for what they need', function () {
    // They said "done" and did nothing; they now do the work (ApiNewEndpointsTest),
    // so an empty call is told what is missing - never "success".
    $h = auditHeaders(auditFullAdmin());

    foreach (['updatemoduleconfiguration', 'triggernotificationevent', 'starttasktimer', 'endtasktimer'] as $action) {
        $this->withHeaders($h)->post('/api/v1/'.$action)->assertStatus(422);
    }
});

test('quotes are written with the status spelling the quote screens use', function () {
    $h = auditHeaders(auditFullAdmin());
    $client = Client::factory()->create();

    $id = $this->withHeaders($h)->post('/api/v1/createquote', ['clientid' => $client->id])->assertOk()->json('quoteid');
    expect(Quote::find($id)->status)->toBe('Draft');

    $this->withHeaders($h)->post('/api/v1/updatequote', ['quoteid' => $id, 'status' => 'sent'])->assertStatus(422);
});

test('addbannedip takes an address or a prefix the ban check can match, nothing else', function () {
    $h = auditHeaders(auditFullAdmin());

    $this->withHeaders($h)->post('/api/v1/addbannedip', ['ip' => '203.0.113.7'])->assertOk();
    $this->withHeaders($h)->post('/api/v1/addbannedip', ['ip' => '198.51.100.*'])->assertOk();
    $this->withHeaders($h)->post('/api/v1/addbannedip', ['ip' => 'somewhere'])->assertStatus(422);
});

// ------------------------------------------- endpoints that now do work ----

test('resetpassword sends the same reset mail the forgot-password form sends', function () {
    Mail::fake();
    $h = auditHeaders(auditFullAdmin());
    $user = User::factory()->create(['email' => 'forgot@example.com']);

    $this->withHeaders($h)->post('/api/v1/resetpassword', ['email' => 'forgot@example.com'])->assertOk();
    Mail::assertSent(\App\Mail\PasswordResetMail::class, fn ($m) => $m->hasTo('forgot@example.com'));
    expect(\DB::table('password_reset_tokens')->where('email', 'forgot@example.com')->exists())->toBeTrue();

    $this->withHeaders($h)->post('/api/v1/resetpassword', ['id' => $user->id])->assertOk();
    $this->withHeaders($h)->post('/api/v1/resetpassword', ['email' => 'nobody@example.com'])->assertNotFound();
});

test('stored methods can be made default and removed, the way the client area does it', function () {
    $h = auditHeaders(auditFullAdmin());
    $client = Client::factory()->create();
    $first = PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'banktransfer', 'payment_type' => 'bank', 'is_default' => true]);
    $second = PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'banktransfer', 'payment_type' => 'bank', 'is_default' => false]);
    $someoneElses = PaymentMethod::create(['client_id' => Client::factory()->create()->id, 'gateway_name' => 'banktransfer', 'payment_type' => 'bank']);

    $this->withHeaders($h)->post('/api/v1/updatepaymethod', ['clientid' => $client->id, 'paymethodid' => $second->id, 'set_as_default' => 1])->assertOk();
    expect($second->fresh()->is_default)->toBeTrue()->and($first->fresh()->is_default)->toBeFalse();

    // Another customer's method is not reachable through this client's id.
    $this->withHeaders($h)->post('/api/v1/deletepaymethod', ['clientid' => $client->id, 'paymethodid' => $someoneElses->id])->assertNotFound();

    $this->withHeaders($h)->post('/api/v1/deletepaymethod', ['clientid' => $client->id, 'paymethodid' => $first->id])->assertOk();
    expect(PaymentMethod::find($first->id))->toBeNull()
        ->and(PaymentMethod::withTrashed()->find($first->id))->not->toBeNull();
});

test('activatemodule and deactivatemodule flip the switch the Modules screen flips', function () {
    $h = auditHeaders(auditFullAdmin());
    GatewaySettings::where('gateway', 'banktransfer')->where('setting', 'active')->delete();

    $this->withHeaders($h)->post('/api/v1/activatemodule', ['moduleType' => 'gateway', 'moduleName' => 'banktransfer'])->assertOk();
    expect(app(\App\Services\Module\ModuleSwitchboard::class)->isActive('gateway', 'banktransfer'))->toBeTrue();

    $this->withHeaders($h)->post('/api/v1/deactivatemodule', ['moduleType' => 'gateway', 'moduleName' => 'banktransfer'])->assertOk();
    expect(app(\App\Services\Module\ModuleSwitchboard::class)->isActive('gateway', 'banktransfer'))->toBeFalse();

    $this->withHeaders($h)->post('/api/v1/activatemodule', ['moduleType' => 'gateway', 'moduleName' => 'nosuch'])->assertNotFound();
    $this->withHeaders($h)->post('/api/v1/activatemodule', ['moduleType' => 'widget', 'moduleName' => 'x'])->assertStatus(422);

    // A server module that a server uses stays on, as on the screen.
    Service::factory()->create();
    \App\Models\Server::factory()->create(['type' => 'plesk']);
    $this->withHeaders($h)->post('/api/v1/deactivatemodule', ['moduleType' => 'server', 'moduleName' => 'plesk'])->assertStatus(422);
});

// ------------------------------------ the names the reference documents ----

test('the parameter names the API reference documents are the ones the API reads', function () {
    Mail::fake();
    $h = auditHeaders(auditFullAdmin());
    [$a, $b] = [Client::factory()->create(), Client::factory()->create()];
    Ticket::factory()->create(['client_id' => $a->id]);
    Ticket::factory()->create(['client_id' => $b->id]);

    // addclientnote: userid (documented) found the client; clientid only did before.
    $this->withHeaders($h)->post('/api/v1/addclientnote', ['userid' => $a->id, 'note' => 'via userid'])->assertOk();
    expect(ClientNote::where('client_id', $a->id)->where('note', 'via userid')->exists())->toBeTrue();

    // gettickets: clientid filters.
    $owners = collect($this->withHeaders($h)->get('/api/v1/gettickets?clientid='.$a->id)->json('data'))->pluck('client_id')->unique()->values()->all();
    expect($owners)->toBe([$a->id]);

    // openticket: clientid is recorded on the ticket.
    $dept = \App\Models\TicketDepartment::factory()->create();
    $tid = $this->withHeaders($h)->post('/api/v1/openticket', ['deptid' => $dept->id, 'subject' => 's', 'message' => 'm', 'email' => $a->email, 'clientid' => $a->id])->assertOk()->json('ticketid');
    expect(Ticket::find($tid)->client_id)->toBe($a->id);

    // createquote: userid and validuntil.
    $qid = $this->withHeaders($h)->post('/api/v1/createquote', ['userid' => $a->id, 'validuntil' => '2030-01-31'])->assertOk()->json('quoteid');
    expect(Quote::find($qid)->client_id)->toBe($a->id)
        ->and(\Illuminate\Support\Carbon::parse(Quote::find($qid)->valid_until)->toDateString())->toBe('2030-01-31');

    // updateinvoice: duedate.
    $invoice = Invoice::factory()->create(['client_id' => $a->id]);
    $this->withHeaders($h)->post('/api/v1/updateinvoice', ['invoiceid' => $invoice->id, 'duedate' => '2031-02-03'])->assertOk();
    expect(\Illuminate\Support\Carbon::parse($invoice->fresh()->due_date)->toDateString())->toBe('2031-02-03');
    $this->withHeaders($h)->post('/api/v1/updateinvoice', ['invoiceid' => $invoice->id, 'paymentmethod' => 'nosuchgateway'])->assertStatus(422);

    // updateclientdomain: expirydate, and a registrar that is installed.
    $domain = Domain::factory()->create(['client_id' => $a->id]);
    $this->withHeaders($h)->post('/api/v1/updateclientdomain', ['domainid' => $domain->id, 'expirydate' => '2032-04-05'])->assertOk();
    expect($domain->fresh()->expiry_date->toDateString())->toBe('2032-04-05');
    $this->withHeaders($h)->post('/api/v1/updateclientdomain', ['domainid' => $domain->id, 'registrar' => 'nosuchregistrar'])->assertStatus(422);
});

test('getproducts honours the documented pid filter, and addannouncement its published flag', function () {
    $h = auditHeaders(auditFullAdmin());
    [$p1, $p2] = [Product::factory()->create(), Product::factory()->create()];

    $ids = collect($this->withHeaders($h)->get('/api/v1/getproducts?pid='.$p1->id)->json('products'))->pluck('id')->all();
    expect($ids)->toBe([$p1->id]);

    $id = $this->withHeaders($h)->post('/api/v1/addannouncement', ['title' => 'Draft news', 'announcement' => 'x', 'published' => 0])->assertOk()->json('announcementid');
    expect((bool) \App\Models\Announcement::find($id)->published)->toBeFalse();
});

test('updateticket changes the subject it documents', function () {
    $h = auditHeaders(auditFullAdmin());
    $ticket = Ticket::factory()->create(['title' => 'Old subject']);

    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $ticket->id, 'subject' => 'New subject'])->assertOk();

    expect($ticket->fresh()->title)->toBe('New subject');
});

test('contact password hashes and credential secrets never leave the API', function () {
    $h = auditHeaders(auditFullAdmin());
    $client = Client::factory()->create();
    \App\Models\Contact::factory()->create(['client_id' => $client->id, 'password' => bcrypt('contact-secret-pw')]);
    $hash = \App\Models\Contact::where('client_id', $client->id)->value('password');

    $details = $this->withHeaders($h)->get('/api/v1/getclientsdetails?clientid='.$client->id)->assertOk()->getContent();
    $contacts = $this->withHeaders($h)->get('/api/v1/getcontacts?userid='.$client->id)->assertOk()->getContent();
    $creds = $this->withHeaders($h)->get('/api/v1/listoauthcredentials')->assertOk()->getContent();

    expect($hash)->not->toBeEmpty()
        ->and($details)->not->toContain($hash)->not->toContain('"password"')
        ->and($contacts)->not->toContain($hash)->not->toContain('"password"')
        ->and($creds)->not->toContain('"secret"');
});
