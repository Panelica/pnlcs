<!DOCTYPE html>
<html lang="en" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName ?? 'PNLCS' }} — Professional Web Hosting, Domains & Servers</title>
    <meta name="description" content="{{ $brandName ?? 'PNLCS' }} — reliable web hosting, VPS servers, domains, and SSL.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
</head>
<body x-data="{ mobileMenu: false }">

    @include('sections.topbar')
    @include('sections.navigation')

    @foreach($sections as $section)
        @if($section->is_enabled && $section->slug !== 'footer' && view()->exists("sections.{$section->slug}"))
            @include("sections.{$section->slug}", [
                'section' => $section,
                'content' => $sectionContent[$section->slug] ?? collect(),
                'products' => $products ?? collect(),
                'domainPricing' => $domainPricing ?? collect(),
            ])
        @endif
    @endforeach

    @include('sections.footer', [
        'content' => $sectionContent['footer'] ?? collect(),
    ])

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Dark mode JS --}}
    <script>
    function toggleDarkMode() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var newMode = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newMode);
        document.cookie = 'pnlcs_theme=' + newMode + ';path=/;max-age=31536000;SameSite=Lax';
        var li = document.getElementById('lightIcon');
        var di = document.getElementById('darkIcon');
        if (li && di) {
            li.style.display = isDark ? '' : 'none';
            di.style.display = isDark ? 'none' : '';
        }
    }
    (function() {
        var m = document.cookie.match(/pnlcs_theme=(\w+)/);
        if (m && m[1] === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            var li = document.getElementById('lightIcon');
            var di = document.getElementById('darkIcon');
            if (li) li.style.display = '';
            if (di) di.style.display = 'none';
        }
    })();
    </script>

    {{-- NO Three.js globe — Flavor uses CSS particles instead --}}
</body>
</html>
