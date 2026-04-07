@extends("admin.layouts.app")
@section("title", $addon->getDisplayName())
@section("content")

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h4 style="margin:0;">{{ $addon->getDisplayName() }}</h4>
        <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ $addon->getDescription() }}</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:12px;color:var(--pn-muted);">v{{ $addon->getVersion() }} by {{ $addon->getAuthor() }}</span>
        <a href="{{ route('admin.config.addons') }}" class="btn btn-sm btn-outline">{{ __('admin.nav.back') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:16px;">
        {!! $output !!}
    </div>
</div>

@endsection
