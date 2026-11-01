<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DockerApp;
use App\Models\Server;
use App\Support\ServerAddress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * A catalogue image is stored on the public disk and served from the panel's
 * own address, so:
 * - it is named after what its bytes are, not after the upload's name or the
 *   URL, and an SVG that carries script is not kept;
 * - "fetch from URL" goes only to public addresses, on every redirect too,
 *   not to this server, its network or a metadata service.
 */
function logoSafetyAdmin(): Admin
{
    Server::create([
        'name' => 'Panel', 'hostname' => 'panel.logo.test', 'ip_address' => '10.0.0.13',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);

    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function logoSafetyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

function logoSafetyUpload(string $name, string $bytes): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, null, null, true);
}

beforeEach(function () {
    Storage::fake('public');
    ServerAddress::resolveUsing(fn (string $host) => match ($host) {
        'internal.example.test' => ['10.0.0.5'],
        default => ['93.184.216.34'],
    });
});

afterEach(function () {
    ServerAddress::resolveUsing(null);
});

test('an uploaded image is stored under the extension of what it is, not of its name', function () {
    $this->actingAs(logoSafetyAdmin(), 'admin')->post(route('admin.docker-apps.upload'), [
        'slug' => 'n8n',
        // A real file, so its type is read from the content as on a real upload.
        'logo' => logoSafetyUpload('n8n.html', logoSafetyPng()),
    ])->assertSessionHasNoErrors();

    expect(DockerApp::where('slug', 'n8n')->value('path'))->toEndWith('.png');
});

test('an SVG with script is refused, a plain one is kept', function () {
    $admin = logoSafetyAdmin();
    $evil = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script><rect width="1" height="1"/></svg>';
    $handler = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>';
    $plain = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><rect width="1" height="1" fill="#000"/></svg>';

    foreach (['evil' => $evil, 'handler' => $handler] as $slug => $svg) {
        $this->actingAs($admin, 'admin')->post(route('admin.docker-apps.upload'), [
            'slug' => $slug, 'logo' => logoSafetyUpload($slug.'.svg', $svg),
        ])->assertSessionHasErrors('logo');
    }
    $this->actingAs($admin, 'admin')->post(route('admin.docker-apps.upload'), [
        'slug' => 'plain', 'logo' => logoSafetyUpload('plain.svg', $plain),
    ])->assertSessionHasNoErrors();

    expect(DockerApp::pluck('slug')->all())->toBe(['plain']);
});

test('a fetch is not made to a private or internal address', function (string $url) {
    Http::fake(['*' => Http::response(logoSafetyPng(), 200, ['Content-Type' => 'image/png'])]);

    $this->actingAs(logoSafetyAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => $url,
    ])->assertSessionHas('error');

    Http::assertNothingSent();
    expect(DockerApp::count())->toBe(0);
})->with([
    'metadata service' => 'http://169.254.169.254/latest/logo.png',
    'loopback' => 'http://127.0.0.1:8080/logo.png',
    'name on the private network' => 'https://internal.example.test/logo.png',
]);

test('a redirect to an internal address is not followed', function () {
    Http::fake([
        'logos.example.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.png']),
        '*' => Http::response(logoSafetyPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->actingAs(logoSafetyAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.example.test/n8n.png',
    ])->assertSessionHas('error');

    Http::assertSentCount(1);
    expect(DockerApp::count())->toBe(0);
});

test('a page fetched from an address ending in .png is not stored as an image', function () {
    Http::fake(['*' => Http::response('<html><script>alert(1)</script></html>', 200, ['Content-Type' => 'image/png'])]);

    $this->actingAs(logoSafetyAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.example.test/n8n.png',
    ])->assertSessionHas('error');

    expect(DockerApp::count())->toBe(0);
});

test('a public image is still fetched', function () {
    Http::fake(['*' => Http::response(logoSafetyPng(), 200, ['Content-Type' => 'image/png'])]);

    $this->actingAs(logoSafetyAdmin(), 'admin')->post(route('admin.docker-apps.fetch'), [
        'slug' => 'n8n', 'url' => 'https://logos.example.test/n8n.png',
    ])->assertSessionHas('success');

    expect(DockerApp::where('slug', 'n8n')->value('path'))->toEndWith('.png');
});
