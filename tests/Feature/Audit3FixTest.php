<?php

use App\Models\Admin;
use App\Models\BannedEmail;
use App\Models\BannedIp;
use App\Models\BillableItem;
use App\Models\Client;
use App\Models\Currency;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\NetworkIssue;
use App\Models\RegistrarSettings;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Third audit sweep. Six admin pages passed their data under one variable name
 * while the blade iterated another, so pages with dozens of rows rendered a
 * permanent "nothing here" empty state. Plus assorted attribute reads that
 * always resolved to null (ticket subject in emails, default currency badge,
 * client last login) and a country list that was never provided.
 */
function a3Admin(): Admin
{
    return Admin::factory()->create();
}

// ---------------------------------------------------------------------------
// Pages that showed an empty state despite having rows
// ---------------------------------------------------------------------------

test('the banned emails page lists existing entries', function () {
    BannedEmail::create(['domain' => 'spammer.example', 'reason' => 'abuse']);

    $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.banned-emails'))
        ->assertOk()
        ->assertSee('spammer.example');
});

test('the banned ips page lists existing entries', function () {
    BannedIp::create(['ip' => '203.0.113.201', 'reason' => 'brute force']);

    $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.banned-ips'))
        ->assertOk()
        ->assertSee('203.0.113.201');
});

test('the network issues page lists existing entries', function () {
    NetworkIssue::create(['title' => 'Rack B power event', 'type' => 'Server', 'status' => 'Investigating', 'description' => 'x']);

    $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.network-issues'))
        ->assertOk()
        ->assertSee('Rack B power event');
});

test('the billable items page lists existing entries', function () {
    $client = Client::factory()->create();
    BillableItem::create(['client_id' => $client->id, 'description' => 'Emergency restore', 'amount' => 40]);

    $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.billable-items'))
        ->assertOk()
        ->assertSee('Emergency restore');
});

test('the registrars page lists installed modules with their settings', function () {
    RegistrarSettings::updateOrCreate(['registrar' => 'enom', 'setting' => 'api_key'], ['value' => 'k']);

    $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.registrars'))
        ->assertOk()
        ->assertSee('enom')
        ->assertSee('namecheap');
});

test('the knowledge base page lists articles and their published state', function () {
    $cat = KbCategory::create(['name' => 'Billing help']);
    KbArticle::create(['category_id' => $cat->id, 'title' => 'How to pay an invoice', 'article' => '...', 'private' => false]);
    KbArticle::create(['category_id' => $cat->id, 'title' => 'Internal runbook', 'article' => '...', 'private' => true]);

    $response = $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.knowledge-base'))
        ->assertOk()
        ->assertSee('How to pay an invoice')
        ->assertSee('Internal runbook');

    // Published/draft badges must reflect the real "private" column.
    expect(substr_count($response->getContent(), 'badge-active'))->toBeGreaterThan(0)
        ->and(substr_count($response->getContent(), 'badge-draft'))->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Attribute reads that always resolved to null
// ---------------------------------------------------------------------------

test('ticket emails render the real ticket subject', function () {
    $ticket = Ticket::factory()->create(['title' => 'Disk quota exceeded']);

    $shared = ['ticket' => $ticket, 'companyName' => 'PNLCS', 'isAdmin' => false, 'clientUrl' => url('/client')];
    $opened = view('emails.ticket-opened', $shared)->render();
    $reply = view('emails.ticket-reply', $shared + ['replyMessage' => 'Looking into it'])->render();

    expect($opened)->toContain('Disk quota exceeded')
        ->and($reply)->toContain('Disk quota exceeded');
});

test('the default currency badge is rendered', function () {
    Currency::where('is_default', true)->update(['is_default' => false]);
    Currency::create(['code' => 'XTS', 'prefix' => 'X', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $html = $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.config.currencies'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'badge-active'))->toBeGreaterThan(0);
});

test('client login records last_login and the admin page shows it', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create(['email' => 'lastlogin@example.com', 'password' => Hash::make('Secret123')]);
    $user->clients()->attach($client->id);

    expect($user->fresh()->last_login)->toBeNull();

    $this->post(route('client.login.submit'), [
        'email' => 'lastlogin@example.com',
        'password' => 'Secret123',
    ])->assertRedirect();

    expect($user->fresh()->last_login)->not->toBeNull();

    $this->actingAs(a3Admin(), 'admin')
        ->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertDontSee(__('admin.clients.never'));
});

test('the client profile country select is populated', function () {
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($user)
        ->get(route('client.account.profile'))
        ->assertOk()
        ->assertSee('value="TR"', false)
        ->assertSee('value="US"', false);
});

test('the legacy account payment methods route redirects to the real page', function () {
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($user)
        ->get(route('client.account.payment_methods'))
        ->assertRedirect(route('client.payment-methods.index'));
});

// ---------------------------------------------------------------------------
// Guard against the whole bug class coming back
// ---------------------------------------------------------------------------

test('every admin config listing page renders its rows, not an empty state', function () {
    // One row per page, then assert the page shows it. This is the regression
    // net for controller/blade variable-name drift.
    $client = Client::factory()->create();
    $cat = KbCategory::create(['name' => 'Net cat']);

    $fixtures = [
        'admin.config.banned-emails' => [fn () => BannedEmail::create(['domain' => 'net-check.example', 'reason' => 'x']), 'net-check.example'],
        'admin.config.banned-ips' => [fn () => BannedIp::create(['ip' => '203.0.113.222', 'reason' => 'x']), '203.0.113.222'],
        'admin.config.network-issues' => [fn () => NetworkIssue::create(['title' => 'Net check issue', 'type' => 'Network', 'status' => 'Open', 'description' => 'x']), 'Net check issue'],
        'admin.config.billable-items' => [fn () => BillableItem::create(['client_id' => $client->id, 'description' => 'Net check item', 'amount' => 1]), 'Net check item'],
        'admin.config.knowledge-base' => [fn () => KbArticle::create(['category_id' => $cat->id, 'title' => 'Net check article', 'article' => 'x']), 'Net check article'],
        'admin.config.ticket-departments' => [fn () => TicketDepartment::factory()->create(['name' => 'Net check dept']), 'Net check dept'],
    ];

    $admin = a3Admin();
    foreach ($fixtures as $route => [$make, $needle]) {
        $make();
        $this->actingAs($admin, 'admin')
            ->get(route($route))
            ->assertOk()
            ->assertSee($needle, false);
    }
});
