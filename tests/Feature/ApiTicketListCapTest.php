<?php

use App\Models\ApiCredential;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Database\Factories\ApiCredentialFactory;

/*
 * Every list endpoint caps a page at 250 rows; the ticket list read limitnum
 * straight off the request, so one call could ask for the whole table.
 */
test('the ticket list caps the page size like every other list', function () {
    $credential = ApiCredential::factory()->create();
    $department = TicketDepartment::factory()->create();
    Ticket::factory()->count(260)->create(['department_id' => $department->id]);

    $response = $this->withHeaders(['X-API-Key' => $credential->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET])
        ->getJson('/api/v1/gettickets?limitnum=100000')
        ->assertSuccessful();

    expect((int) $response->json('numreturned'))->toBe(250)
        ->and((int) $response->json('totalresults'))->toBe(260);
});
