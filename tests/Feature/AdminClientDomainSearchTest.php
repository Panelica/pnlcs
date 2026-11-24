<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Service;

it('searches clients by service and domain names and lists their domains', function () {
    $client = Client::factory()->create(['first_name' => 'Domain', 'last_name' => 'Searcher']);

    Service::factory()->create([
        'client_id' => $client->id,
        'domain' => 'service.example',
    ]);
    Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'record.example',
    ]);

    $response = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.clients.index', ['search' => 'service.example']))
        ->assertOk()
        ->assertSee('Domain Searcher')
        ->assertSee('service.example')
        ->assertSee(__('admin.clients.tab_domains'));

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.clients.index', ['search' => 'record.example']))
        ->assertOk()
        ->assertSee('Domain Searcher')
        ->assertSee('record.example');
});
