@extends('admin.layouts.app')
@section('title', '#' . $ticket->tid . ' - ' . $ticket->title)
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>
        #{{ $ticket->tid }} &mdash; {{ $ticket->title }}
        <span class="badge-{{ strtolower($ticket->status) }}" style="font-size:12px;vertical-align:middle;margin-left:6px;">{{ ucfirst($ticket->status) }}</span>
        <span style="font-size:12px;vertical-align:middle;margin-left:4px;padding:2px 8px;background:#e9e9e9;border-radius:3px;color:#555;">{{ ucfirst($ticket->priority) }}</span>
    </h1>
    <a href="{{ route('admin.tickets.index') }}" class="btn btn-default btn-sm">&larr; {{ __('admin.tickets.back') }}</a>
</div>

{{-- Ticket Info Bar --}}
<div class="card" style="margin-bottom:15px;">
    <div class="card-body" style="padding:10px 15px;display:flex;gap:20px;flex-wrap:wrap;font-size:13px;">
        <div><strong style="color:#777;">{{ __('admin.tickets.department_label') }}</strong> {{ $ticket->department->name ?? 'N/A' }}</div>
        <div><strong style="color:#777;">{{ __('admin.tickets.client_label') }}</strong>
            @if($ticket->client)
            <a href="{{ $ticket->client ? route("admin.clients.show", $ticket->client) : "#" }}" style="color:#337ab7;">{{ $ticket->client?->full_name ?? $ticket->name ?? $ticket->email }}</a>
            @else
            {{ $ticket->name ?? $ticket->email }}
            @endif
        </div>
        <div><strong style="color:#777;">{{ __('admin.tickets.email_label') }}</strong> {{ $ticket->email }}</div>
        <div><strong style="color:#777;">{{ __('admin.tickets.created_label') }}</strong> {{ $ticket->created_at->format('d M Y H:i') }}</div>
    </div>
</div>

{{-- Original Message --}}
<div class="card" style="margin-bottom:10px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <strong>{{ $ticket->name ?? $ticket->client?->full_name ?? $ticket->email }}</strong>
        <span style="font-size:12px;color:#777;">{{ $ticket->created_at->format('d M Y H:i') }}</span>
    </div>
    <div class="card-body" style="font-size:13px;line-height:1.6;color:#333;">{!! nl2br(e($ticket->message)) !!}</div>
</div>

{{-- Replies --}}
@foreach($ticket->replies as $reply)
<div style="margin-bottom:10px;border-radius:4px;overflow:hidden;border:1px solid {{ $reply->admin ? '#bce8f1' : '#ddd' }};border-left:4px solid {{ $reply->admin ? '#31708f' : '#ccc' }};">
    <div style="padding:8px 15px;background:{{ $reply->admin ? '#d9edf7' : '#f9f9f9' }};display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:13px;">{{ $reply->admin ? 'Staff: '.$reply->admin : ($ticket->client?->full_name ?? $ticket->name ?? $ticket->email) }}</strong>
        <span style="font-size:12px;color:#777;">{{ $reply->created_at->format('d M Y H:i') }}</span>
    </div>
    <div style="padding:12px 15px;font-size:13px;line-height:1.6;color:#333;background:#fff;">{!! nl2br(e($reply->message)) !!}</div>
</div>
@endforeach

{{-- Reply Form --}}
<div class="card" style="margin-bottom:15px;">
    <div class="card-header"><strong>{{ __('admin.tickets.add_reply') }}</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}">
            @csrf
            <div class="form-group">
                <textarea name="message" rows="6" required placeholder="{{ __('admin.tickets.reply_placeholder') }}" class="form-control"></textarea>
            </div>
            <div style="display:flex;gap:8px;margin-top:8px;">
                <button type="submit" class="btn btn-primary">{{ __('admin.tickets.send_reply') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Internal Notes --}}
@if(isset($ticket->notes) && $ticket->notes->count() > 0)
<div>
    <h3 style="font-size:14px;font-weight:600;color:#555;margin-bottom:8px;">{{ __('admin.tickets.internal_notes') }}</h3>
    @foreach($ticket->notes as $note)
    <div style="margin-bottom:8px;background:#fcf8e3;border:1px solid #faebcc;border-left:4px solid #e6ac00;border-radius:3px;overflow:hidden;">
        <div style="padding:6px 12px;background:#faf3cd;display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:12px;">{{ $note->admin }}</strong>
            <span style="font-size:11px;color:#8a6d3b;">{{ $note->created_at->format('d M Y H:i') }}</span>
        </div>
        <div style="padding:10px 12px;font-size:13px;color:#333;">{!! nl2br(e($note->message)) !!}</div>
    </div>
    @endforeach
</div>
@endif

@endsection
