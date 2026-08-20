@extends("client.layouts.app")
@section("title", $announcement->title)
@section("content")

<a href="{{ route("client.announcements.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.announcements.all_announcements') }}
</a>

<div class="pn-card">
    <div class="pn-card-header">
        <div>
            <div style="font-size:18px;font-weight:800;color:var(--primary);letter-spacing:-0.3px">{{ $announcement->title }}</div>
            <div class="text-muted text-sm" style="margin-top:4px">{{ __('client.announcements.published_at') }} {{ $announcement->created_at->format(date_fmt()) }} at {{ $announcement->created_at->format("H:i") }}</div>
        </div>
    </div>
    <div class="pn-card-body">
        <div style="font-size:14px;line-height:1.85;color:var(--text)">
            {!! nl2br(e($announcement->announcement)) !!}
        </div>
    </div>
</div>

@endsection
