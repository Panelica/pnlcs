<?php

use App\Http\Controllers\Client\EmailVerificationController;
use App\Mail\EmailVerificationMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Language;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use App\Support\GeoLocale;
use App\Support\PasswordHistory;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * Four things a hosting company learned it needed the hard way, running this
 * software for real customers, and brought back into the product.
 */

// ---------------------------------------------------------------- plain text

test('every email leaves with a plain-text part beside the HTML', function () {
    $captured = null;
    Event::listen(MessageSending::class, function ($event) use (&$captured) {
        $captured = $event->message;
    });

    $user = User::factory()->create(['email_verified_at' => null]);

    Mail::to($user->email)->send(new EmailVerificationMail(EmailVerificationController::verificationUrl($user), $user->email, 'Ada'));

    expect($captured)->not->toBeNull()
        ->and($captured->getTextBody())->not->toBeNull()
        // The link survives as an address, not as a dead button label.
        ->and($captured->getTextBody())->toContain('/client/email/verify/')
        ->and($captured->getTextBody())->not->toContain('<a ');
});

// ----------------------------------------------------------- password reuse

function reuseCustomer(string $password = 'Original1A'): User
{
    $user = User::factory()->create(['password' => Hash::make($password)]);
    $client = \App\Models\Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    return $user;
}

test('the current password cannot be set again', function () {
    $user = reuseCustomer('Original1A');

    expect(PasswordHistory::isReused($user, 'Original1A'))->toBeTrue()
        ->and(PasswordHistory::isReused($user, 'Different2B'))->toBeFalse();
});

test('the password page refuses one of the last three', function () {
    $user = reuseCustomer('Original1A');

    $this->actingAs($user)->put(route('client.account.password.update'), [
        'current_password' => 'Original1A',
        'password' => 'Second2B!', 'password_confirmation' => 'Second2B!',
    ])->assertSessionHasNoErrors();

    // Back to the first one: it is only one change ago.
    $this->actingAs($user->fresh())->put(route('client.account.password.update'), [
        'current_password' => 'Second2B!',
        'password' => 'Original1A', 'password_confirmation' => 'Original1A',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('Second2B!', $user->fresh()->password))->toBeTrue();
});

test('the window is three deep, and older passwords come free again', function () {
    $user = reuseCustomer('One1AAAA');

    foreach (['Two2BBBB', 'Three3CC', 'Four4DDD'] as $next) {
        $previous = (string) $user->fresh()->password;
        $user->fresh()->update(['password' => Hash::make($next)]);
        PasswordHistory::remember($user, $previous);
    }

    $user = $user->fresh();

    // Current is Four; Three and Two are the two before it. One is outside.
    expect(PasswordHistory::isReused($user, 'Four4DDD'))->toBeTrue()
        ->and(PasswordHistory::isReused($user, 'Three3CC'))->toBeTrue()
        ->and(PasswordHistory::isReused($user, 'Two2BBBB'))->toBeTrue()
        ->and(PasswordHistory::isReused($user, 'One1AAAA'))->toBeFalse();

    // And nothing outside the window is still being held.
    expect(\DB::table('password_histories')->where('user_id', $user->id)->count())->toBe(PasswordHistory::KEEP - 1);
});

// ------------------------------------------------------------- automation

test('the automation screen lists what the scheduler is really set up to run', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Ops', 'permissions' => ['manage_settings']])->id,
    ]);

    ScheduledTaskRun::create([
        'command' => 'pnlcs:generate-invoices',
        'last_run_at' => now()->subMinutes(30),
        'runtime_ms' => 1200,
        'exit_code' => 0,
    ]);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.config.automation'))->assertOk()->getContent();

    expect($html)->toContain('pnlcs:generate-invoices')
        ->and($html)->toContain(__('admin.automation.tasks')['pnlcs_generate_invoices'])
        // A real schedule has more than the eight rows that used to be typed in.
        ->and(substr_count($html, '<tr>'))->toBeGreaterThan(9)
        // The hardcoded "Not configured" is gone for a task that just ran.
        ->and($html)->toContain(__('admin.automation.state_ok'));
});

test('a task that exits non-zero is recorded as failed even without a failed event', function () {
    $listener = new \App\Listeners\RecordCronHeartbeat;

    // A real scheduler entry, so the summary has the shape cron produces.
    $task = app(\Illuminate\Console\Scheduling\Schedule::class)->command('pnlcs:db-backup');
    $task->exitCode = 1;

    $listener->handleFinished(new \Illuminate\Console\Events\ScheduledTaskFinished($task, 2.5));

    $row = ScheduledTaskRun::where('command', 'pnlcs:db-backup')->first();

    expect($row)->not->toBeNull()
        ->and($row->failures)->toBe(1)
        ->and($row->last_failed_at)->not->toBeNull();
});

// ------------------------------------------------------------- geo locale

test('the browser language wins when we publish it', function () {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'pl-PL,pl;q=0.9',
        'HTTP_CF_IPCOUNTRY' => 'DE',
    ]);

    expect(GeoLocale::locale($request, ['en', 'tr', 'pl']))->toBe('pl');
});

test('a country whose language we do not publish gets english, not the site default', function () {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
        'HTTP_CF_IPCOUNTRY' => 'FR',
    ]);

    expect(GeoLocale::locale($request, ['en', 'tr']))->toBe('en');
});

test('a browser asking for a language we do not have is not handed it anyway', function () {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
    ]);

    // No country header either: nothing to reason from, leave it to the default.
    expect(GeoLocale::locale($request, ['en', 'tr']))->toBeNull();
});

test('cloudflare\'s "could not place" answers are not treated as countries', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_CF_IPCOUNTRY' => 'XX']);

    expect(GeoLocale::country($request))->toBeNull();
});
