<?php

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * The administrator the seeder creates.
 *
 * A headless install that ran db:seed by hand kept admin / admin123 until
 * somebody remembered to change it. The password is now random and printed
 * once, or the one PNLCS_ADMIN_PASSWORD names.
 */
afterEach(function () {
    putenv('PNLCS_ADMIN_PASSWORD');
    unset($_ENV['PNLCS_ADMIN_PASSWORD'], $_SERVER['PNLCS_ADMIN_PASSWORD']);
});

test('the seeded administrator does not get a password everybody knows', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = Admin::where('username', 'admin')->firstOrFail();
    expect(Hash::check('admin123', $admin->password))->toBeFalse();
});

test('PNLCS_ADMIN_PASSWORD chooses it', function () {
    putenv('PNLCS_ADMIN_PASSWORD=chosen-by-script');
    $_ENV['PNLCS_ADMIN_PASSWORD'] = $_SERVER['PNLCS_ADMIN_PASSWORD'] = 'chosen-by-script';

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('chosen-by-script', Admin::where('username', 'admin')->value('password')))->toBeTrue();
});

test('the demo seeder does not get one either', function () {
    // Demo data is seeded deliberately, but the administrator it creates signs
    // in to the same panel: admin / admin123 was the same open door there.
    $this->seed(DemoSeeder::class);

    expect(Hash::check('admin123', Admin::where('username', 'admin')->value('password')))->toBeFalse();
});

test('PNLCS_ADMIN_PASSWORD chooses the demo one too', function () {
    putenv('PNLCS_ADMIN_PASSWORD=chosen-by-script');
    $_ENV['PNLCS_ADMIN_PASSWORD'] = $_SERVER['PNLCS_ADMIN_PASSWORD'] = 'chosen-by-script';

    $this->seed(DemoSeeder::class);

    expect(Hash::check('chosen-by-script', Admin::where('username', 'admin')->value('password')))->toBeTrue();
});
