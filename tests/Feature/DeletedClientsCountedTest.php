<?php

use App\Models\Client;
use App\Widgets\ClientsWidget;
use App\Widgets\OverviewWidget;
use Illuminate\Http\Request;
use Modules\Reports\ClientSourcesReport;

/**
 * Customers who have been deleted.
 *
 * Deleting a customer marks the row rather than removing it, and every query
 * written through the model knows to leave those rows alone. The dashboard
 * widgets and the reports go to the table directly, and did not, so they
 * counted the deleted along with the living. On this installation twenty of
 * the fifty-nine rows are deleted: the dashboard reported half again as many
 * customers as there are, and the recent ones it listed could be people who
 * are no longer there.
 */
it('does not count a deleted customer on the dashboard', function () {
    $before = (new ClientsWidget)->getData();
    $beforeOverview = (new OverviewWidget)->getData();

    Client::factory()->create(['status' => 'active']);

    $gone = Client::factory()->create(['status' => 'active', 'email' => 'gone@test.local']);
    $gone->delete();

    $after = (new ClientsWidget)->getData();
    $afterOverview = (new OverviewWidget)->getData();

    expect($after['total'] - $before['total'])->toBe(1)
        ->and($after['active'] - $before['active'])->toBe(1)
        ->and($afterOverview['clients'] - $beforeOverview['clients'])->toBe(1)
        ->and(collect($after['recent'])->pluck('email'))->not->toContain('gone@test.local');
});

it('does not count a deleted customer in a report', function () {
    $total = fn () => collect((new ClientSourcesReport)->generate(Request::create('/'))['rows'])
        ->sum(fn ($row) => (int) ((array) $row)['clients']);

    $before = $total();

    Client::factory()->create(['status' => 'active']);
    Client::factory()->create(['status' => 'active'])->delete();

    expect($total() - $before)->toBe(1);
});
