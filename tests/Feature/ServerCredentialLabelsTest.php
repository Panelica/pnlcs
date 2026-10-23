<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;

/**
 * The server form named the fields after something other than what the
 * modules read. cPanel called the password field "API Token" while the token
 * is read from the second field, Plesk and DirectAdmin said that field was
 * "Not used" while it is their API key / login key, and the error for a
 * missing token told the operator to fill in an "API key field" that the
 * form did not show under that name.
 */
function credentialAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function serverPost(array $fields): array
{
    return array_merge([
        'name' => 'Node '.uniqid(), 'hostname' => 'node.example.com', 'ip_address' => '203.0.113.10',
        'port' => 2087, 'username' => 'root', 'active' => '1',
    ], $fields);
}

it('names the field the module reads when the token is missing', function () {
    $this->actingAs(credentialAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), serverPost(['type' => 'cpanel', 'password' => 'token-in-the-wrong-field']))
        ->assertSessionHasErrors(['access_hash' => __('admin.servers.needs_api_token', ['type' => 'CPANEL', 'field' => 'API Token'])]);

    expect(Server::count())->toBe(0);
});

it('accepts a cPanel server with the token in the API Token field', function () {
    $this->actingAs(credentialAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), serverPost(['type' => 'cpanel', 'access_hash' => 'WHMTOKEN']))
        ->assertSessionHasNoErrors();

    expect(Server::where('type', 'cpanel')->value('access_hash'))->toBe('WHMTOKEN');
});

it('labels the second field as the key each panel reads', function () {
    $page = $this->actingAs(credentialAdmin(), 'admin')->get(route('admin.config.servers'))->assertOk()->getContent();

    expect($page)->toContain("hashLabel: 'API Token', hashPlaceholder: 'WHM")
        ->and($page)->toContain("hashLabel: 'API Key', hashPlaceholder: 'Plesk")
        ->and($page)->toContain("hashLabel: 'Login Key'")
        ->and($page)->not->toContain('Not used by Plesk')
        ->and($page)->not->toContain('Not used by DirectAdmin');
});
