{{-- The shop's favicon and theme colours on the admin sign-in pages, like
     the client ones (ThemeComposer). --}}
@if(!empty($customFavicon))
<link rel="icon" href="{{ $customFavicon }}">
@endif
@if(!empty($themeCssVars))
<style id="theme-vars">{!! $themeCssVars !!}</style>
@endif
