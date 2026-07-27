<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\DomainPricing;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Regressions for the 2026-07 config-page audit:
 *  - storeTld/updateTld silently dropped every field except the extension,
 *    creating TLDs with $0.00 prices (free domains) and making it impossible
 *    to disable a TLD or set its registrar.
 *  - The promotion form posted type=fixed / expiry_date / active while the
 *    backend expects fixed_amount / expiration_date (and has no active
 *    column), so fixed-amount promos could never be created and no promo
 *    ever expired.
 *  - The download form posted name/url against a controller requiring
 *    title/location, so downloads could never be added; the category delete
 *    route referenced by the page did not exist, turning the page into a 500
 *    as soon as a category was created.
 *  - The client security page never received $sessions and its revoke route
 *    did not exist.
 *  - admin.payment_notifications.* lived inside the 'nav' array, rendering
 *    raw translation keys; client.payment_methods.type was missing.
 */
function cfgAdmin(): Admin
{
    return Admin::factory()->create();
}

// ---------------------------------------------------------------------------
// Domain pricing (TLD)
// ---------------------------------------------------------------------------

test('storeTld persists prices, limits, registrar and enabled flag', function () {
    $this->actingAs(cfgAdmin(), 'admin')
        ->post(route('admin.config.domain-pricing.store'), [
            'extension' => '.example',
            'register_price' => 9.99,
            'transfer_price' => 8.99,
            'renew_price' => 10.99,
            'grace_period' => 30,
            'min_years' => 1,
            'max_years' => 5,
            'auto_registrar' => 'enom',
            'sort_order' => 3,
            'enabled' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $tld = DomainPricing::where('extension', '.example')->firstOrFail();
    expect((float) $tld->register_price)->toBe(9.99)
        ->and((float) $tld->transfer_price)->toBe(8.99)
        ->and((float) $tld->renew_price)->toBe(10.99)
        ->and($tld->grace_period)->toBe(30)
        ->and($tld->max_years)->toBe(5)
        ->and($tld->auto_registrar)->toBe('enom')
        ->and($tld->sort_order)->toBe(3)
        ->and($tld->enabled)->toBeTrue();
});

test('updateTld persists the secondary fields and can disable a TLD', function () {
    $tld = DomainPricing::create(['extension' => '.upd', 'register_price' => 5, 'transfer_price' => 5, 'renew_price' => 5, 'enabled' => true]);

    $this->actingAs(cfgAdmin(), 'admin')
        ->put(route('admin.config.domain-pricing.update', $tld), [
            'extension' => '.upd',
            'register_price' => 6,
            'transfer_price' => 6,
            'renew_price' => 6,
            'grace_period' => 45,
            'min_years' => 2,
            'max_years' => 10,
            'auto_registrar' => 'namecheap',
            'sort_order' => 7,
            // enabled checkbox left unchecked
        ])->assertRedirect()->assertSessionHasNoErrors();

    $tld->refresh();
    expect($tld->grace_period)->toBe(45)
        ->and($tld->min_years)->toBe(2)
        ->and($tld->auto_registrar)->toBe('namecheap')
        ->and($tld->sort_order)->toBe(7)
        ->and($tld->enabled)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Promotions
// ---------------------------------------------------------------------------

test('a fixed amount promotion can be created and its expiry is persisted', function () {
    $this->actingAs(cfgAdmin(), 'admin')
        ->post(route('admin.config.promotions.store'), [
            'code' => 'FIXED5',
            'type' => 'fixed_amount',
            'value' => 5,
            'max_uses' => 0,
            'expiration_date' => '2027-01-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $promo = Promotion::where('code', 'FIXED5')->firstOrFail();
    expect($promo->type)->toBe('fixed_amount')
        ->and($promo->expiration_date->toDateString())->toBe('2027-01-01')
        ->and($promo->isValid())->toBeTrue();
});

test('an expired promotion reports itself invalid', function () {
    $promo = Promotion::create(['code' => 'OLD10', 'type' => 'percentage', 'value' => 10, 'expiration_date' => now()->subDay()]);

    expect($promo->isValid())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Downloads
// ---------------------------------------------------------------------------

test('a download can be created from the form field names', function () {
    $cat = DownloadCategory::create(['name' => 'Guides']);

    $this->actingAs(cfgAdmin(), 'admin')
        ->post(route('admin.config.downloads.store'), [
            'category_id' => $cat->id,
            'title' => 'Setup Guide',
            'description' => 'How to set things up',
            'location' => 'https://example.com/guide.pdf',
            'published' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

    $dl = Download::where('title', 'Setup Guide')->firstOrFail();
    expect($dl->location)->toBe('https://example.com/guide.pdf')
        ->and($dl->hidden)->toBeFalse();
});

test('an unpublished download is stored hidden', function () {
    $cat = DownloadCategory::create(['name' => 'Drafts']);

    $this->actingAs(cfgAdmin(), 'admin')
        ->post(route('admin.config.downloads.store'), [
            'category_id' => $cat->id,
            'title' => 'Draft Guide',
            'location' => 'https://example.com/draft.pdf',
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Download::where('title', 'Draft Guide')->firstOrFail()->hidden)->toBeTrue();
});

test('the downloads page renders with categories and a category can be deleted', function () {
    $cat = DownloadCategory::create(['name' => 'Temp Cat']);
    Download::create(['category_id' => $cat->id, 'title' => 'Doomed', 'location' => 'x']);

    // Used to 500 with RouteNotFoundException as soon as a category existed.
    $this->actingAs(cfgAdmin(), 'admin')
        ->get(route('admin.config.downloads'))
        ->assertOk()
        ->assertSee('Temp Cat');

    $this->actingAs(cfgAdmin(), 'admin')
        ->delete(route('admin.config.downloads.categories.destroy', $cat))
        ->assertRedirect();

    expect(DownloadCategory::find($cat->id))->toBeNull()
        ->and(Download::where('title', 'Doomed')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Client security sessions
// ---------------------------------------------------------------------------

function securityClient(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('security page renders without a session section on non-database drivers', function () {
    config(['session.driver' => 'file']);

    $this->actingAs(securityClient())
        ->get(route('client.account.security'))
        ->assertOk()
        ->assertDontSee(__('client.security.active_sessions'));
});

test('with the database driver the page lists sessions and revoke removes only own rows', function () {
    config(['session.driver' => 'database']);
    $user = securityClient();
    $other = securityClient();

    DB::table('sessions')->insert([
        ['id' => 'sess-own', 'user_id' => $user->id, 'ip_address' => '198.51.100.7', 'user_agent' => 'TestAgent', 'payload' => '', 'last_activity' => time()],
        ['id' => 'sess-other', 'user_id' => $other->id, 'ip_address' => '198.51.100.8', 'user_agent' => 'TestAgent', 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($user)
        ->get(route('client.account.security'))
        ->assertOk()
        ->assertSee('198.51.100.7')
        ->assertDontSee('198.51.100.8');

    // Cannot revoke someone else's session.
    $this->actingAs($user)
        ->post(route('client.account.security.logout_session', 'sess-other'))
        ->assertRedirect();
    expect(DB::table('sessions')->where('id', 'sess-other')->exists())->toBeTrue();

    // Can revoke own session.
    $this->actingAs($user)
        ->post(route('client.account.security.logout_session', 'sess-own'))
        ->assertRedirect();
    expect(DB::table('sessions')->where('id', 'sess-own')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Translations
// ---------------------------------------------------------------------------

test('payment notification and payment method translation keys resolve', function () {
    expect(__('admin.payment_notifications.title'))->toBe('Payment Notifications')
        ->and(__('admin.payment_notifications.approved_partial', ['balance' => '5.00']))->toContain('5.00')
        ->and(__('client.payment_methods.type'))->toBe('Type')
        ->and(__('messages.success.category_deleted'))->toBe('Category deleted.')
        ->and(__('client.security.session_revoked'))->toBe('Session revoked.');
});
