{{--
    Quick links to a service's hosting tools, for rows in listing pages.

    Which tools exist is the service's own answer (hostingFeatureKeys), so a
    container plan shows Apps alone and a non-hosting service shows nothing at
    all. Only a short, fixed set is linked here - the service page still lists
    everything - because a row is not the place for nine links.
--}}
@php
    $svcTools = $svc->hostingFeatureKeys();
    $svcShortcuts = [
        'containers' => ['client.services.containers', 'ri-apps-2-line', __('client.hosting.tools.apps')],
        'files' => ['client.services.files', 'ri-folder-line', __('client.hosting.tools.files')],
        'emails' => ['client.services.emails', 'ri-mail-line', __('client.hosting.tools.emails')],
        'databases' => ['client.services.databases', 'ri-database-2-line', __('client.hosting.tools.databases')],
    ];
    $svcShown = array_filter($svcShortcuts, fn ($v, $k) => in_array($k, $svcTools, true), ARRAY_FILTER_USE_BOTH);
@endphp
{{-- Inline, like the hosting pages do: the client layout has no styles stack,
     and the CSS bundle is built by Vite, which a blade-only change must not
     depend on. @once keeps it to one copy per page however many rows there are. --}}
@once
<style>
    .svc-tools{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
    .svc-tool{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;line-height:1;
        padding:4px 8px;border:1px solid var(--border);border-radius:999px;color:var(--muted);
        background:var(--bg);text-decoration:none;transition:color .14s,border-color .14s,background .14s}
    .svc-tool:hover{color:var(--primary);border-color:var(--primary);background:var(--primary-light)}
    .svc-tool i{font-size:12px}
</style>
@endonce
@if($svcShown)
<div class="svc-tools">
    @foreach($svcShown as $key => [$route, $icon, $label])
    <a href="{{ route($route, $svc) }}" class="svc-tool" title="{{ $label }}"><i class="{{ $icon }}"></i>{{ $label }}</a>
    @endforeach
</div>
@endif
