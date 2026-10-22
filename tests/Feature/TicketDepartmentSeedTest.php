<?php

use App\Models\TicketDepartment;
use Database\Seeders\DatabaseSeeder;

/**
 * Every install seeded its ticket departments with support@, tech@, billing@
 * and sales@pnlcs.com - addresses of the project, not of the operator - and
 * the form described the field as being "for ticket notifications", which it
 * is not: it is the address mail import treats as the department's own.
 */
it('seeds the default departments without someone else\'s address', function () {
    $this->seed(DatabaseSeeder::class);

    expect(TicketDepartment::count())->toBeGreaterThanOrEqual(4)
        ->and(TicketDepartment::where('email', 'like', '%@pnlcs.com')->count())->toBe(0);
});
