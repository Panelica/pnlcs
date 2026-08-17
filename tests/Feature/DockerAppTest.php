<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DockerApp;
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

it('lists the panel catalogue with what has a logo and what does not', function () {
    logoServer();
    fakeCatalogue([['slug' => 'wordpress', 'name' => 'WordPress'], ['slug' => 'n8n', 'name' => 'n8n']]);
    DockerApp::create(['slug' => 'wordpress', 'path' => 'docker-apps/wordpress-abc.png']);

    $page = $this->actingAs(logoAdmin(), 'admin')->get(route('admin.docker-apps.index'))
        ->assertOk()->assertSee('WordPress')->assertSee('n8n');

    // Counts the logos that ship with the product as well as operator uploads -
    // both are logos the customer sees.
    expect($page->viewData('totalWithLogo'))->toBeGreaterThanOrEqual(count(DockerApp::bundledUrlMap()));
});

it('shows only the apps still missing a logo when asked', function () {
    logoServer();
    // Neither slug ships with the product, so the filter is about what the
    // operator has supplied rather than about the bundled set.
    fakeCatalogue([['slug' => 'brand-new-one', 'name' => 'Brand New One'], ['slug' => 'brand-new-two', 'name' => 'Brand New Two']]);
    DockerApp::create(['slug' => 'brand-new-one', 'path' => 'docker-apps/one-abc.png']);

    $page = $this->actingAs(logoAdmin(), 'admin')->get(route('admin.docker-apps.index', ['missing' => 1]))->assertOk();
    expect(collect($page->viewData('templates'))->pluck('slug')->all())->toBe(['brand-new-two']);
});

it('stores an uploaded image and serves it to the catalogue', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.upload'), [
        'slug' => 'n8n',
        'logo' => UploadedFile::fake()->image('n8n.png', 40, 40),
    ])->assertRedirect();

    $logo = DockerApp::where('slug', 'n8n')->firstOrFail();
    Storage::disk('public')->assertExists($logo->path);

    // Relative, so it resolves against whatever host the panel is served from.
    // Building on the app URL broke here: a container environment variable
    // overrode it with an internal host:port and every image 404'd.
    expect(DockerApp::urlMap()['n8n'])->toStartWith('/storage/docker-apps/')
        ->not->toContain('http');
});

it('refuses a file that is not an image', function () {
    logoServer();
    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.upload'), [
        'slug' => 'n8n',
        'logo' => UploadedFile::fake()->create('payload.php', 4, 'application/x-php'),
    ])->assertSessionHasErrors('logo');

    expect(DockerApp::count())->toBe(0);
});

it('fetches an image from a URL', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.test/n8n.png',
    ])->assertRedirect()->assertSessionHas('success');

    expect(DockerApp::where('slug', 'n8n')->exists())->toBeTrue();
});

it('stores nothing when the link is dead', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']], null, 404);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.test/gone.png',
    ])->assertRedirect()->assertSessionHas('error');

    // Half the panel's links are dead; a failure must leave the app on its
    // letter tile rather than storing an error page as an image.
    expect(DockerApp::count())->toBe(0);
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

    expect(DockerApp::pluck('slug')->all())->toBe(['good']);
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
    expect(DockerApp::pluck('slug')->sort()->values()->all())->toBe(['deadlink', 'nolink']);
});

it('leaves images already set alone unless told to replace them', function () {
    logoServer();
    fakeCatalogue([['slug' => 'good', 'name' => 'Good', 'logo_url' => 'https://logos.test/a.png']]);
    DockerApp::create(['slug' => 'good', 'path' => 'docker-apps/good-old.png', 'source' => 'upload']);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.import'))->assertRedirect();
    expect(DockerApp::where('slug', 'good')->value('path'))->toBe('docker-apps/good-old.png');

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.import'), ['overwrite' => 1])->assertRedirect();
    expect(DockerApp::where('slug', 'good')->value('path'))->not->toBe('docker-apps/good-old.png');
});

it('removes an image and its file', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);
    Storage::disk('public')->put('docker-apps/n8n-x.png', 'DATA');
    DockerApp::create(['slug' => 'n8n', 'path' => 'docker-apps/n8n-x.png']);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.destroy'), ['slug' => 'n8n'])->assertRedirect();

    // The file goes; the row stays, because it also records whether we sell
    // the app - see the selling test below.
    expect(DockerApp::where('slug', 'n8n')->value('path'))->toBeNull();
    Storage::disk('public')->assertMissing('docker-apps/n8n-x.png');
});

it('keeps the screen out of a client\'s hands', function () {
    // The client guard is not the admin guard: a signed-in customer hitting the
    // admin URL is sent to the admin login, not served the catalogue.
    $this->actingAs(User::factory()->create())
        ->get(route('admin.docker-apps.index'))
        ->assertRedirect();
});

/*
 * Selling the catalogue: 98 apps cannot be 98 products, so the operator says
 * which apps are on offer and how they are presented, and a customer picks one
 * while ordering a single product.
 */

it('lets the operator take an app off the shelf without deleting anything', function () {
    logoServer();
    fakeCatalogue([['slug' => 'gitlab', 'name' => 'GitLab'], ['slug' => 'n8n', 'name' => 'n8n']]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.selling'), [
        'slug' => 'gitlab', 'sort_order' => 0,
    ])->assertRedirect();

    $offered = DockerApp::decorate([
        ['slug' => 'gitlab', 'name' => 'GitLab'],
        ['slug' => 'n8n', 'name' => 'n8n'],
    ], sellableOnly: true);

    expect(collect($offered)->pluck('slug')->all())->toBe(['n8n']);
});

it('puts featured apps first, then the operator order, then the name', function () {
    DockerApp::create(['slug' => 'b', 'is_sellable' => true, 'is_featured' => true]);
    DockerApp::create(['slug' => 'c', 'is_sellable' => true, 'sort_order' => 50]);

    $sorted = DockerApp::decorate([
        ['slug' => 'a', 'name' => 'Alpha'],
        ['slug' => 'b', 'name' => 'Bravo'],
        ['slug' => 'c', 'name' => 'Charlie'],
    ]);

    // b is featured; c is ordered ahead of a; a falls back to alphabetical.
    expect(collect($sorted)->pluck('slug')->all())->toBe(['b', 'c', 'a']);
});

it('keeps unknown apps in the catalogue rather than hiding them', function () {
    // A fresh install has no rows at all; hiding everything until someone fills
    // in ninety-eight of them would be a worse default than showing them.
    $out = DockerApp::decorate([['slug' => 'brand-new', 'name' => 'Brand New']], sellableOnly: true);

    expect($out)->toHaveCount(1)
        ->and($out[0]['is_featured'])->toBeFalse()
        ->and($out[0]['logo_url_local'])->toBeNull();
});

it('carries the operator selling line onto the app', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.selling'), [
        'slug' => 'n8n', 'is_sellable' => 1, 'is_featured' => 1, 'sort_order' => 10,
        'tagline' => 'Automate anything, no code',
    ])->assertRedirect();

    $app = DockerApp::where('slug', 'n8n')->firstOrFail();
    expect($app->tagline)->toBe('Automate anything, no code')
        ->and($app->is_featured)->toBeTrue();
});

it('removing the image leaves the selling settings alone', function () {
    logoServer();
    fakeCatalogue([['slug' => 'n8n', 'name' => 'n8n']]);
    Storage::disk('public')->put('docker-apps/n8n-x.png', 'DATA');
    DockerApp::create(['slug' => 'n8n', 'path' => 'docker-apps/n8n-x.png', 'is_featured' => true, 'tagline' => 'keep me']);

    $this->actingAs(logoAdmin(), 'admin')->post(route('admin.docker-apps.destroy'), ['slug' => 'n8n'])->assertRedirect();

    $app = DockerApp::where('slug', 'n8n')->firstOrFail();
    expect($app->path)->toBeNull()
        ->and($app->is_featured)->toBeTrue()
        ->and($app->tagline)->toBe('keep me');
    Storage::disk('public')->assertMissing('docker-apps/n8n-x.png');
});

/*
 * Logos that ship with the product.
 *
 * A fresh install should not open on a wall of letter tiles waiting for
 * somebody to press "fetch logos", so a set is committed to the repository.
 */

it('serves the bundled logos with no database rows at all', function () {
    $map = DockerApp::bundledUrlMap();

    expect($map)->not->toBeEmpty()
        ->and($map['wordpress'] ?? null)->toStartWith('/img/apps/');
    // Relative, like the uploaded ones: the configured app URL is not to be
    // trusted here (a container env var overrides it).
    expect($map['wordpress'])->not->toContain('http');
});

it('prefers an operator upload over the bundled logo', function () {
    DockerApp::create(['slug' => 'wordpress', 'path' => 'docker-apps/wordpress-custom.png']);

    expect(DockerApp::urlMap()['wordpress'])->toBe('/storage/docker-apps/wordpress-custom.png');
});

it('falls back to the bundled logo for apps the operator has not touched', function () {
    DockerApp::create(['slug' => 'n8n', 'path' => 'docker-apps/n8n-custom.png']);

    $map = DockerApp::urlMap();
    expect($map['n8n'])->toBe('/storage/docker-apps/n8n-custom.png')
        ->and($map['wordpress'] ?? null)->toStartWith('/img/apps/');
});

it('decorates catalogue entries with the bundled logo', function () {
    $out = DockerApp::decorate([['slug' => 'wordpress', 'name' => 'WordPress']]);

    expect($out[0]['logo_url_local'])->toStartWith('/img/apps/wordpress');
});
