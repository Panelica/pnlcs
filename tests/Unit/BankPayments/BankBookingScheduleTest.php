<?php

use Carbon\Carbon;
use Modules\Addons\BankPayments\Services\BankBookingSchedule;

beforeEach(function () {
    config(['bank-payments.banks' => (require base_path('modules/Addons/BankPayments/config/bank-payments.php'))['banks']]);
});

it('opens the Nest Bank sync windows thirty minutes after official sessions', function () {
    $schedule = new BankBookingSchedule;

    foreach ([
        '10:59' => false,
        '11:00' => true,
        '11:10' => true,
        '11:11' => false,
        '14:44' => false,
        '14:45' => true,
        '17:30' => true,
    ] as $time => $expected) {
        $now = Carbon::parse('2026-11-27 '.$time);

        expect($schedule->isSyncWindowOpen('nest_bank', $now))->toBe($expected);
    }
});

it('does not open the window on a weekend', function () {
    $schedule = new BankBookingSchedule;

    expect($schedule->isSyncWindowOpen('nest_bank', Carbon::parse('2026-11-28 11:00')))->toBeFalse()
        ->and($schedule->isSyncWindowOpen('nest_bank', Carbon::parse('2026-11-29 17:30')))->toBeFalse();
});

it('can return the next effective window', function () {
    $schedule = new BankBookingSchedule;

    expect($schedule->nextWindow('nest_bank', Carbon::parse('2026-11-27 11:30'))?->format('H:i'))->toBe('14:45');
});

it('does not open a window for an unknown bank', function () {
    $schedule = new BankBookingSchedule;

    expect($schedule->isSyncWindowOpen('unknown_bank', Carbon::parse('2026-11-27 11:00')))->toBeFalse();
});
