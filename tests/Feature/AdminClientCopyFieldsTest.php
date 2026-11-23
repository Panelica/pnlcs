<?php

use App\Models\Admin;
use App\Models\Client;

it('copies the complete billing block from the button and useful fields from icons', function () {
    $client = Client::factory()->create([
        'client_type' => 'company',
        'company_name' => 'Example Company',
        'tax_office' => 'Warszawa',
        'tax_id' => '1234567890',
        'billing_email' => 'billing@example.test',
        'phone_prefix' => '+48',
        'phone_number' => '512 344 445',
        'address1' => 'ul. Lipowa 12',
        'city' => 'Śliwiec',
        'state' => 'Dolnośląskie',
        'postcode' => '55-093',
        'country' => 'PL',
    ]);

    $response = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.clients.show', $client))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->not->toContain('id="billing-identity-copy"')
        ->toContain('data-billing-copy')
        ->toContain('data-copy-text=')
        ->and(substr_count($html, 'data-copy-value='))->toBe(8);
});
