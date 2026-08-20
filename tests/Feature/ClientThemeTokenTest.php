<?php

/*
 * The client area's colours come from the theme, or dark mode is a lie.
 *
 * Dark mode swaps a handful of CSS variables on the root element. Any view
 * that writes a light-theme colour inline - a white background, a grey text, a
 * pale border - stays light when everything around it goes dark. There were
 * 138 of these across 21 files; the light theme did not change by a pixel when
 * they were swapped for the variables, because the values replaced were the
 * variables' own light values. This test keeps the count at zero.
 */

/** Hex colours that are exactly what a theme token already provides. */
const FORBIDDEN_INLINE_COLOURS = [
    'color:#777', 'color:#999', 'color:#888', 'color:#64748b', 'color:#94a3b8',
    'color:#6b7280', 'color:#9ca3af', 'color:#334155', 'color:#1e293b',
    'color:#111827', 'color:#333',
    'background:#fff;', 'background:#fff"', 'background:#f8fafc', 'background:#f9fafb',
    'background:#f1f5f9', 'background:#fafafa',
    'solid #e2e8f0', 'solid #e5e7eb', 'solid #eee',
];

test('client views carry no light-theme colour a token already provides', function () {
    $offences = [];
    $files = glob(resource_path('views/client/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE);

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        foreach (FORBIDDEN_INLINE_COLOURS as $needle) {
            $count = substr_count($source, $needle);
            if ($count > 0) {
                $offences[] = str_replace(resource_path('views/'), '', $file)." uses '$needle' x$count";
            }
        }
    }

    expect($offences)->toBe([], "Use var(--muted)/var(--text)/var(--card)/var(--bg)/var(--border) instead:\n".implode("\n", $offences));
});

test('the classes the client pages lean on are actually defined', function () {
    $layout = (string) file_get_contents(resource_path('views/client/layouts/app.blade.php'));

    // .btn-default was used by five pages and defined by none; the Cancel
    // button rendered as bare text. Same for .text-danger on error messages.
    foreach (['.btn-default', '.text-danger', '.form-group', '.form-label', '.form-control'] as $class) {
        expect($layout)->toContain($class);
    }
});

test('dark mode restates the badges', function () {
    $layout = (string) file_get_contents(resource_path('views/client/layouts/app.blade.php'));

    // Badges are fixed light pastels; without a dark restatement they turn
    // into pale chips with unreadable text.
    expect($layout)->toContain('[data-theme="dark"] .badge-active')
        ->and($layout)->toContain('[data-theme="dark"] .badge-pending')
        ->and($layout)->toContain('[data-theme="dark"] .badge-overdue');
});

test('the server renders the theme the cookie asks for', function () {
    // The toggle writes a raw JavaScript cookie. With cookie encryption on,
    // the server could never read it: every page was born light and flashed
    // dark after a script ran. The cookie is excepted from encryption, so the
    // page arrives dark.
    $response = $this->withUnencryptedCookie('pnlcs_theme', 'dark')
        ->get(route('client.login'));

    $response->assertOk();
    expect($response->getContent())->toContain('data-theme="dark"');
});

test('the footer is pinned to the bottom of short pages', function () {
    $layout = (string) file_get_contents(resource_path('views/client/layouts/app.blade.php'));

    // A short page - the transfer form, an empty list - left the footer
    // floating mid-screen under the content. The body is a full-height column,
    // the main area stretches, the footer takes the rest.
    expect($layout)->toContain('min-height:100vh;display:flex;flex-direction:column')
        ->and($layout)->toContain('width:100%;flex:1')
        ->and($layout)->toContain('margin-top:auto');
});

test('the dark theme borders its panels with the border token, not the text one', function () {
    $layout = (string) file_get_contents(resource_path('views/client/layouts/app.blade.php'));

    // A colour sweep once turned border-color:#334155 into
    // border-color:var(--text) - which in the dark is the light text colour,
    // drawing bright lines around every card. The border token is the border.
    expect($layout)->not->toContain('border-color:var(--text)')
        ->and(substr_count($layout, 'border-color:var(--border) !important'))->toBeGreaterThanOrEqual(7);
});
