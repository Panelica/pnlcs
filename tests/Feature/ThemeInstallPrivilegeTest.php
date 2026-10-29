<?php

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Http\UploadedFile;

/**
 * A theme's views are prepended to the view path and compiled as Blade, so a
 * theme zip is code that runs as the web user. The upload sat behind
 * manage_settings, which nobody reads as "may deploy code" - reported
 * privately in October 2026. It now takes a full administrator.
 */
function settingsOnlyAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Settings '.uniqid(),
            'is_full_admin' => false,
            'permissions' => ['manage_settings'],
        ])->id,
    ]);
}

function themeZip(string $slug): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'theme').'.zip';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('theme.json', json_encode(['slug' => $slug, 'name' => 'Test '.$slug, 'version' => '1.0.0']));
    $zip->addFromString('views/client/test.blade.php', 'hello');
    $zip->close();

    return new UploadedFile($path, $slug.'.zip', 'application/zip', null, true);
}

it('refuses a theme upload from a settings-only administrator', function () {
    $this->actingAs(settingsOnlyAdmin(), 'admin')
        ->post(route('admin.settings.appearance.theme.install'), ['theme_zip' => themeZip('evil')])
        ->assertStatus(403);

    expect(is_dir(base_path('themes/evil')))->toBeFalse();
});

it('keeps the upload card off the appearance page for that administrator', function () {
    $this->actingAs(settingsOnlyAdmin(), 'admin')
        ->get(route('admin.settings.appearance'))
        ->assertOk()
        ->assertDontSee('themeUploadForm');
});

it('still lets a full administrator install a theme', function () {
    $slug = 'tpriv'.substr((string) getmypid(), -4);
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.appearance.theme.install'), ['theme_zip' => themeZip($slug)])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(is_dir(base_path('themes/'.$slug)))->toBeTrue();

    \Illuminate\Support\Facades\File::deleteDirectory(base_path('themes/'.$slug));
});
