@extends("client.layouts.app")
@section("title", __("client.announcements.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Announcements</h1>
        <p class="pn-page-subtitle">Latest news and updates from our team.</p>
    </div>
</div>

@if($announcements->isEmpty())
<div class="pn-card">
    <div class="pn-empty">
        <div class="pn-empty-icon">&#128227;</div>
        <p>No announcements at this time.</p>
    </div>
</div>
@else
<div style="display:flex;flex-direction:column;gap:12px">
    @foreach($announcements as $announcement)
    <a href="{{ route("client.announcements.show", $announcement) }}" style="text-decoration:none;color:inherit">
        <div class="pn-card" style="padding:20px 24px;transition:all 0.2s;display:flex;align-items:flex-start;gap:18px">
            <div style="width:44px;height:44px;background:var(--primary-light);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="20" height="20" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:14.5px;font-weight:700;color:var(--primary);margin-bottom:5px">{{ $announcement->title }}</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.55;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                    {{ strip_tags($announcement->announcement) }}
                </div>
            </div>
            <time style="font-size:12px;color:var(--muted);white-space:nowrap;flex-shrink:0;margin-top:2px">{{ $announcement->created_at->format("d M Y") }}</time>
        </div>
    </a>
    @endforeach
</div>
<div class="mt-16">{{ $announcements->links() }}</div>
@endif

@endsection
