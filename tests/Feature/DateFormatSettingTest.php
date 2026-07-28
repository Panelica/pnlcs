<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Settings → General has always offered a DateFormat field, and nothing read
 * it: every view carried its own hard-coded pattern, so the operator could
 * type anything and the panel kept printing "05 Jul 2026".
 */
test('the date format setting is what the helper returns', function () {
    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => 'Y.m.d']);

    expect(date_fmt())->toBe('Y.m.d')
        ->and(datetime_fmt())->toBe('Y.m.d H:i');
});

test('an unset or blank date format falls back to a sane default', function () {
    Setting::where('setting', 'DateFormat')->delete();
    expect(date_fmt())->toBe('d/m/Y');

    app()->forgetInstance('pnlcs.date_format');
    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => '   ']);
    expect(date_fmt())->toBe('d/m/Y');
});

test('the format is read once per request, not once per date on the page', function () {
    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => 'd.m.Y']);

    DB::enableQueryLog();
    for ($i = 0; $i < 25; $i++) {
        date_fmt();
        datetime_fmt();
    }
    $reads = collect(DB::getRawQueryLog())
        ->filter(fn ($q) => str_contains($q['raw_query'], 'DateFormat'))
        ->count();
    DB::disableQueryLog();

    expect($reads)->toBe(1);
});

test('changing the setting changes the dates a customer sees', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'date' => '2026-07-05',
        'due_date' => '2026-07-19',
        'status' => 'unpaid',
    ]);

    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => 'd.m.Y']);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('05.07.2026')
        ->assertDontSee('05 Jul 2026');
});

test('changing the setting changes the dates an admin sees', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'date' => '2026-07-05',
        'due_date' => '2026-07-19',
        'status' => 'unpaid',
    ]);

    Setting::updateOrCreate(['setting' => 'DateFormat'], ['value' => 'd.m.Y']);

    $this->actingAs($admin, 'admin')->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('05.07.2026');
});
