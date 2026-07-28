<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Support\Facades\DB;

/**
 * Settings → General offers a Timezone field. Nothing read it: the only match
 * for the key anywhere in the code was a comment in config/app.php, so an
 * operator in Istanbul set their timezone and every time in the panel carried
 * on being printed in UTC.
 *
 * Stored values stay in UTC. What changes is the clock the panel shows.
 */
test('the timezone setting is what the helper returns', function () {
    Setting::updateOrCreate(['setting' => 'Timezone'], ['value' => 'Europe/Istanbul']);

    expect(display_tz())->toBe('Europe/Istanbul');
});

test('an unset or nonsense timezone falls back to the app default', function () {
    Setting::where('setting', 'Timezone')->delete();
    expect(display_tz())->toBe(config('app.timezone'));

    app()->forgetInstance('pnlcs.display_tz');
    Setting::updateOrCreate(['setting' => 'Timezone'], ['value' => 'Mars/Olympus']);
    expect(display_tz())->toBe(config('app.timezone'));
});

test('the timezone is read once per request', function () {
    Setting::updateOrCreate(['setting' => 'Timezone'], ['value' => 'Europe/Istanbul']);

    DB::enableQueryLog();
    for ($i = 0; $i < 20; $i++) {
        display_tz();
    }
    $reads = collect(DB::getRawQueryLog())
        ->filter(fn ($q) => str_contains($q['raw_query'], 'Timezone'))
        ->count();
    DB::disableQueryLog();

    expect($reads)->toBe(1);
});

test('a timestamp is shown in the configured timezone', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();

    // 21:30 UTC is 00:30 the next day in Istanbul.
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'title' => 'Timezone check',
    ]);
    $ticket->created_at = '2026-07-05 21:30:00';
    $ticket->save();

    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => 'd.m.Y']);
    Setting::updateOrCreate(['setting' => 'Timezone'], ['value' => 'Europe/Istanbul']);

    $this->actingAs($admin, 'admin')->get(route('admin.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('06.07.2026 00:30')
        ->assertDontSee('05.07.2026 21:30');
});

test('a date without a time is not dragged into the previous day', function () {
    // A registration date is a date, not a moment. Converting it would show
    // 04.07 for a record stored as the 5th in a timezone behind UTC.
    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => 'd.m.Y']);
    Setting::updateOrCreate(['setting' => 'Timezone'], ['value' => 'America/New_York']);

    $admin = Admin::factory()->create();
    $domain = Domain::factory()->create([
        'registration_date' => '2026-07-05',
        'expiry_date' => '2027-07-05',
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.domains.show', $domain))
        ->assertOk()
        ->assertSee('05.07.2026');
});
