{{-- What search engines and link previews read about this page (App\Support\Seo).
     Pass 'title' (the page title as the browser shows it) and, when the page has
     one, 'description'; otherwise the shop's description from Setup > General.
     The address shared is the page's own (canonical comes from the layout).
     Without parameters (the client layout) it reads the page's sections:
     title, meta_description, og_type. A page that writes these tags itself
     (an addon's blog, with its own image, dates and language versions)
     sets @section('seo_own', '1') and gets none of them from here. Once a
     social profile is set, the home page also names the shop to search
     engines: schema.org Organization, with its profiles as sameAs. --}}
@unless($__env->hasSection('seo_own'))
@php
    $title ??= trim($__env->yieldContent('title', e(__('client.my_account')))).' - '.company_name();
    $description ??= trim($__env->yieldContent('meta_description')) ?: null;
    $type ??= trim($__env->yieldContent('og_type')) ?: 'website';
    $seoDescription = \App\Support\Seo::description($description ?? null);
    $seoImage = \App\Support\Seo::image();
    $seoTwitter = \App\Support\Seo::twitter();
    $seoTitle = trim(html_entity_decode((string) ($title ?? company_name()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
@endphp
@if($seoDescription !== '')
<meta name="description" content="{{ $seoDescription }}">
@endif
<meta property="og:type" content="{{ $type ?? 'website' }}">
<meta property="og:site_name" content="{{ company_name() }}">
<meta property="og:title" content="{{ $seoTitle }}">
@if($seoDescription !== '')
<meta property="og:description" content="{{ $seoDescription }}">
@endif
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:locale" content="{{ \App\Support\Seo::ogLocale() }}">
@if($seoImage !== '')
<meta property="og:image" content="{{ $seoImage }}">
@endif
<meta name="twitter:card" content="{{ $seoImage !== '' ? 'summary_large_image' : 'summary' }}">
@if($seoTwitter !== '')
<meta name="twitter:site" content="{{ $seoTwitter }}">
@endif
@if(request()->routeIs('home') && \App\Support\SocialProfiles::all())
<script type="application/ld+json">{!! json_encode(\App\Support\Seo::organization(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
@endunless
