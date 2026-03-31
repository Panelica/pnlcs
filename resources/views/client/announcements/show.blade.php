@extends('client.layouts.app')
@section('title', $announcement->title)
@section('content')

<div style="margin-bottom:14px;">
    <a href="{{ route('client.announcements.index') }}" style="color:#337ab7; font-size:13px;">&larr; All Announcements</a>
</div>

<div class="card" style="max-width:800px;">
    <div class="card-header">
        <div>
            <div style="font-size:16px; font-weight:600; color:#1a4d80;">{{ $announcement->title }}</div>
            <div style="font-size:12px; color:#999; margin-top:3px; font-weight:400;">
                Published {{ $announcement->created_at->format('d F Y, H:i') }}
            </div>
        </div>
    </div>
    <div class="card-body">
        <div style="font-size:13px; line-height:1.8; color:#444;">
            {!! nl2br(e($announcement->announcement)) !!}
        </div>
    </div>
</div>

@endsection
