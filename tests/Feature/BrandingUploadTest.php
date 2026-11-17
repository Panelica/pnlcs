<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;

/*
 * The appearance screen said "PNG, ICO, SVG" for the favicon and "PNG, JPG,
 * SVG, WebP" for the logo. Laravel's image rule has neither ICO nor SVG, so
 * an .ico favicon was refused and SVG never worked anywhere (#456).
 *
 * ICO now works. SVG stays out on purpose: the file is served from
 * public/branding on the panel's own origin, where a script inside an SVG
 * would run. And the stored name takes its extension from the content, so a
 * PNG uploaded as "x.html" is not served as a page.
 */

function brandingAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function pngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/** A one-image .ico: the 6-byte header, one 16-byte directory entry, then a PNG. */
function icoBytes(): string
{
    $png = pngBytes();

    return pack('vvv', 0, 1, 1).pack('CCCCvvVV', 1, 1, 0, 0, 1, 32, strlen($png), 22).$png;
}

/**
 * A real upload: its type is read from the bytes, the way PHP's own uploads
 * are. UploadedFile::fake() takes the type from the file name instead.
 */
function realUpload(string $name, string $bytes): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'brand');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, null, null, true);
}

afterEach(function () {
    foreach (glob(public_path('branding/{logo,favicon}_*'), GLOB_BRACE) ?: [] as $file) {
        @unlink($file);
    }
});

test('an .ico favicon is accepted', function () {
    $this->actingAs(brandingAdmin(), 'admin')
        ->post(route('admin.settings.appearance.favicon'), [
            'favicon' => realUpload('favicon.ico', icoBytes()),
        ])
        ->assertSessionHasNoErrors();

    expect(Setting::get('custom_favicon_path'))->toEndWith('.ico');
});

test('a PNG favicon and logo are accepted', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.appearance.favicon'), ['favicon' => UploadedFile::fake()->createWithContent('f.png', pngBytes())])
        ->assertSessionHasNoErrors();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.appearance.logo'), ['logo' => UploadedFile::fake()->createWithContent('l.png', pngBytes())])
        ->assertSessionHasNoErrors();

    expect(Setting::get('custom_favicon_path'))->toEndWith('.png')
        ->and(Setting::get('custom_logo_path'))->toEndWith('.png');
});

test('an SVG is refused for both, and the screen no longer offers it', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    $admin = brandingAdmin();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.appearance.favicon'), ['favicon' => UploadedFile::fake()->createWithContent('f.svg', $svg)])
        ->assertSessionHasErrors('favicon');
    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.appearance.logo'), ['logo' => UploadedFile::fake()->createWithContent('l.svg', $svg)])
        ->assertSessionHasErrors('logo');

    $html = $this->actingAs($admin, 'admin')->get(route('admin.settings.appearance'))->assertOk()->getContent();

    expect($html)->not->toContain('image/svg+xml')
        ->and($html)->toContain(__('admin.appearance.favicon_formats'))
        ->and(__('admin.appearance.favicon_formats'))->not->toContain('SVG')
        ->and(__('admin.appearance.logo_formats'))->not->toContain('SVG');
});

test('the stored file is named after its content, not the name it was sent with', function () {
    $this->actingAs(brandingAdmin(), 'admin')
        ->post(route('admin.settings.appearance.logo'), ['logo' => realUpload('logo.html', pngBytes())]);

    $path = (string) Setting::get('custom_logo_path', '');

    // The rule passes it - the bytes are a PNG - so the risk was the name.

    expect($path)->toEndWith('.png');
});
