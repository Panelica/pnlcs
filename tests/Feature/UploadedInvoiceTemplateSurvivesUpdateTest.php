<?php

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\File;

/*
 * The one uploaded template an installation had before #361 (custom.html,
 * switched on by the InvoiceTemplate setting) is not known to the renderer any
 * more: it looks for <LANG>_invoice_template_v<VER>.html. Without the
 * migration below, the update would have taken a working custom invoice
 * layout away without saying so.
 */

function rescueMigration(): object
{
    return require base_path('database/migrations/2026_11_01_000001_keep_the_uploaded_invoice_template.php');
}

function rescueDir(): string
{
    return dirname(InvoicePdfService::customTemplatePath());
}

beforeEach(function () {
    File::ensureDirectoryExists(rescueDir());
    File::cleanDirectory(rescueDir());
});

afterEach(fn () => File::cleanDirectory(rescueDir()));

it('keeps printing the template the installation had uploaded', function () {
    File::put(InvoicePdfService::customTemplatePath(), '<html>mine{items}</html>');
    Setting::set('InvoiceTemplate', 'custom', 'general');
    Setting::set('DefaultLanguage', 'pl', 'general');

    rescueMigration()->up();

    $rescued = rescueDir().'/PL_invoice_template_v1.00.html';

    expect(is_file($rescued))->toBeTrue()
        ->and(file_get_contents($rescued))->toBe('<html>mine{items}</html>')
        // custom.html stays where it was, so rolling the update back finds it.
        ->and(is_file(InvoicePdfService::customTemplatePath()))->toBeTrue()
        ->and(app(InvoicePdfService::class)->customTemplate(Invoice::factory()->create(['language' => 'pl'])))
        ->toBe($rescued);
});

it('names the copy English when no default language is set', function () {
    File::put(InvoicePdfService::customTemplatePath(), '<html>mine</html>');
    Setting::set('InvoiceTemplate', 'custom', 'general');
    Setting::where('setting', 'DefaultLanguage')->delete();

    rescueMigration()->up();

    expect(is_file(rescueDir().'/EN_invoice_template_v1.00.html'))->toBeTrue();
});

it('leaves a template the operator had switched off alone', function () {
    File::put(InvoicePdfService::customTemplatePath(), '<html>mine</html>');
    Setting::set('InvoiceTemplate', 'default', 'general');

    rescueMigration()->up();

    expect(glob(rescueDir().'/*_invoice_template_v*.html'))->toBe([]);
});

it('does nothing when language templates are already in use', function () {
    File::put(InvoicePdfService::customTemplatePath(), '<html>mine</html>');
    File::put(rescueDir().'/EN_invoice_template_v2.00.html', '<html>newer</html>');
    Setting::set('InvoiceTemplate', 'custom', 'general');

    rescueMigration()->up();

    expect(glob(rescueDir().'/*_invoice_template_v*.html'))
        ->toBe([rescueDir().'/EN_invoice_template_v2.00.html'])
        ->and(file_get_contents(rescueDir().'/EN_invoice_template_v2.00.html'))->toBe('<html>newer</html>');
});

it('does nothing on an installation that never uploaded one', function () {
    Setting::set('InvoiceTemplate', 'custom', 'general');

    rescueMigration()->up();

    expect(glob(rescueDir().'/*_invoice_template_v*.html'))->toBe([]);
});

it('takes its own copy back and keeps anything else on the way down', function () {
    File::put(InvoicePdfService::customTemplatePath(), '<html>mine</html>');
    Setting::set('InvoiceTemplate', 'custom', 'general');
    Setting::set('DefaultLanguage', 'en', 'general');

    $migration = rescueMigration();
    $migration->up();
    File::put(rescueDir().'/DE_invoice_template_v1.00.html', '<html>hand written</html>');

    $migration->down();

    expect(is_file(rescueDir().'/EN_invoice_template_v1.00.html'))->toBeFalse()
        ->and(is_file(rescueDir().'/DE_invoice_template_v1.00.html'))->toBeTrue();
});

it('runs twice without making a second copy', function () {
    File::put(InvoicePdfService::customTemplatePath(), '<html>mine</html>');
    Setting::set('InvoiceTemplate', 'custom', 'general');
    Setting::set('DefaultLanguage', 'en', 'general');

    $migration = rescueMigration();
    $migration->up();
    File::put(rescueDir().'/EN_invoice_template_v1.00.html', '<html>edited since</html>');
    $migration->up();

    expect(file_get_contents(rescueDir().'/EN_invoice_template_v1.00.html'))->toBe('<html>edited since</html>');
});
