@extends('client.layouts.app')
@section('title', '#'. $ticket->tid .' - '. $ticket->title)
@section('styles')
<style>
    .ticket-meta { display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; color: #777; margin-bottom: 20px; }
    .ticket-meta span { display: flex; align-items: center; gap: 4px; }
    .message-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 14px; }
    .message-card.staff { background: #f0f6ff; border-color: #c8dff7; }
    .message-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #e5e5e5; font-size: 13px; }
    .message-header.staff { border-bottom-color: #c8dff7; }
    .message-author { font-weight: 600; color: #333; }
    .message-author.staff { color: #1a4d80; }
    .message-date { font-size: 12px; color: #999; }
    .message-body { padding: 14px; font-size: 13px; line-height: 1.7; color: #444; }
    .reply-box { margin-top: 20px; }
</style>
@endsection
@section('content')

<div class="page-header">
    <div>
        <h1>#{{ $ticket->tid }} &mdash; {{ $ticket->title }}</h1>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <span class="badge badge-{{ strtolower(str_replace(' ', '-', $ticket->status)) }}">{{ ucfirst($ticket->status) }}</span>
        <span class="badge badge-{{ strtolower($ticket->priority) }}" style="background:#fcf8e3; color:#8a6d3b;">{{ ucfirst($ticket->priority) }}</span>
    </div>
</div>

<div class="ticket-meta">
    <span>&#128194; {{ $ticket->department->name ?? 'General' }}</span>
    <span>&#128197; Opened {{ $ticket->created_at->format('d M Y H:i') }}</span>
    @if($ticket->last_reply)<span>&#128490; Last reply {{ $ticket->last_reply->diffForHumans() }}</span>@endif
</div>

{{-- Original message --}}
<div class="message-card">
    <div class="message-header">
        <span class="message-author">{{ $ticket->name ?? auth()->user()->full_name }}</span>
        <span class="message-date">{{ $ticket->created_at->format('d M Y H:i') }}</span>
    </div>
    <div class="message-body">{!! nl2br(e($ticket->message)) !!}</div>
</div>

{{-- Replies --}}
@foreach($ticket->replies as $reply)
<div class="message-card {{ $reply->admin ? 'staff' : '' }}">
    <div class="message-header {{ $reply->admin ? 'staff' : '' }}">
        <span class="message-author {{ $reply->admin ? 'staff' : '' }}">
            {{ $reply->admin ? '&#128274; Staff: '. $reply->admin : auth()->user()->full_name }}
        </span>
        <span class="message-date">{{ $reply->created_at->format('d M Y H:i') }}</span>
    </div>
    <div class="message-body">{!! nl2br(e($reply->message)) !!}</div>
</div>
@endforeach

{{-- Reply form --}}
@if($ticket->status !== 'closed')
<div class="reply-box">
    <div class="card">
        <div class="card-header">Reply</div>
        <div class="card-body">
            <form method="POST" action="{{ route('client.tickets.reply', $ticket) }}">
                @csrf
                @if($errors->any())<div class="alert alert-error" style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:9px 12px;border-radius:4px;font-size:13px;margin-bottom:12px;">{{ $errors->first() }}</div>@endif
                <div class="form-group">
                    <label class="form-label">Message <span style="color:#c43c35;">*</span></label>
                    <textarea name="message" rows="6" required class="form-control" placeholder="Type your reply here..."></textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary">Post Reply</button>
                    <a href="{{ route('client.tickets.index') }}" class="btn btn-default">Back to Tickets</a>
                </div>
            </form>
        </div>
    </div>
</div>
@else
<div style="margin-top:16px; padding:12px 16px; background:#f5f5f5; border:1px solid #e0e0e0; border-radius:4px; font-size:13px; color:#777; text-align:center;">
    This ticket is closed. <a href="{{ route('client.tickets.create') }}" style="color:#337ab7;">Open a new ticket</a> if you need further assistance.
</div>
@endif

@endsection
