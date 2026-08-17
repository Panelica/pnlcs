<?php

use App\Models\DockerApp;
use App\Models\HomepageSection;
use App\Models\Server;
use Illuminate\Support\Facades\Http;

/*
 * The shop window on the front page.
 *
 * It is a homepage section like any other, so it can be switched off, reordered
 * and reworded from the admin screen. Two things it must not do: cost a call to
 * the panel when nobody is showing it, and draw an empty section when the panel
 * cannot be reached.
 */

function showcaseServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.showcase.test', 'ip_address' => '10.0.0.15',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function enableShowcase(bool $on = true): void
{
    HomepageSection::updateOrCreate(['slug' => 'docker-apps'], [
        'title' => 'One-Click Apps', 'sort_order' => 99, 'is_enabled' => $on,
    ]);
}

function fakeShowcaseCatalogue(array $apps, int $status = 200): void
{
    Http::fake(function ($request) use ($apps, $status) {
        if (str_contains($request->url(), '/v1/docker/templates')) {
            return Http::response(['data' => ['templates' => $apps]], $status);
        }

        return Http::response(['data' => []], 200);
    });
}

it('shows the apps on the front page when the section is on', function () {
    showcaseServer();
    enableShowcase();
    fakeShowcaseCatalogue([
        ['slug' => 'wordpress', 'name' => 'WordPress', 'description' => 'Blog', 'logo_url' => '', 'categories' => []],
    ]);

    $this->get('/')->assertOk()->assertSee('WordPress');
});

it('costs nothing when the section is off', function () {
    showcaseServer();
    enableShowcase(false);
    fakeShowcaseCatalogue([['slug' => 'wordpress', 'name' => 'WordPress']]);

    $this->get('/')->assertOk()->assertDontSee('oca__grid', false);

    // The front page is the most-hit page there is; a section nobody shows must
    // not make it call the panel.
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/docker/templates'));
});

it('draws nothing rather than an empty section when the panel is unreachable', function () {
    showcaseServer();
    enableShowcase();
    fakeShowcaseCatalogue([], 500);

    $this->get('/')->assertOk()->assertDontSee('oca__grid', false);
});

it('leaves out apps the operator does not sell', function () {
    showcaseServer();
    enableShowcase();
    fakeShowcaseCatalogue([
        ['slug' => 'wordpress', 'name' => 'WordPress', 'description' => '', 'logo_url' => '', 'categories' => []],
        ['slug' => 'secret', 'name' => 'SecretApp', 'description' => '', 'logo_url' => '', 'categories' => []],
    ]);
    DockerApp::create(['slug' => 'secret', 'is_sellable' => false]);

    $this->get('/')->assertOk()->assertSee('WordPress')->assertDontSee('SecretApp');
});

it('shows the operator selling line instead of the panel description', function () {
    showcaseServer();
    enableShowcase();
    fakeShowcaseCatalogue([
        ['slug' => 'n8n', 'name' => 'n8n', 'description' => 'Workflow automation tool', 'logo_url' => '', 'categories' => []],
    ]);
    DockerApp::create(['slug' => 'n8n', 'tagline' => 'Automate anything, no code']);

    $this->get('/')->assertOk()
        ->assertSee('Automate anything, no code')
        ->assertDontSee('Workflow automation tool');
});
