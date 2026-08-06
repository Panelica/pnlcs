<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Widgets\ClientsWidget;
use App\Widgets\DomainsWidget;
use App\Widgets\SupportWidget;

/**
 * What the dashboard does with what customers typed.
 *
 * The widgets build their own HTML by joining strings, and the dashboard
 * prints that HTML unescaped - it has to, that is how a widget works. But the
 * strings being joined are customer records: names, email addresses, domains,
 * ticket subjects. None of them were escaped.
 *
 * So a customer who signs up with a script tag in their surname does not get a
 * strangely named account: they get their script running in the operator's
 * browser, with the operator's session, every time the dashboard is opened.
 */
function dashboardAdmin(): Admin
{
    return Admin::factory()->create();
}

it('does not run a customer name as markup on the dashboard', function () {
    Client::factory()->create([
        'first_name' => '<script>alert(1)</script>',
        'last_name' => 'Smith',
        'email' => 'xss@test.local',
    ]);

    $html = (new ClientsWidget)->render((new ClientsWidget)->getData());

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('alert(1)');
});

it('does not run a domain name as markup', function () {
    Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => '<img src=x onerror=alert(1)>.com',
        'type' => 'Register',
        'registration_period' => 1,
        'registration_date' => now(),
        'expiry_date' => now()->addDays(10),
        'next_due_date' => now()->addDays(10),
        'status' => 'active',
        'recurring_amount' => 10,
        'registrar' => 'enom',
    ]);

    $html = (new DomainsWidget)->render((new DomainsWidget)->getData());

    expect($html)->not->toContain('<img src=x onerror=alert(1)>');
});

it('does not run a ticket subject as markup', function () {
    $department = TicketDepartment::firstOrCreate(['name' => 'Support'], ['email' => 'support@test.local']);

    Ticket::create([
        'tid' => 'XSS-'.uniqid(),
        'department_id' => $department->id,
        'client_id' => Client::factory()->create()->id,
        'name' => 'Test',
        'email' => 'ticket@test.local',
        'title' => '<script>alert(2)</script>',
        'message' => 'x',
        'status' => 'Open',
        'priority' => 'Medium',
        'last_reply' => now(),
    ]);

    $html = (new SupportWidget)->render((new SupportWidget)->getData());

    expect($html)->not->toContain('<script>alert(2)</script>');
});

it('still shows the dashboard', function () {
    $this->actingAs(dashboardAdmin(), 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();
});
