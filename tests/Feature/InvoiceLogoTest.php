<?php

use App\Models\Setting;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\File;

/*
 * The invoice logo, end to end.
 *
 * The PDF service read a 'Logo' setting that no screen has ever written - the
 * appearance screen writes custom_logo_path - and the PDF template never
 * rendered the value anyway. Two dead ends met in the middle, and no invoice
 * ever carried a logo. Found by auditing every settings key against its
 * writers.
 */

function withBrandingFile(string $web, callable $fn): void
{
    $file = public_path(ltrim($web, '/'));
    File::ensureDirectoryExists(dirname($file));
    $existed = file_exists($file);
    if (! $existed) {
        // A 1x1 PNG - a real file, because the service answers file paths.
        file_put_contents($file, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }

    try {
        $fn();
    } finally {
        if (! $existed) {
            @unlink($file);
        }
    }
}

test('the logo uploaded on the appearance screen reaches the invoice', function () {
    Setting::set('Logo', '', 'general');
    Setting::set('custom_logo_path', '/branding/test-invoice-logo.png', 'appearance');

    withBrandingFile('/branding/test-invoice-logo.png', function () {
        $company = app(InvoicePdfService::class)->companyDetails();

        expect($company['logo'])->toBe(public_path('branding/test-invoice-logo.png'));
    });
});

test('a hand-set Logo key still wins over the appearance upload', function () {
    Setting::set('Logo', '/branding/legacy-logo.png', 'general');
    Setting::set('custom_logo_path', '/branding/test-invoice-logo.png', 'appearance');

    withBrandingFile('/branding/legacy-logo.png', function () {
        withBrandingFile('/branding/test-invoice-logo.png', function () {
            $company = app(InvoicePdfService::class)->companyDetails();

            expect($company['logo'])->toBe(public_path('branding/legacy-logo.png'));
        });
    });
});

test('a recorded logo whose file is gone renders as no logo, not a broken invoice', function () {
    Setting::set('Logo', '', 'general');
    Setting::set('custom_logo_path', '/branding/deleted-logo.png', 'appearance');

    expect(app(InvoicePdfService::class)->companyDetails()['logo'])->toBe('');
});

test('a traversal path in the setting is refused', function () {
    Setting::set('Logo', '/../../etc/passwd', 'general');
    Setting::set('custom_logo_path', '', 'appearance');

    expect(app(InvoicePdfService::class)->companyDetails()['logo'])->toBe('');
});

test('the template actually renders the logo it is given', function () {
    $markup = file_get_contents(resource_path('views/pdf/invoice.blade.php'));

    // The value was computed and then never used; the template is the other
    // half of this fix.
    expect($markup)->toContain("company['logo']");
});
