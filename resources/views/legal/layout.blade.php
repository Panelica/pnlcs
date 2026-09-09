{{--
     LEGAL — shared chrome.

     Reuses the site's own navigation and footer so a customer reading the
     terms is visibly still on the same sitem. The typography is deliberately
     plain and high-contrast: these documents get printed, emailed to lawyers
     and read on phones, and a legal text set in a decorative style is harder
     to rely on than one set like a contract.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('legal-title') — {{ $brandName ?? company_name() }}</title>
    <meta name="description" content="@yield('legal-description')">
    {{-- Crawlable: several payment providers verify that the terms and privacy
         pages are publicly indexable before approving a merchant account. --}}
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    @if(!empty($customFavicon))
        <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
        <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    @include('sections.styles')
    @if(!empty($activeThemeAssets))
        <link rel="stylesheet" href="{{ $activeThemeAssets }}/css/theme.css">
    @endif
    <style>
        .legal-wrap { max-width: 1180px; margin: 0 auto; padding: 40px 20px 72px; }
        .legal-head { padding: 24px 0 28px; border-bottom: 1px solid var(--legal-line); margin-bottom: 32px; }
        .legal-head h1 { font-size: 30px; line-height: 1.25; font-weight: 800; margin: 0 0 10px; letter-spacing: -.02em; }
        .legal-head p { margin: 0; font-size: 15px; color: var(--legal-muted); max-width: 70ch; }
        .legal-crumb { font-size: 13px; color: var(--legal-muted); margin-bottom: 14px; }
        .legal-crumb a { color: var(--legal-muted); text-decoration: none; }
        .legal-crumb a:hover { text-decoration: underline; }
        .legal-grid { display: grid; grid-template-columns: 268px minmax(0, 1fr); gap: 44px; align-items: start; }
        .legal-side { position: sticky; top: 92px; }
        .legal-side-title { font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: none;
                            color: var(--legal-muted); margin: 0 0 12px; }
        .legal-side a { display: block; padding: 8px 12px; border-radius: 8px; font-size: 13.5px; line-height: 1.4;
                        color: var(--legal-ink); text-decoration: none; margin-bottom: 2px; }
        .legal-side a:hover { background: var(--legal-hover); }
        .legal-side a.is-active { background: var(--legal-active); font-weight: 600; }
        .legal-body { font-size: 15.5px; line-height: 1.72; color: var(--legal-ink); max-width: 78ch; }
        .legal-body h2 { font-size: 20px; font-weight: 700; margin: 40px 0 12px; letter-spacing: -.01em;
                         padding-top: 4px; scroll-margin-top: 96px; }
        .legal-body h2:first-child { margin-top: 0; }
        .legal-body h3 { font-size: 16.5px; font-weight: 700; margin: 26px 0 8px; }
        .legal-body p { margin: 0 0 14px; }
        .legal-body ul, .legal-body ol { margin: 0 0 16px; padding-left: 22px; }
        .legal-body li { margin-bottom: 7px; }
        .legal-body a { color: var(--legal-link); }
        .legal-body strong { font-weight: 650; }
        .legal-body table { width: 100%; border-collapse: collapse; margin: 8px 0 20px; font-size: 14px; display: block; overflow-x: auto; }
        .legal-body th, .legal-body td { border: 1px solid var(--legal-line); padding: 9px 12px; text-align: left; vertical-align: top; }
        .legal-body th { background: var(--legal-hover); font-weight: 650; }
        .legal-note { border: 1px solid var(--legal-line); border-left: 3px solid var(--legal-accent);
                      background: var(--legal-hover); border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 14.5px; }
        .legal-note p:last-child { margin-bottom: 0; }
        .legal-meta { font-size: 13px; color: var(--legal-muted); margin-top: 44px; padding-top: 18px;
                      border-top: 1px solid var(--legal-line); }
        .legal-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(272px, 1fr)); gap: 14px; }
        .legal-card { display: block; border: 1px solid var(--legal-line); border-radius: 11px; padding: 18px 20px;
                      text-decoration: none; color: var(--legal-ink); transition: border-color .15s, transform .15s; }
        .legal-card:hover { border-color: var(--legal-accent); transform: translateY(-2px); }
        .legal-card i { font-size: 19px; color: var(--legal-accent); }
        .legal-card b { display: block; font-size: 15px; font-weight: 650; margin: 9px 0 5px; line-height: 1.35; }
        .legal-card span { font-size: 13.5px; color: var(--legal-muted); line-height: 1.5; }
        .legal-tag { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 5px;
                     background: var(--legal-active); color: var(--legal-muted); margin-left: 6px; vertical-align: 2px; }
        :root { --legal-ink: #1e2430; --legal-muted: #667085; --legal-line: #e3e7ee; --legal-hover: #f6f8fb;
                --legal-active: #eaeff7; --legal-link: #2563eb; --legal-accent: #2563eb; }
        [data-theme="dark"] { --legal-ink: #dfe4ec; --legal-muted: #97a1b3; --legal-line: #2c3444; --legal-hover: #1a2029;
                --legal-active: #232b38; --legal-link: #7aa5f7; --legal-accent: #7aa5f7; }
        @media (max-width: 900px) {
            .legal-grid { grid-template-columns: 1fr; gap: 26px; }
            .legal-side { position: static; }
            .legal-head h1 { font-size: 25px; }
        }
        @media print {
            .main-nav, .topbar, .footer, .legal-side, .legal-crumb { display: none !important; }
            .legal-grid { grid-template-columns: 1fr; }
            .legal-body { max-width: none; font-size: 11pt; }
        }
    </style>
</head>
<body x-data="{ mobileMenu: false }">
    @include('sections.topbar')
    @include('sections.navigation', ['apps' => []])

    <div class="legal-wrap">
        @yield('legal-content')
    </div>

    @include('sections.footer', ['content' => collect()])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
