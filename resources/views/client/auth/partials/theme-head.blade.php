{{-- The shop's favicon and theme colours on the client sign-in pages, the
     way login.blade.php has them (ThemeComposer). --card, --border and
     --text are read by the smaller pages and were defined nowhere. --}}
@if(!empty($customFavicon))
<link rel="icon" href="{{ $customFavicon }}">
@endif
@if(!empty($themeCssVars))
<style id="theme-vars">{!! $themeCssVars !!}</style>
@endif
<style>:root{--card:var(--theme-card-bg,#ffffff);--border:var(--theme-border-color,#e2e8f0);--text:var(--theme-text-color,#333333)}</style>
