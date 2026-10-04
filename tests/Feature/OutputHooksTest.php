<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;

/*
 * Addons could add pages but not a line to an existing one: no analytics or
 * chat snippet in the head, nothing on a service page (a licence key, an
 * outside account's status). WHMCS has *Output hooks for this.
 */

test('the client area, home page and legal pages print what ClientAreaHeadOutput and ClientAreaFooterOutput return', function () {
    add_hook('ClientAreaHeadOutput', fn () => '<meta name="ika-head-probe" content="1">');
    add_hook('ClientAreaFooterOutput', fn () => '<script>window.ikaFooterProbe=1</script>');
    add_hook('ClientAreaHeadOutput', fn () => ['not' => 'a string']);

    foreach (['/', route('client.login'), route('client.register'), route('legal.index')] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect($html)->toContain('<meta name="ika-head-probe" content="1">')
            ->and($html)->toContain('<script>window.ikaFooterProbe=1</script>')
            ->and(strpos($html, 'ika-head-probe'))->toBeLessThan(strpos($html, '</head>'));
    }
});

test('the admin area prints AdminAreaHeadOutput and AdminAreaFooterOutput', function () {
    add_hook('AdminAreaHeadOutput', fn ($vars) => '<meta name="ika-admin-probe" content="'.e($vars['admin']->username).'">');
    add_hook('AdminAreaFooterOutput', fn () => '<script>window.ikaAdminFooter=1</script>');
    $admin = Admin::factory()->create(['username' => 'probeadmin', 'role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['view_dashboard']])->id]);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->getContent();

    expect($html)->toContain('<meta name="ika-admin-probe" content="probeadmin">')->and($html)->toContain('window.ikaAdminFooter=1');
});

test('a service page shows what ClientAreaProductDetailsOutput returns for that service', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $service = Service::factory()->create(['client_id' => $client->id, 'status' => 'active', 'server_id' => null]);
    add_hook('ClientAreaProductDetailsOutput', fn ($vars) => '<div class="licence">Licence key for #'.$vars['service']->id.'</div>');

    $this->actingAs($user)->get(route('client.services.show', $service))->assertOk()
        ->assertSee('<div class="licence">Licence key for #'.$service->id.'</div>', false);
});
