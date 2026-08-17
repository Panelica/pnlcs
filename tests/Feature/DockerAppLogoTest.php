<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DockerAppLogo;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Catalogue images.
 *
 * The panel's logo_url points at third-party servers: of 98 apps only 13 had a
 * link that still resolved, 11 were dead and the rest had none, so the grid
 * looked half-finished and every page load called out to github/jsdelivr.
 * Images now live on our side, and these pin the operator screen that manages
 * them - including that a bad file or a dead link cannot end up stored.
 */

function logoAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function logoServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.logo.test', 'ip_address' => '10.0.0.13',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

/** @param array<int, array{slug:string,name:string,logo_url?:string}> $apps */
function fakeCatalogue(array $apps, ?string $imageBody = null, int $imageStatus = 200): void
{
    Http::fake(function ($request) use ($apps, $imageBody, $imageStatus) {
        if (str_contains($request->url(), '/v1/docker/templates')) {
            return Http::response(['data' => ['templates' => $apps]], 200);
        }
        if (str_contains($request->url(), 'logos.test')) {
            return Http::response($imageBody ?? 'PNGDATA', $imageStatus, ['Content-Type' => 'image/png']);
        }

        return Http::response('', 404);
    });
}

beforeEach(function () {
    Storage::fake('public');
});

it('lists the panel catalogue with what has an image and what does not', function () {
    logoServer();
    fakeCatalogue([['slug' => 'wordpress', 'name' => 'WordPress'], ['slug' => 'n8n', 'name' => 'n8n']]);
    DockerAppLogo::create(['slug' => 'wordpress', 'path' => 'docker-apps/wordpress-abc.png']);

    $this->actingAs(logoAdmin(), 'admin')->get(route('admin.docker-apps.index'))
        ->assertOk()
        ->assertSee('WordPress')->assertSee('n8n')
        ->assertViewHas('totalWithLogo', 1);
});

it('shows only the apps still missing an image when asked', function () {
    logoServer();
    fakeCatalogue([['slug' => 'wordpress', 'name' => 'WordPress'], ['slug' => 'n8n', 'name' => 'n8n']]);
    DockerAppLogo::create(['slug' => 'wordpress', 'path' => 'docker-apps/wordpress-abc.png']);

    $page = $this->actingAs(logoAdmin(), 'admin')->get(route('admin.docker-apps.index', ['missing' => 1]))->assertOk();
    expect(collect($page->viewData('templates'))->pluck('slug')->all())->toBe(['n8n']);
});

it('stores an uploaded image and serves it to the catalogue', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.upload'), [
        'slug' => 'n8n',
        'logo' => UploadedFile::fake()->image('n8n.png', 40, 40),
    ])->assertRedirect();

    $logo = DockerAppLogo::where('slug', 'n8n')->firstOrFail();
    Storage::disk('public')->assertExists($logo->path);
    expect(DockerAppLogo::urlMap())->toHaveKey('n8n');
});

it('refuses a file that is not an image', function () {
    logoServer();
    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.upload'), [
        'slug' => 'n8n',
        'logo' => UploadedFile::fake()->create('payload.php', 4, 'application/x-php'),
    ])->assertSessionHasErrors('logo');

    expect(DockerAppLogo::count())->toBe(0);
});

it('fetches an image from a URL', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.test/n8n.png',
    ])->assertRedirect()->assertSessionHas('success');

    expect(DockerAppLogo::where('slug', 'n8n')->exists())->toBeTrue();
});

it('stores nothing when the link is dead', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']], null, 404);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.test/gone.png',
    ])->assertRedirect()->assertSessionHas('error');

    // Half the panel's links are dead; a failure must leave the app on its
    // letter tile rather than storing an error page as an image.
    expect(DockerAppLogo::count())->toBe(0);
});

it('fills in every missing image in one pass and reports what failed', function () {
    logoServer();
    fakeCatalogue([
        ['slug' => 'good', 'name' => 'Good', 'logo_url' => 'https://logos.test/a.png'],
        ['slug' => 'dead', 'name' => 'Dead', 'logo_url' => 'https://dead.test/b.png'],
        ['slug' => 'nolink', 'name' => 'No Link'],
    ]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.import'))
        ->assertRedirect()->assertSessionHas('success');

    expect(DockerAppLogo::pluck('slug')->all())->toBe(['good']);
    // An app the panel has no link for is still tried against the icon set, so
    // it is reported as a failure rather than as nothing to try.
    expect(session('success'))->toContain('Fetched 1')->toContain('Failed 2');
});

it('falls back to the icon set when the panel has no link or a dead one', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/v1/docker/templates')) {
            return Http::response(['data' => ['templates' => [
                ['slug' => 'nolink', 'name' => 'No Link'],
                ['slug' => 'deadlink', 'name' => 'Dead Link', 'logo_url' => 'https://dead.test/x.png'],
            ]]], 200);
        }
        // The icon set answers for both; the panel's own link does not.
        if (str_contains($url, 'dashboard-icons')) {
            return Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']);
        }

        return Http::response('', 404);
    });
    logoServer();

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.import'))->assertRedirect();

    // Three quarters of the catalogue has no panel link at all; without a
    // second source they would all stay on letter tiles.
    expect(DockerAppLogo::pluck('slug')->sort()->values()->all())->toBe(['deadlink', 'nolink']);
});

it('leaves images already set alone unless told to replace them', function () {
    logoServer();
    fakeCatalogue([['slug' => 'good', 'name' => 'Good', 'logo_url' => 'https://logos.test/a.png']]);
    DockerAppLogo::create(['slug' => 'good', 'path' => 'docker-apps/good-old.png', 'source' => 'upload']);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.import'))->assertRedirect();
    expect(DockerAppLogo::where('slug', 'good')->value('path'))->toBe('docker-apps/good-old.png');

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.import'), ['overwrite' => 1])->assertRedirect();
    expect(DockerAppLogo::where('slug', 'good')->value('path'))->not->toBe('docker-apps/good-old.png');
});

it('removes an image and its file', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);
    Storage::disk('public')->put('docker-apps/n8n-x.png', 'DATA');
    DockerAppLogo::create(['slug' => 'n8n', 'path' => 'docker-apps/n8n-x.png']);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.destroy'), ['slug' => 'n8n'])->assertRedirect();

    expect(DockerAppLogo::count())->toBe(0);
    Storage::disk('public')->assertMissing('docker-apps/n8n-x.png');
});

it('keeps the screen out of a client\'s hands', function () {
    // The client guard is not the admin guard: a signed-in customer hitting the
    // admin URL is sent to the admin login, not served the catalogue.
    $this->actingAs(User::factory()->create())
        ->get(route('admin.docker-apps.index'))
        ->assertRedirect();
});
