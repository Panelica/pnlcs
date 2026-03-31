@extends('client.layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="page-header">
    <h1>Announcements</h1>
</div>

@if($announcements->isEmpty())
<div class="card">
    <div class="card-body" style="text-align:center; padding:48px; color:#999;">
        No announcements at this time.
    </div>
</div>
@else
<div style="display:flex; flex-direction:column; gap:10px;">
    @foreach($announcements as $announcement)
    <a href="{{ route('client.announcements.show', $announcement) }}" style="text-decoration:none; color:inherit;">
        <div class="card" style="transition:box-shadow 0.15s;">
            <div class="card-body">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px;">
                    <div style="flex:1;">
                        <div style="font-size:14px; font-weight:600; color:#1a4d80; margin-bottom:6px;">{{ $announcement->title }}</div>
                        <div style="font-size:13px; color:#666; line-height:1.5; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                            {{ strip_tags($announcement->announcement) }}
                        </div>
                    </div>
                    <time style="font-size:12px; color:#999; white-space:nowrap; flex-shrink:0; margin-top:2px;">{{ $announcement->created_at->format('d M Y') }}</time>
                </div>
            </div>
        </div>
    </a>
    @endforeach
</div>
<div style="margin-top:16px;">{{ $announcements->links() }}</div>
@endif

@endsection
