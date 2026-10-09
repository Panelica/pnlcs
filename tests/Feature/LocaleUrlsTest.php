<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Models\Client;
use App\Models\Language;
use App\Models\Setting;
use App\Support\LocaleUrl;
use Illuminate\Support\Facades\URL;

/*
 * The language in the address (Setup > Languages, off by default).
 *
 * The default language keeps today's addresses; every other active language is
 * served under its code. Shop: English by default, Turkish switched on.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
    foreach ([['en', 'English', 'English', 'gb', true, 1], ['tr', 'Turkish', 'Türkçe', 'tr', false, 2]] as [$code, $name, $native, $flag, $default, $sort]) {
        Language::updateOrCreate(['code' => $code], ['name' => $name, 'native_name' => $native, 'flag_code' => $flag, 'direction' => 'ltr', 'is_active' => true, 'is_default' => $default, 'sort_order' => $sort]);
    }
    Setting::set('DefaultLanguage', 'en', 'language');
    LocaleUrl::forget();
});

function localeUrlsOn(): void
{
    Setting::set(LocaleUrl::SETTING, '1', 'language');
    LocaleUrl::forget();
}

test('switched off, nothing changes: no prefixed address, no prefixed link', function () {
    $this->get('/tr/client/login')->assertNotFound();

    app()->setLocale('tr');
    expect(route('client.login'))->not->toContain('/tr/');
});

test('a prefixed address is the page in that language, and the choice is kept', function () {
    localeUrlsOn();

    $this->get('/tr/client/login')
        ->assertOk()
        ->assertSee('lang="tr"', false)
        ->assertSessionHas('locale', 'tr');
});

test('links on a page in another language carry its prefix', function () {
    localeUrlsOn();

    $html = $this->get('/tr/client/login')->assertOk()->getContent();

    expect($html)->toContain('/tr/client/register')
        ->and($html)->toContain('/tr/client/forgot-password');
});

test('the default language has no prefix: its prefixed address moves to the plain one', function () {
    localeUrlsOn();

    $this->get('/en/client/login?x=1')->assertStatus(301)->assertRedirect('/client/login?x=1');
});

test('a prefix never opens the admin area, the API or a gateway callback', function () {
    localeUrlsOn();

    $this->get('/tr/admin/login')->assertNotFound();
    $this->get('/tr/api/health')->assertNotFound();

    app()->setLocale('tr');
    expect(route('admin.login'))->not->toContain('/tr/')
        ->and(route('gateway.stripe.webhook'))->not->toContain('/tr/')
        ->and(url('/api/v1/clients'))->not->toContain('/tr/');
});

test('a visitor who chose a language and follows a plain link lands on that language\'s address', function () {
    localeUrlsOn();

    $this->withSession(['locale' => 'tr'])->get('/client/login')->assertRedirect('/tr/client/login');
});

test('picking a language with ?lang goes to its address, and back', function () {
    localeUrlsOn();

    $this->get('/client/login?lang=tr')->assertRedirect('/tr/client/login');
    $this->get('/tr/client/login?lang=en')->assertRedirect('/client/login');
});

test('without a choice, a plain address is the default language, whatever the browser asks for', function () {
    localeUrlsOn();

    $this->withHeader('Accept-Language', 'tr-TR,tr;q=0.9')
        ->get('/client/login')
        ->assertOk()
        ->assertSee('lang="en"', false);
});

test('a signed link keeps the address it was signed for and still opens', function () {
    localeUrlsOn();
    $client = Client::factory()->create();

    app()->setLocale('tr');
    $url = URL::signedRoute('client.unsubscribe', ['client' => $client->id]);
    expect($url)->not->toContain('/tr/');

    $this->withSession(['locale' => 'tr'])->get($url)->assertOk();
});

test('e-mails link to the recipient\'s language', function () {
    localeUrlsOn();

    app()->setLocale('tr');
    expect(route('client.invoices.index'))->toEndWith('/tr/client/invoices');

    app()->setLocale('en');
    expect(route('client.invoices.index'))->toEndWith('/client/invoices')->not->toContain('/en/');
});

test('a form on a prefixed page posts to a prefixed address that works', function () {
    localeUrlsOn();

    $this->post('/tr/client/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertRedirect()
        ->assertSessionHasErrors();
});

test('every page names its address in each language for search engines', function () {
    localeUrlsOn();

    $root = rtrim(url('/'), '/');

    $this->get('/tr/client/login')
        ->assertSee('<link rel="alternate" hreflang="en" href="'.$root.'/client/login">', false)
        ->assertSee('<link rel="alternate" hreflang="tr" href="'.$root.'/tr/client/login">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$root.'/client/login">', false)
        ->assertSee('<link rel="canonical" href="'.$root.'/tr/client/login">', false);
});

test('switched off, pages carry no language links', function () {
    $this->get('/client/login')->assertOk()->assertDontSee('hreflang', false);
});

test('the language switcher links to the page in the other language, and the choice is remembered', function () {
    localeUrlsOn();

    expect(LocaleUrl::switchTo('tr', \Illuminate\Http\Request::create('http://localhost/client/login')))->toBe(rtrim(url('/'), '/').'/tr/client/login?lang=tr');

    // following it: the page in Turkish at its own address, without ?lang
    $this->get('/tr/client/login?lang=tr')->assertRedirect('/tr/client/login');
    // and back to the default language
    $this->withSession(['locale' => 'tr'])->get('/client/login?lang=en')->assertRedirect('/client/login');
    $this->get('/client/login')->assertOk()->assertSee('lang="en"', false);
});

test('switched off, the language switcher keeps today\'s ?lang link', function () {
    expect(LocaleUrl::switchTo('tr', \Illuminate\Http\Request::create('http://localhost/client/login?x=1')))->toBe('http://localhost/client/login?x=1&lang=tr');
});

test('an administrator switches it on and off on the languages screen', function () {
    $admin = \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.languages.index'))
        ->assertOk()->assertSee('name="locale_urls"', false);

    $this->actingAs($admin, 'admin')->post(route('admin.config.languages.url-setting'), ['locale_urls' => '1'])->assertSessionHasNoErrors();
    expect(Setting::get(LocaleUrl::SETTING))->toBe('1')->and(LocaleUrl::enabled())->toBeTrue();
    // as a visitor (acting as staff makes the admin guard the default one)
    app('auth')->forgetGuards();
    $this->get('/tr/client/login')->assertOk();

    $this->actingAs($admin, 'admin')->post(route('admin.config.languages.url-setting'), ['locale_urls' => '0']);
    expect(LocaleUrl::enabled())->toBeFalse();
    app('auth')->forgetGuards();
    $this->get('/tr/client/login')->assertNotFound();
});

test('the e-mail confirmation link sent in another language still confirms', function () {
    // Its signature is checked against the address without a prefix.
    localeUrlsOn();
    $user = \App\Models\User::factory()->create(['email_verified_at' => null]);

    app()->setLocale('tr');
    $url = \App\Http\Controllers\Client\EmailVerificationController::verificationUrl($user);
    expect($url)->not->toContain('/tr/');

    $this->withSession(['locale' => 'tr'])->get($url)->assertRedirect();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('social sign-in keeps the callback address registered with Google and GitHub', function () {
    localeUrlsOn();
    app()->setLocale('tr');

    expect(route('client.social.google.callback'))->not->toContain('/tr/')
        ->and(route('client.social.github.callback'))->not->toContain('/tr/');
});

test('a queued job reads the setting afresh, so a long-running worker follows it', function () {
    localeUrlsOn();
    expect(LocaleUrl::enabled())->toBeTrue();

    Setting::set(LocaleUrl::SETTING, '0', 'language');
    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class)->shouldIgnoreMissing();
    $job->allows('payload')->andReturn([]);
    event(new \Illuminate\Queue\Events\JobProcessing('sync', $job));

    expect(LocaleUrl::enabled())->toBeFalse();
});

test('the sitemap and robots.txt keep one address, and a shared page names its own', function () {
    localeUrlsOn();

    $this->withSession(['locale' => 'tr'])->get('/sitemap.xml')->assertOk();
    $this->withSession(['locale' => 'tr'])->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml', false);
    app()->setLocale('tr');
    expect(route('sitemap'))->not->toContain('/tr/');

    $root = rtrim((string) config('app.url'), '/');
    $this->get('/tr/client/login')->assertOk()->assertSee('<meta property="og:url" content="'.$root.'/tr/client/login">', false);
});
