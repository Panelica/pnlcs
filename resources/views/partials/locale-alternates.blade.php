{{-- Language in the address (App\Support\LocaleUrl): this page in every active
     language for search engines, the default language as x-default, and the
     page's own address as canonical. Nothing while the setting is off. --}}
@if(\App\Support\LocaleUrl::enabled() && request()->isMethod('GET'))
@foreach(\App\Support\LocaleUrl::locales() as $altLocale)
<link rel="alternate" hreflang="{{ $altLocale }}" href="{{ \App\Support\LocaleUrl::current($altLocale) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ \App\Support\LocaleUrl::current(\App\Support\LocaleUrl::defaultLocale()) }}">
<link rel="canonical" href="{{ \App\Support\LocaleUrl::current(app()->getLocale()) }}">
@endif
