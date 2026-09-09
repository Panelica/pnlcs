<?php

use App\Http\Controllers\LegalController;
use App\Mail\DomainRenewalReminderMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
 * What a host that sells domains for a living needed and the product lacked.
 */

function reminderDomain(int $daysUntilExpiry, array $extra = []): Domain
{
    $client = Client::factory()->create(['email' => 'owner'.uniqid().'@example.test']);

    return Domain::factory()->create(array_merge([
        'client_id' => $client->id,
        'domain' => 'expiring'.uniqid().'.com',
        'status' => 'active',
        'expiry_date' => now()->addDays($daysUntilExpiry)->toDateString(),
        'renewal_reminder_stage' => null,
    ], $extra));
}

// ------------------------------------------------------ renewal reminders

test('the renewal reminder template finally has something that sends it', function () {
    Mail::fake();

    reminderDomain(10);   // inside the 15-day stage

    $this->artisan('pnlcs:domain-renewal-reminders')->assertSuccessful();

    Mail::assertQueued(DomainRenewalReminderMail::class);
});

test('each stage is sent once, and a skipped week still catches up', function () {
    Mail::fake();
    $domain = reminderDomain(10);

    $this->artisan('pnlcs:domain-renewal-reminders');
    $this->artisan('pnlcs:domain-renewal-reminders');

    // Two runs on the same stage: one mail.
    Mail::assertQueued(DomainRenewalReminderMail::class, 1);
    expect($domain->fresh()->renewal_reminder_stage)->toBe('before15');

    // The domain slides into the next stage; the next run notices.
    $domain->update(['expiry_date' => now()->addDay()->toDateString()]);
    $this->artisan('pnlcs:domain-renewal-reminders');

    Mail::assertQueued(DomainRenewalReminderMail::class, 2);
    expect($domain->fresh()->renewal_reminder_stage)->toBe('before1');
});

test('a domain past any hope of recovery is left alone', function () {
    Mail::fake();
    DomainPricing::create(['extension' => '.com', 'register_price' => 10, 'transfer_price' => 10, 'renew_price' => 10, 'grace_period' => 30, 'redemption_grace_period' => 30]);

    // 90 days gone: grace (30) + redemption (30) are both behind it.
    reminderDomain(-90);

    $this->artisan('pnlcs:domain-renewal-reminders');

    Mail::assertNothingQueued();
});

test('the dry run lists and sends nothing', function () {
    Mail::fake();
    reminderDomain(10);

    $this->artisan('pnlcs:domain-renewal-reminders', ['--dry-run' => true])
        ->expectsOutputToContain('Would send 1');

    Mail::assertNothingQueued();
});

// ------------------------------------------------------- registrar float

test('a registrar balance at the floor warns the operator once a day', function () {
    // Mail::raw() is not a mailable, so the fake would count nothing; the
    // mailer is the array driver under test, and the sending event is real.
    $sent = 0;
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function () use (&$sent) { $sent++; });
    Setting::set('SystemEmailAddress', 'ops@example.test');
    Setting::set('RegistrarBalanceThreshold', '1000');
    Setting::set('RegistrarBalanceCurrency', 'TRY');
    Setting::set('BalanceWatchRegistrar', 'fakebalance');

    app(\App\Services\Module\ModuleRegistry::class)->registerRegistrar('fakebalance', FakeBalanceRegistrar::class);
    FakeBalanceRegistrar::$balance = 250.0;

    $this->artisan('pnlcs:registrar-balance')->assertSuccessful();
    $this->artisan('pnlcs:registrar-balance')->assertSuccessful();

    expect($sent)->toBe(1)
        ->and((string) Setting::get('RegistrarBalanceLastAlert'))->toStartWith('low:');
});

test('a healthy balance clears the alert so the next dip warns again', function () {
    $sent = 0;
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function () use (&$sent) { $sent++; });
    Setting::set('SystemEmailAddress', 'ops@example.test');
    Setting::set('BalanceWatchRegistrar', 'fakebalance');
    Setting::set('RegistrarBalanceLastAlert', 'low:'.now()->toDateString());
    app(\App\Services\Module\ModuleRegistry::class)->registerRegistrar('fakebalance', FakeBalanceRegistrar::class);
    FakeBalanceRegistrar::$balance = 9000.0;

    $this->artisan('pnlcs:registrar-balance')->assertSuccessful();

    expect((string) Setting::get('RegistrarBalanceLastAlert'))->toBe('')
        ->and($sent)->toBe(0);
});

// -------------------------------------------------------- manual registrar

test('the manual registrar says out loud that nothing was registered', function () {
    $dispatched = [];
    app()->bind(NotificationService::class, function () use (&$dispatched) { return new class($dispatched) extends NotificationService {
        public function __construct(private &$seen) {}
        public function dispatch(string $eventType, array $data = []): void { $this->seen[] = $eventType; }
    }; });

    $domain = reminderDomain(365);
    (new \Modules\Registrars\Manual\ManualRegistrar)->register($domain, 1, []);

    expect($dispatched)->toContain('domain.manual_registration_required')
        ->and(NotificationService::eventTypes())->toContain('domain.manual_registration_required')
        ->and(NotificationService::eventTypes())->toContain('registrar.balance_low')
        ->and(NotificationService::eventTypes())->toContain('service.provision_failed');
});

// ------------------------------------------------------ domain pricing

test('an operator can record a restore fee, a category and a popular flag', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Domains', 'permissions' => ['manage_servers', 'manage_domains']])->id,
    ]);

    $this->actingAs($admin, 'admin')->post(route('admin.config.domain-pricing.store'), [
        'extension' => '.example', 'register_price' => 12, 'transfer_price' => 12, 'renew_price' => 14,
        'restore_price' => 80, 'category' => 'local', 'is_popular' => '1', 'grace_period' => 30,
    ])->assertSessionHasNoErrors();

    $row = DomainPricing::where('extension', '.example')->first();

    expect((float) $row->restore_price)->toBe(80.0)
        ->and($row->category)->toBe('local')
        ->and($row->is_popular)->toBeTrue();
});

test('the pricing page groups extensions by their category, not by a list typed into the template', function () {
    DomainPricing::create(['extension' => '.local', 'register_price' => 9, 'transfer_price' => 9, 'renew_price' => 9, 'category' => 'local', 'enabled' => true]);

    $html = $this->get(route('client.domain.pricing'))->assertOk()->getContent();

    expect($html)->toContain('data-cats="local"')
        ->and($html)->toContain(__('client.domain_pricing.local_tlds'));
});

// ------------------------------------------------------------- legal pages

test('the legal hub and every listed document render', function () {
    $this->get(route('legal.index'))->assertOk();

    foreach (array_keys(LegalController::published()) as $slug) {
        $this->get(route('legal.show', $slug))->assertOk();
    }
});

test('the turkish statutory forms are listed only for a turkish seller', function () {
    Setting::set('Country', 'DE');
    expect(LegalController::published())->not->toHaveKey('distance-sales');
    $this->get(route('legal.show', 'distance-sales'))->assertNotFound();

    Setting::set('Country', 'TR');
    expect(LegalController::published())->toHaveKey('distance-sales');
    $this->get(route('legal.show', 'distance-sales'))->assertOk();
});

test('the legal documents name the seller from the settings', function () {
    Setting::set('CompanyName', 'Example Hosting');
    Setting::set('CompanyLegalName', 'Example Hosting Ltd');

    $this->get(route('legal.show', 'terms'))->assertOk()->assertSee('Example Hosting Ltd');
});

test('the corporate pages render without an operator writing anything', function () {
    $this->get(route('pages.about'))->assertOk();
    $this->get(route('pages.ssl'))->assertOk();
    $this->get(route('pages.mail-setup'))->assertOk()->assertSee('mail.');
});

// ---------------------------------------------------- test helper module

class FakeBalanceRegistrar extends \Modules\Registrars\Manual\ManualRegistrar
{
    public static float $balance = 0.0;

    public function getBalance(): array
    {
        return ['success' => true, 'try' => self::$balance, 'usd' => self::$balance / 35];
    }
}
