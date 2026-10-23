<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Support\Str;

/**
 * The API took a fourth priority, "critical", that nothing else knows: the
 * admin ticket page offers Low, Medium and High, so a critical ticket was
 * saved as Low the next time staff changed its status there, and escalation
 * rules could not match it. WHMCS's API has the same three.
 */
function priorityApiHeaders(): array
{
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $secret = Str::random(64);
    $cred = ApiCredential::create([
        'admin_id' => $admin->id, 'identifier' => Str::random(32),
        'secret' => ApiCredential::hashSecret($secret), 'description' => 'priority', 'active' => true,
    ]);

    return ['X-API-Key' => $cred->identifier, 'X-API-Secret' => $secret, 'Accept' => 'application/json'];
}

test('the api takes the priorities the rest of the app knows', function () {
    $ticket = Ticket::factory()->create(['priority' => 'medium']);
    $h = priorityApiHeaders();

    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $ticket->id, 'priority' => 'critical'])->assertStatus(422);
    expect($ticket->fresh()->priority)->toBe('medium');

    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $ticket->id, 'priority' => 'high'])->assertOk();
    expect($ticket->fresh()->priority)->toBe('high');
});

test('a ticket opened through the api cannot be critical either', function () {
    $dept = TicketDepartment::factory()->create();

    $this->withHeaders(priorityApiHeaders())->post('/api/v1/openticket', [
        'deptid' => $dept->id, 'subject' => 'Down', 'message' => 'Site down', 'email' => 'someone@example.test', 'priority' => 'critical',
    ])->assertStatus(422);
});
