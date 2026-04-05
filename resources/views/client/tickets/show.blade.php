@extends("client.layouts.app")
@section("title", "#". $ticket->tid ." - ". $ticket->title)
@section("content")

<a href="{{ route("client.tickets.index") }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.tickets.back_to_tickets') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">#{{ $ticket->tid }} &mdash; {{ $ticket->title }}</h1>
        <p class="pn-page-subtitle">
            {{ $ticket->department->name ?? "General" }}
            &nbsp;&middot;&nbsp; {{ __('client.tickets.opened') }} {{ $ticket->created_at->format("d M Y H:i") }}
            @if($ticket->last_reply) &nbsp;&middot;&nbsp; {{ __('client.tickets.last_reply') }} {{ $ticket->last_reply->diffForHumans() }} @endif
        </p>
    </div>
    <div class="flex gap-8 items-center">
        <span class="badge badge-{{ strtolower(str_replace(" ", "-", $ticket->status)) }}" style="font-size:12.5px;padding:4px 12px">{{ ucfirst($ticket->status) }}</span>
        <span class="badge badge-{{ strtolower($ticket->priority ?? "medium") }}" style="font-size:12.5px;padding:4px 12px">{{ ucfirst($ticket->priority ?? "Medium") }}</span>
    </div>
</div>

{{-- Original message --}}
<div class="pn-msg">
    <div class="pn-msg-head">
        <span class="pn-msg-author">{{ $ticket->name ?? auth()->user()->full_name }}</span>
        <span class="pn-msg-date">{{ $ticket->created_at->format("d M Y H:i") }}</span>
    </div>
    <div class="pn-msg-body">{{ $ticket->message }}</div>
    @if($ticket->attachment)
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border, #eee)">
        <a href="{{ route("client.tickets.attachment", $ticket) }}" style="color:#405189;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            {{ __('client.tickets.download_attachment') }}
        </a>
    </div>
    @endif
</div>

{{-- Replies --}}
@foreach($ticket->replies as $reply)
<div class="pn-msg {{ $reply->admin ? "staff" : "" }}">
    <div class="pn-msg-head">
        <span class="pn-msg-author">
            @if($reply->admin)
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-1px;margin-right:4px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                {{ __('client.tickets.support_staff') }}: {{ $reply->admin }}
            @else
                {{ auth()->user()->full_name }}
            @endif
        </span>
        <span class="pn-msg-date">{{ $reply->created_at->format("d M Y H:i") }}</span>
    </div>
    <div class="pn-msg-body">{{ $reply->message }}</div>
    @if($reply->attachment)
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border, #eee)">
        <a href="{{ route("client.tickets.reply.attachment", [$ticket, $reply->id]) }}" style="color:#405189;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            {{ __('client.tickets.download_attachment') }}
        </a>
    </div>
    @endif
</div>
@endforeach

{{-- Reply form --}}
@if($ticket->status !== "closed")
<div class="pn-card mt-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.tickets.post_reply') }}</span></div>
    <div class="pn-card-body">
        <form method="POST" action="{{ route("client.tickets.reply", $ticket) }}" enctype="multipart/form-data">
            @csrf
            @if($errors->any())
            <div class="pn-alert pn-alert-error">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $errors->first() }}
            </div>
            @endif
            <div class="form-group">
                <label class="form-label" for="message">{{ __('client.tickets.your_reply') }} <span class="req">*</span></label>
                <textarea id="message" name="message" rows="6" required class="form-control" placeholder="{{ __('client.tickets.reply_placeholder') }}"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('client.tickets.attachment') }} <span style="font-weight:400;color:var(--muted)">({{ __('client.form.optional') }}, {{ __('client.tickets.max_10mb') }})</span></label>
                <input type="file" name="attachment" accept=".jpg,.png,.gif,.pdf,.doc,.docx,.txt,.zip" class="form-control" style="padding:6px 10px;">
            </div>
            <div class="flex gap-8">
                <button type="submit" class="btn btn-primary">{{ __('client.tickets.post_reply_btn') }}</button>
                <a href="{{ route("client.tickets.index") }}" class="btn btn-outline">{{ __('client.tickets.back_to_tickets') }}</a>
            </div>
        </form>
    </div>
</div>
@else
<div class="pn-alert pn-alert-info mt-24">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    {{ __('client.tickets.ticket_closed') }} <a href="{{ route("client.tickets.create") }}" class="link">{{ __('client.tickets.open_new_ticket') }}</a> {{ __('client.tickets.need_further_assistance') }}
</div>
@endif

@endsection
