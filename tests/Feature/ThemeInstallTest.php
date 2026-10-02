<?php

use App\Services\ThemeManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/*
 * Installing an uploaded theme.
 *
 * The zip was extracted to the system temp folder and moved into themes/
 * without checking the move: where the two are different filesystems (a
 * container's /tmp and a mounted volume) the move failed, the installer said
 * "success", the theme never appeared - and an update had already deleted
 * the version that was there.
 */

function tiManager(string $themes): ThemeManager
{
    $manager = new ThemeManager;
    $prop = new ReflectionProperty(ThemeManager::class, 'themesPath');
    $prop->setValue($manager, $themes);

    return $manager;
}

function tiZip(string $version, array $extra = []): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'tizip').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('demo/theme.json', json_encode(['name' => 'Demo', 'slug' => 'demo', 'version' => $version]));
    foreach ($extra as $name => $body) {
        $zip->addFromString('demo/'.$name, $body);
    }
    $zip->close();

    return new UploadedFile($path, 'demo.zip', 'application/zip', null, true);
}

beforeEach(function () {
    $this->themes = sys_get_temp_dir().'/ti-themes-'.uniqid();
    File::makeDirectory($this->themes);
});

afterEach(function () {
    @chmod($this->themes, 0755);
    File::deleteDirectory($this->themes);
});

it('installs a theme and leaves nothing behind', function () {
    $result = tiManager($this->themes)->install(tiZip('1.0.0', ['views/a.blade.php' => 'A']));

    expect($result['success'])->toBeTrue()
        ->and(is_file($this->themes.'/demo/views/a.blade.php'))->toBeTrue()
        ->and(File::directories($this->themes))->toBe([$this->themes.'/demo']);
});

it('replaces the installed version on an update', function () {
    $manager = tiManager($this->themes);
    $manager->install(tiZip('1.0.0', ['old.txt' => 'old']));

    $result = $manager->install(tiZip('1.1.0', ['new.txt' => 'new']));

    expect($result['success'])->toBeTrue()
        ->and(is_file($this->themes.'/demo/new.txt'))->toBeTrue()
        ->and(is_file($this->themes.'/demo/old.txt'))->toBeFalse()
        ->and(json_decode(file_get_contents($this->themes.'/demo/theme.json'), true)['version'])->toBe('1.1.0')
        ->and(File::directories($this->themes))->toBe([$this->themes.'/demo']);
});

it('says so when the themes folder cannot be written, and changes nothing', function () {
    $manager = tiManager($this->themes);
    $manager->install(tiZip('1.0.0', ['old.txt' => 'old']));
    chmod($this->themes, 0555);

    $result = $manager->install(tiZip('1.1.0', ['new.txt' => 'new']));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('messages.theme.not_writable'))
        ->and(is_file($this->themes.'/demo/old.txt'))->toBeTrue();
})->skip(fn () => function_exists('posix_getuid') && posix_getuid() === 0, 'root can write anywhere');

// File::directories() leaves dot-folders out, so the work folders are never
// listed as themes; this keeps it that way.
it('does not list an install that was cut short', function () {
    File::makeDirectory($this->themes.'/.pnlcs_install_abc');
    file_put_contents($this->themes.'/.pnlcs_install_abc/theme.json', json_encode(['name' => 'Half', 'slug' => 'half']));
    tiManager($this->themes)->install(tiZip('1.0.0'));

    expect(array_keys(tiManager($this->themes)->getInstalled()))->toBe(['demo']);
});
