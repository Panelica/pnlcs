<?php

use App\Models\Setting;
use App\Services\ThemeManager;
use Illuminate\Support\Facades\File;

/*
 * A theme could restyle every page but the error pages. Laravel finds those
 * through the errors:: namespace, built from config('view.paths') when an
 * error is rendered; pnlcs only prepended the theme to the view finder, so a
 * theme's errors/404.blade.php was never used. The built-in pages also named
 * the site "PNLCS" in the title, whatever the company was called.
 */

beforeEach(function () {
    $this->theme = base_path('themes/error-pages-test');
    File::ensureDirectoryExists($this->theme.'/views/errors');
    File::put($this->theme.'/views/errors/404.blade.php', '<p>Theme says: nothing here</p>');
});

afterEach(function () {
    File::deleteDirectory($this->theme);
});

test('a theme\'s own 404 page is the one shown', function () {
    Setting::set('active_theme_slug', 'error-pages-test');
    app(ThemeManager::class)->applyViewPaths();

    $this->get('/no-such-page-'.uniqid())->assertNotFound()->assertSee('Theme says: nothing here');
});

test('the built-in error pages carry the company name, not PNLCS', function () {
    Setting::set('CompanyName', 'Acme Hosting');
    app()->forgetInstance('pnlcs.company_name');

    $this->get('/no-such-page-'.uniqid())->assertNotFound()->assertSee(' - Acme Hosting</title>', false)->assertDontSee('PNLCS');
});
