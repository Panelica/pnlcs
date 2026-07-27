<?php

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\BannedEmail;
use App\Models\BillableItem;
use App\Models\Client;
use App\Models\HomepageContent;
use App\Models\HomepageSection;
use App\Models\Invoice;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\RegistrarSettings;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;
use App\Models\TodoItem;
use App\Models\User;
use App\Services\CartService;

/**
 * Second audit sweep: form/controller vocabulary mismatches that made whole
 * admin features dead (banned emails, announcements, KB articles, billable
 * items, registrar settings), the ignored mark-paid amount, cart fields that
 * were silently discarded, and assorted stale-attribute reads.
 */
function a2Admin(): Admin
{
    return Admin::factory()->create();
}

function a2ClientUser(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

// ---------------------------------------------------------------------------
// Previously dead admin features
// ---------------------------------------------------------------------------

test('a banned email entry can be created from the form', function () {
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.banned-emails.store'), [
            'domain' => 'spam@example.com',
            'reason' => 'abuse',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect(BannedEmail::where('domain', 'spam@example.com')->exists())->toBeTrue();
});

test('an announcement can be created from the form and respects published', function () {
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.announcements.store'), [
            'title' => 'Maintenance window',
            'body' => 'We will be upgrading the network.',
            'published' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $a = Announcement::where('title', 'Maintenance window')->firstOrFail();
    expect($a->announcement)->toBe('We will be upgrading the network.')
        ->and((bool) $a->published)->toBeTrue();
});

test('a knowledge base article can be created and unpublishing stores private', function () {
    $cat = KbCategory::create(['name' => 'General']);

    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.knowledge-base.articles.store'), [
            'category_id' => $cat->id,
            'title' => 'Getting started',
            'article' => 'Step one...',
            'published' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $art = KbArticle::where('title', 'Getting started')->firstOrFail();
    expect($art->category_id)->toBe($cat->id)
        ->and($art->article)->toBe('Step one...')
        ->and((bool) $art->private)->toBeFalse();

    // Unchecking "published" must make it private.
    $this->actingAs(a2Admin(), 'admin')
        ->put(route('admin.config.knowledge-base.articles.update', $art), [
            'category_id' => $cat->id,
            'title' => 'Getting started',
            'article' => 'Step one...',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect((bool) $art->fresh()->private)->toBeTrue();
});

test('a billable item can be created with its client', function () {
    $client = Client::factory()->create();

    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.billable-items.store'), [
            'client_id' => $client->id,
            'description' => 'Custom migration work',
            'amount' => 75.50,
        ])->assertRedirect()->assertSessionHasNoErrors();

    $item = BillableItem::where('description', 'Custom migration work')->firstOrFail();
    expect($item->client_id)->toBe($client->id);
});

test('registrar settings are actually persisted', function () {
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.registrars.settings.update', 'enom'), [
            'settings' => ['api_user' => 'reseller1', 'api_key' => 'sekrit'],
        ])->assertRedirect();

    expect(RegistrarSettings::where('registrar', 'enom')->where('setting', 'api_key')->value('value'))->toBe('sekrit')
        ->and(RegistrarSettings::where('registrar', 'enom')->where('setting', 'api_user')->value('value'))->toBe('reseller1');

    // JSON fallback path.
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.registrars.settings.update', 'namecheap'), [
            'settings_json' => json_encode(['api_key' => 'nc-key']),
        ])->assertRedirect();

    expect(RegistrarSettings::where('registrar', 'namecheap')->where('setting', 'api_key')->value('value'))->toBe('nc-key');
});

// ---------------------------------------------------------------------------
// Invoice mark-paid amount
// ---------------------------------------------------------------------------

test('mark paid with a partial amount records a partial payment instead of full', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);

    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.invoices.mark-paid', $invoice), [
            'gateway' => 'banktransfer',
            'amount' => 40,
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

test('mark paid without an amount settles the invoice in full', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);

    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.invoices.mark-paid', $invoice), [
            'gateway' => 'manual',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
});

// ---------------------------------------------------------------------------
// Silently dropped admin form fields
// ---------------------------------------------------------------------------

test('ticket status form persists sort order and client visibility', function () {
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.ticket-statuses.store'), [
            'title' => 'Escalated',
            'color' => '#ff0000',
            'sort_order' => 7,
            'show_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $st = TicketStatus::where('title', 'Escalated')->firstOrFail();
    expect($st->sort_order)->toBe(7)->and((bool) $st->show_active)->toBeTrue();

    // Unchecking the visibility box must persist false.
    $this->actingAs(a2Admin(), 'admin')
        ->put(route('admin.config.ticket-statuses.update', $st), [
            'title' => 'Escalated',
            'sort_order' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();

    $st->refresh();
    expect($st->sort_order)->toBe(8)->and((bool) $st->show_active)->toBeFalse();
});

test('ticket department form persists description and hidden', function () {
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.ticket-departments.store'), [
            'name' => 'Abuse Desk',
            'email' => 'abuse@example.com',
            'description' => 'Reports of abuse',
            'hidden' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $dep = TicketDepartment::where('name', 'Abuse Desk')->firstOrFail();
    expect($dep->description)->toBe('Reports of abuse')->and((bool) $dep->hidden)->toBeTrue();
});

test('todo form persists the description', function () {
    $this->actingAs(a2Admin(), 'admin')
        ->post(route('admin.config.todo.store'), [
            'title' => 'Renew certs',
            'description' => 'Wildcard expires soon',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect(TodoItem::where('title', 'Renew certs')->value('description'))->toBe('Wildcard expires soon');
});

// ---------------------------------------------------------------------------
// Client-side fixes
// ---------------------------------------------------------------------------

test('client contact form phone reaches phone_number', function () {
    [$user, $client] = a2ClientUser();

    $this->actingAs($user)
        ->post(route('client.account.contacts.store'), [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'email' => 'ali@example.com',
            'phone_number' => '+90 555 000 0000',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect($client->contacts()->where('email', 'ali@example.com')->value('phone_number'))->toBe('+90 555 000 0000');
});

test('client tickets are opened with the canonical Open status', function () {
    [$user] = a2ClientUser();
    $dep = TicketDepartment::factory()->create();

    $this->actingAs($user)
        ->post(route('client.tickets.store'), [
            'department_id' => $dep->id,
            'subject' => 'Site down',
            'message' => 'Please check.',
        ])->assertRedirect();

    expect(Ticket::where('title', 'Site down')->value('status'))->toBe('Open');
});

test('cart items keep customer notes and the domain intent reaches the service', function () {
    [$user, $client] = a2ClientUser();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
    ]);

    $svc = app(CartService::class);
    $cart = $svc->getOrCreateCart($client->id);
    $svc->addProduct($cart, $product, 'monthly', 'yeni-site.com', [], 'Lütfen PHP 8.3 kurun', 'register');

    $order = $svc->checkout($cart, $client->id, 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    expect($service->notes)->toContain('Lütfen PHP 8.3 kurun')
        ->and($service->notes)->toContain('[Domain register requested: yeni-site.com]');
});

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------

test('HomepageContent::section resolves through the real key pair', function () {
    HomepageSection::firstOrCreate(['slug' => 'hero-test'], ['title' => 'Hero', 'sort_order' => 99, 'is_enabled' => true]);
    $content = HomepageContent::create(['section_slug' => 'hero-test', 'content_key' => 'headline', 'content_value' => 'Hi', 'content_type' => 'text']);

    expect($content->section?->slug)->toBe('hero-test');
});

test('the sidebar ticket search posts department_id with real options', function () {
    TicketDepartment::factory()->create(['name' => 'Sales Desk']);

    $this->actingAs(a2Admin(), 'admin')
        ->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertSee('name="department_id"', false)
        ->assertSee('Sales Desk');

    $this->actingAs(a2Admin(), 'admin')
        ->get(route('admin.clients.index'))
        ->assertOk()
        ->assertDontSee('name="search_type"', false);
});

test('the partial payment translation key resolves', function () {
    expect(__('admin.messages.invoice_partially_paid', ['num' => '42', 'balance' => '60.00']))
        ->toContain('42')->toContain('60.00');
});
