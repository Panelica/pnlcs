@extends("admin.layouts.app")
@section("title", __("admin.addon_modules.title"))
@section("content")

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h4 style="margin:0;">{{ __('admin.addon_modules.title') }}</h4>
        <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ __('admin.addon_modules.description') }}</p>
    </div>
</div>

@if($addons->isEmpty())
<div class="card"><div class="card-body" style="text-align:center;padding:48px;">
    <p style="color:var(--pn-muted);">{{ __('admin.addon_modules.no_modules') }}</p>
</div></div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:16px;">
    @foreach($addons as $name => $addon)
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:600;">{{ $addon->getDisplayName() }}</span>
            <span style="font-size:11px;color:var(--pn-muted);">v{{ $addon->getVersion() }}</span>
        </div>
        <div class="card-body" style="padding:16px;">
            <p style="font-size:13px;color:var(--pn-muted);margin:0 0 12px;">{{ $addon->getDescription() }}</p>
            <div style="font-size:12px;margin-bottom:12px;">
                <span>{{ __('admin.addon_modules.author') }}: <b>{{ $addon->getAuthor() }}</b></span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <form method="POST" action="{{ route('admin.config.addons.modules.toggle', $name) }}" style="display:inline;">
                    @csrf
                    @if($statuses[$name] ?? false)
                        <button type="submit" class="btn btn-sm" style="background:#c43c35;color:#fff;">{{ __('admin.addon_modules.deactivate') }}</button>
                        <a href="{{ route('admin.config.addons.modules.show', $name) }}" class="btn btn-sm btn-primary">{{ __('admin.addon_modules.open') }}</a>
                    @else
                        <button type="submit" class="btn btn-sm" style="background:#46a546;color:#fff;">{{ __('admin.addon_modules.activate') }}</button>
                    @endif
                </form>
                <span style="font-size:11px;font-weight:600;color:{{ ($statuses[$name] ?? false) ? '#46a546' : '#999' }};">
                    {{ ($statuses[$name] ?? false) ? __('admin.addon_modules.status_active') : __('admin.addon_modules.status_inactive') }}
                </span>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
